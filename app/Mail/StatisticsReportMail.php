<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * StatisticsReportMail - Email gửi báo cáo thống kê (đi trễ, số ngày làm) theo kỳ
 * File mẹ: Illuminate\Mail\Mailable
 * View: resources/views/emails/statistics-report.blade.php
 * Khác với PeriodReportMail (báo cáo lương): email này tập trung vào thống kê chấm công.
 */
class StatisticsReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $reportData;

    public $meta;

    /**
     * @param  array  $reportData (total_work_days, late_days, details[])
     * @param  array  $meta (period, title, range)
     */
    public function __construct($reportData, $meta)
    {
        $this->reportData = $reportData;
        $this->meta = $meta;
    }

    public function build()
    {
        $subject = '📊 '.($this->meta['title'] ?? 'Báo cáo thống kê').' - NCT Attendance';

        return $this->subject($subject)
            ->view('emails.statistics-report')
            ->with([
                'reportData' => $this->reportData,
                'meta' => $this->meta,
            ]);
    }
}
