<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Auto-sync Google Sheets (tắt mặc định)
        if (! config('services.google_sheets.auto_sync_enabled')) {
            return;
        }

        $mode = (string) config('services.google_sheets.export_mode', 'replace');
        $dailyAt = (string) config('services.google_sheets.auto_sync_daily_at', '23:55');
        $payrollDay = (int) config('services.google_sheets.auto_sync_payroll_day', 1);
        $payrollAt = (string) config('services.google_sheets.auto_sync_payroll_at', '00:10');
        $payrollDay = max(1, min(28, $payrollDay));

        // 1) Statistics: sync tháng hiện tại mỗi ngày (replace theo kỳ => không trùng)
        $schedule->call(function () use ($mode) {
            $now = now();
            $params = [
                'type' => 'attendance-statistics',
                '--period' => 'monthly',
                '--year' => (int) $now->year,
                '--month' => (int) $now->month,
                '--mode' => $mode,
            ];

            $code = Artisan::call('google-sheets:export', $params);
            Log::info('GoogleSheets auto-sync statistics done', [
                'exit_code' => $code,
                'year' => (int) $now->year,
                'month' => (int) $now->month,
                'mode' => $mode,
            ]);
        })->dailyAt($dailyAt);

        // 2) Payroll: chạy theo tháng, xuất THÁNG TRƯỚC để chốt lương
        $schedule->call(function () use ($mode) {
            $prev = now()->subMonth();
            $params = [
                'type' => 'payroll',
                '--period' => 'monthly',
                '--year' => (int) $prev->year,
                '--month' => (int) $prev->month,
                '--mode' => $mode,
            ];

            $code = Artisan::call('google-sheets:export', $params);
            Log::info('GoogleSheets auto-sync payroll done', [
                'exit_code' => $code,
                'year' => (int) $prev->year,
                'month' => (int) $prev->month,
                'mode' => $mode,
            ]);
        })->monthlyOn($payrollDay, $payrollAt);
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
