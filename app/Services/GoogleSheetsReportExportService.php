<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;

class GoogleSheetsReportExportService
{
    public function __construct(
        private GoogleSheetsService $sheets,
    ) {
    }

    /**
     * Export thống kê đi trễ/số ngày làm (dạng theo từng ngày) lên Google Sheet.
     * Chỉ ADD, không thay đổi endpoint report hiện có.
     */
    public function exportAttendanceStatistics(array $params): array
    {
        $spreadsheetId = config('services.google_sheets.spreadsheet_id');
        if (! $spreadsheetId) {
            throw new \RuntimeException('Thiếu GOOGLE_SHEETS_SPREADSHEET_ID');
        }

        $sheetName = config('services.google_sheets.statistics_sheet', 'Statistics');

        $mode = (string) ($params['mode'] ?? config('services.google_sheets.export_mode', 'replace'));
        if (! in_array($mode, ['append', 'replace'], true)) {
            $mode = 'replace';
        }

        [$start, $end, $meta] = $this->resolvePeriod($params);

        // Mặc định export toàn bộ staff
        $userIds = $params['user_ids'] ?? null;
        $usersQuery = User::query()->where('role', 'staff');
        if (is_array($userIds) && count($userIds) > 0) {
            $usersQuery->whereIn('id', $userIds);
        }
        $userIdList = $usersQuery->pluck('id')->all();

        $attendances = Attendance::query()
            ->whereIn('user_id', $userIdList)
            ->whereBetween('check_in_time', [$start, $end])
            ->with(['user', 'shift'])
            ->orderBy('check_in_time')
            ->get();

        $header = [
            'Thời điểm xuất',
            'Kỳ',
            'Ngày bắt đầu',
            'Ngày kết thúc',
            'Mã nhân viên',
            'Tên nhân viên',
            'Mã chấm công',
            'Ngày',
            'Giờ vào',
            'Giờ bắt đầu ca',
            'Đi trễ',
            'Số phút trễ',
        ];

        $rows = [];
        $exportedAt = now()->format('Y-m-d H:i:s');

        foreach ($attendances as $att) {
            $checkIn = Carbon::parse($att->check_in_time);
            $shiftStart = $att->shift ? Carbon::parse($att->shift->start_time) : null;
            $isLate = $shiftStart ? $checkIn->greaterThan($shiftStart) : false;

            $rows[] = [
                $exportedAt,
                $meta['period'] ?? null,
                $meta['start_date'] ?? null,
                $meta['end_date'] ?? null,
                $att->user_id,
                $att->user?->name,
                $att->id,
                $checkIn->format('Y-m-d'),
                $checkIn->format('H:i'),
                $shiftStart ? $shiftStart->format('H:i') : null,
                $isLate ? 'Có' : 'Không',
                ($isLate && $shiftStart) ? $checkIn->diffInMinutes($shiftStart) : 0,
            ];
        }

        $this->sheets->ensureSheetExists($spreadsheetId, $sheetName);
        $this->sheets->appendHeaderIfEmpty($spreadsheetId, $sheetName, $header);

        // Chuẩn thực tế: replace theo kỳ để tránh trùng dữ liệu khi sync nhiều lần
        $deleted = 0;
        if ($mode === 'replace' && ! empty($meta['period']) && ! empty($meta['start_date']) && ! empty($meta['end_date'])) {
            $deleted = $this->sheets->deleteRowsByMeta(
                $spreadsheetId,
                $sheetName,
                (string) $meta['period'],
                (string) $meta['start_date'],
                (string) $meta['end_date'],
            );
        }

        if (count($rows) === 0) {
            return [
                'status' => 'success',
                'message' => 'Không có dữ liệu attendance trong khoảng thời gian đã chọn',
                'meta' => $meta,
                'mode' => $mode,
                'deleted_rows' => $deleted,
                'written_rows' => 0,
                'sheet' => $sheetName,
            ];
        }

        $result = $this->sheets->appendRows($spreadsheetId, $sheetName, $rows);

        return [
            'status' => 'success',
            'meta' => $meta,
            'sheet' => $sheetName,
            'mode' => $mode,
            'deleted_rows' => $deleted,
            'written_rows' => count($rows),
            'google' => $result,
        ];
    }

    /**
     * Export payroll tổng hợp theo nhân viên lên Google Sheet.
     */
    public function exportPayroll(array $params): array
    {
        $spreadsheetId = config('services.google_sheets.spreadsheet_id');
        if (! $spreadsheetId) {
            throw new \RuntimeException('Thiếu GOOGLE_SHEETS_SPREADSHEET_ID');
        }

        $sheetName = config('services.google_sheets.payroll_sheet', 'Payroll');

        $mode = (string) ($params['mode'] ?? config('services.google_sheets.export_mode', 'replace'));
        if (! in_array($mode, ['append', 'replace'], true)) {
            $mode = 'replace';
        }

        [$start, $end, $meta] = $this->resolvePeriod($params);

        $users = User::query()->where('role', 'staff')->get();

        $header = [
            'Thời điểm xuất',
            'Kỳ',
            'Ngày bắt đầu',
            'Ngày kết thúc',
            'Mã nhân viên',
            'Tên nhân viên',
            'Lương theo giờ',
            'Số ngày công',
            'Tổng giờ làm',
            'Tổng lương',
        ];

        $rows = [];
        $exportedAt = now()->format('Y-m-d H:i:s');

        foreach ($users as $user) {
            $attendances = Attendance::query()
                ->where('user_id', $user->id)
                ->whereBetween('check_in_time', [$start, $end])
                ->whereNotNull('work_hours')
                ->get();

            $totalWorkHours = (float) $attendances->sum('work_hours');
            $totalSalary = $totalWorkHours * (float) $user->hourly_rate;

            // Chuẩn nghiệp vụ: ngày công = số ngày DISTINCT có check-in trong kỳ
            $totalDaysWorked = (int) $attendances
                ->map(fn ($a) => Carbon::parse($a->check_in_time)->toDateString())
                ->unique()
                ->count();

            $rows[] = [
                $exportedAt,
                $meta['period'] ?? null,
                $meta['start_date'] ?? null,
                $meta['end_date'] ?? null,
                $user->id,
                $user->name,
                $user->hourly_rate,
                $totalDaysWorked,
                round($totalWorkHours, 2),
                round($totalSalary, 0),
            ];
        }

        $this->sheets->ensureSheetExists($spreadsheetId, $sheetName);
        $this->sheets->appendHeaderIfEmpty($spreadsheetId, $sheetName, $header);

        // Chuẩn thực tế: replace theo kỳ để tránh trùng dữ liệu khi sync nhiều lần
        $deleted = 0;
        if ($mode === 'replace' && ! empty($meta['period']) && ! empty($meta['start_date']) && ! empty($meta['end_date'])) {
            $deleted = $this->sheets->deleteRowsByMeta(
                $spreadsheetId,
                $sheetName,
                (string) $meta['period'],
                (string) $meta['start_date'],
                (string) $meta['end_date'],
            );
        }

        $result = $this->sheets->appendRows($spreadsheetId, $sheetName, $rows);

        return [
            'status' => 'success',
            'meta' => $meta,
            'sheet' => $sheetName,
            'mode' => $mode,
            'deleted_rows' => $deleted,
            'written_rows' => count($rows),
            'google' => $result,
        ];
    }

    /**
     * Resolve period range theo kiểu đang dùng trong ReportController/PayrollController.
     *
     * @return array{0:Carbon,1:Carbon,2:array}
     */
    private function resolvePeriod(array $params): array
    {
        $period = $params['period'] ?? 'monthly';
        $year = (int) ($params['year'] ?? now()->year);

        if ($year < 2000 || $year > 2100) {
            throw new \InvalidArgumentException('Năm không hợp lệ');
        }

        if ($period === 'weekly') {
            $week = (int) ($params['week'] ?? now()->isoWeek);
            if ($week < 1 || $week > 53) {
                throw new \InvalidArgumentException('Tuần không hợp lệ');
            }
            $start = Carbon::now()->setISODate($year, $week)->startOfWeek(Carbon::MONDAY);
            $end = (clone $start)->endOfWeek(Carbon::SUNDAY);

            return [$start, $end, [
                'period' => 'weekly',
                'year' => $year,
                'week' => $week,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]];
        }

        if ($period === 'quarterly') {
            $quarter = (int) ($params['quarter'] ?? ceil(now()->month / 3));
            if ($quarter < 1 || $quarter > 4) {
                throw new \InvalidArgumentException('Quý không hợp lệ');
            }
            $startMonth = ($quarter - 1) * 3 + 1;
            $start = Carbon::create($year, $startMonth, 1)->startOfDay();
            $end = (clone $start)->addMonths(2)->endOfMonth()->endOfDay();

            return [$start, $end, [
                'period' => 'quarterly',
                'year' => $year,
                'quarter' => $quarter,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]];
        }

        if ($period === 'yearly') {
            $start = Carbon::create($year, 1, 1)->startOfDay();
            $end = Carbon::create($year, 12, 31)->endOfDay();

            return [$start, $end, [
                'period' => 'yearly',
                'year' => $year,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]];
        }

        // monthly (default)
        $month = (int) ($params['month'] ?? now()->month);
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Tháng không hợp lệ');
        }

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = (clone $start)->endOfMonth()->endOfDay();

        return [$start, $end, [
            'period' => 'monthly',
            'year' => $year,
            'month' => $month,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]];
    }
}
