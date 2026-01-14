<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Sheets\AddSheetRequest;
use Google\Service\Sheets as GoogleSheets;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\Request as SheetsRequest;
use Google\Service\Sheets\ValueRange;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleSheetsService
{
    private function makeClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setApplicationName('NCT Attendance');
        $client->setScopes([
            GoogleSheets::SPREADSHEETS,
        ]);

        $credentialsPath = config('services.google_sheets.credentials_path');
        $credentialsJson = config('services.google_sheets.credentials_json');

        if ($credentialsJson) {
            $json = $credentialsJson;

            // Hỗ trợ cả JSON raw và base64(JSON)
            $decoded = base64_decode($credentialsJson, true);
            if ($decoded !== false && str_starts_with(ltrim($decoded), '{')) {
                $json = $decoded;
            }

            $targetDir = storage_path('app/google');
            if (! is_dir($targetDir)) {
                @mkdir($targetDir, 0775, true);
            }

            $tmpPath = $targetDir.DIRECTORY_SEPARATOR.'service-account.'.Str::random(12).'.json';
            file_put_contents($tmpPath, $json);
            $client->setAuthConfig($tmpPath);

            // Best-effort cleanup
            register_shutdown_function(function () use ($tmpPath) {
                @unlink($tmpPath);
            });
        } elseif ($credentialsPath) {
            $client->setAuthConfig($credentialsPath);
        } else {
            throw new \RuntimeException('Thiếu cấu hình GOOGLE_SHEETS_CREDENTIALS_PATH hoặc GOOGLE_SHEETS_CREDENTIALS_JSON');
        }

        return $client;
    }

    private function makeSheets(): GoogleSheets
    {
        return new GoogleSheets($this->makeClient());
    }

    /**
     * Đảm bảo sheet/tab tồn tại (nếu chưa có thì tạo).
     */
    public function ensureSheetExists(string $spreadsheetId, string $sheetName): void
    {
        $sheets = $this->makeSheets();
        $spreadsheet = $sheets->spreadsheets->get($spreadsheetId);

        foreach (($spreadsheet->getSheets() ?? []) as $sheet) {
            if (($sheet->getProperties()?->getTitle()) === $sheetName) {
                return;
            }
        }

        $addSheetRequest = new AddSheetRequest([
            'properties' => [
                'title' => $sheetName,
            ],
        ]);

        $batch = new BatchUpdateSpreadsheetRequest([
            'requests' => [
                new SheetsRequest(['addSheet' => $addSheetRequest]),
            ],
        ]);

        $sheets->spreadsheets->batchUpdate($spreadsheetId, $batch);
    }

    /**
     * Nếu sheet đang trống, append header trước.
     */
    public function appendHeaderIfEmpty(string $spreadsheetId, string $sheetName, array $header): void
    {
        $sheets = $this->makeSheets();
        $endCol = $this->columnLetter(max(1, count($header)));
        $range = $sheetName.'!A1:'.$endCol.'1';
        $resp = $sheets->spreadsheets_values->get($spreadsheetId, $range);
        $values = $resp->getValues() ?? [];

        $current = [];
        if (! empty($values) && ! empty($values[0]) && is_array($values[0])) {
            $current = $values[0];
        }

        $isEmpty = count(array_filter($current, fn ($v) => (string) $v !== '')) === 0;
        $same = ! $isEmpty && ($this->normalizeRow($current) === $this->normalizeRow($header));
        if ($same) {
            return;
        }

        // Nếu trống: ghi header. Nếu khác: overwrite header để chuẩn hoá tiếng Việt.
        $body = new ValueRange([
            'values' => [$header],
        ]);
        $params = [
            'valueInputOption' => 'USER_ENTERED',
        ];

        try {
            $sheets->spreadsheets_values->update(
                $spreadsheetId,
                $range,
                $body,
                $params
            );
        } catch (\Throwable $e) {
            Log::error('GoogleSheets appendHeaderIfEmpty(update header) failed: '.$e->getMessage(), [
                'sheet' => $sheetName,
            ]);
            throw $e;
        }
    }

    private function columnLetter(int $index): string
    {
        $index = max(1, $index);
        $letters = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $index = intdiv(($index - 1), 26);
        }

        return $letters;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<int, string>
     */
    private function normalizeRow(array $row): array
    {
        return array_map(
            fn ($v) => trim((string) $v),
            $row
        );
    }

    /**
     * Append rows vào sheet (USER_ENTERED để Sheets tự format).
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function appendRows(string $spreadsheetId, string $sheetName, array $rows): array
    {
        $this->ensureSheetExists($spreadsheetId, $sheetName);

        $sheets = $this->makeSheets();
        $body = new ValueRange([
            'values' => $rows,
        ]);

        $params = [
            'valueInputOption' => 'USER_ENTERED',
            'insertDataOption' => 'INSERT_ROWS',
        ];

        try {
            $result = $sheets->spreadsheets_values->append(
                $spreadsheetId,
                $sheetName.'!A1',
                $body,
                $params
            );

            return [
                'updatedRange' => $result->getUpdates()?->getUpdatedRange(),
                'updatedRows' => $result->getUpdates()?->getUpdatedRows(),
                'spreadsheetId' => $spreadsheetId,
                'sheetName' => $sheetName,
            ];
        } catch (\Throwable $e) {
            Log::error('GoogleSheets appendRows failed: '.$e->getMessage(), [
                'sheet' => $sheetName,
            ]);
            throw $e;
        }
    }

    /**
     * Xóa các dòng trong sheet theo meta (period/start_date/end_date) để hỗ trợ chế độ replace.
     * Quy ước cột:
     * - A: exported_at
     * - B: period
     * - C: start_date
     * - D: end_date
     * Header nằm ở row 1, dữ liệu từ row 2.
     */
    public function deleteRowsByMeta(
        string $spreadsheetId,
        string $sheetName,
        string $period,
        string $startDate,
        string $endDate,
    ): int {
        $this->ensureSheetExists($spreadsheetId, $sheetName);

        $sheets = $this->makeSheets();

        // Lấy sheetId để deleteDimension
        $sheetId = $this->getSheetIdByName($sheets, $spreadsheetId, $sheetName);
        if ($sheetId === null) {
            return 0;
        }

        // Đọc các cột A..D từ row 2 trở đi
        $range = $sheetName.'!A2:D';
        $resp = $sheets->spreadsheets_values->get($spreadsheetId, $range);
        $values = $resp->getValues() ?? [];
        if (count($values) === 0) {
            return 0;
        }

        $matchedRows = [];
        // $values[0] tương ứng row 2
        foreach ($values as $i => $row) {
            $rowPeriod = $row[1] ?? null;
            $rowStart = $row[2] ?? null;
            $rowEnd = $row[3] ?? null;

            if ($rowPeriod === $period && $rowStart === $startDate && $rowEnd === $endDate) {
                $matchedRows[] = $i + 2; // 1-based row number in sheet
            }
        }

        if (count($matchedRows) === 0) {
            return 0;
        }

        sort($matchedRows);

        // Group contiguous row numbers into [startRow, endRowExclusive) blocks (1-based)
        $blocks = [];
        $start = $matchedRows[0];
        $prev = $matchedRows[0];
        for ($idx = 1; $idx < count($matchedRows); $idx++) {
            $cur = $matchedRows[$idx];
            if ($cur === $prev + 1) {
                $prev = $cur;

                continue;
            }
            $blocks[] = [$start, $prev + 1];
            $start = $cur;
            $prev = $cur;
        }
        $blocks[] = [$start, $prev + 1];

        // Delete from bottom to top to avoid index shifting.
        $requests = [];
        $deletedCount = 0;
        for ($b = count($blocks) - 1; $b >= 0; $b--) {
            [$rowStart, $rowEndExclusive] = $blocks[$b];

            $deletedCount += ($rowEndExclusive - $rowStart);

            $requests[] = new SheetsRequest([
                'deleteDimension' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'ROWS',
                        'startIndex' => $rowStart - 1,
                        'endIndex' => $rowEndExclusive - 1,
                    ],
                ],
            ]);
        }

        try {
            $batch = new BatchUpdateSpreadsheetRequest([
                'requests' => $requests,
            ]);
            $sheets->spreadsheets->batchUpdate($spreadsheetId, $batch);
        } catch (\Throwable $e) {
            Log::error('GoogleSheets deleteRowsByMeta failed: '.$e->getMessage(), [
                'sheet' => $sheetName,
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
            throw $e;
        }

        return $deletedCount;
    }

    private function getSheetIdByName(GoogleSheets $sheets, string $spreadsheetId, string $sheetName): ?int
    {
        $spreadsheet = $sheets->spreadsheets->get($spreadsheetId, [
            'fields' => 'sheets(properties(sheetId,title))',
        ]);

        foreach (($spreadsheet->getSheets() ?? []) as $sheet) {
            $props = $sheet->getProperties();
            if ($props && $props->getTitle() === $sheetName) {
                return (int) $props->getSheetId();
            }
        }

        return null;
    }
}
