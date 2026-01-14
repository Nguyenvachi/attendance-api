<?php

namespace App\Console\Commands;

use App\Services\GoogleSheetsReportExportService;
use Illuminate\Console\Command;

class GoogleSheetsExportCommand extends Command
{
    protected $signature = 'google-sheets:export
        {type : attendance-statistics | payroll}
        {--period=monthly : weekly|monthly|quarterly|yearly}
        {--mode= : append|replace (mặc định theo GOOGLE_SHEETS_EXPORT_MODE)}
        {--year= : Năm}
        {--week= : ISO week (1-53)}
        {--month= : Tháng (1-12)}
        {--quarter= : Quý (1-4)}';

    protected $description = 'Export báo cáo/thống kê lên Google Sheets (tách biệt NFC)';

    public function handle(GoogleSheetsReportExportService $exporter): int
    {
        $type = (string) $this->argument('type');
        $period = (string) $this->option('period');

        $params = [
            'period' => $period,
            'year' => $this->option('year') ? (int) $this->option('year') : (int) now()->year,
        ];

        if ($this->option('mode') !== null && $this->option('mode') !== '') {
            $params['mode'] = (string) $this->option('mode');
        }

        if ($this->option('week') !== null) {
            $params['week'] = (int) $this->option('week');
        }
        if ($this->option('month') !== null) {
            $params['month'] = (int) $this->option('month');
        }
        if ($this->option('quarter') !== null) {
            $params['quarter'] = (int) $this->option('quarter');
        }

        try {
            if ($type === 'attendance-statistics') {
                $result = $exporter->exportAttendanceStatistics($params);
            } elseif ($type === 'payroll') {
                $result = $exporter->exportPayroll($params);
            } else {
                $this->error('Type không hợp lệ. Dùng: attendance-statistics hoặc payroll');

                return self::INVALID;
            }

            $this->info('OK: export success');
            $this->line('Sheet: '.($result['sheet'] ?? ''));
            $this->line('Rows: '.($result['written_rows'] ?? 0));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Export failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
