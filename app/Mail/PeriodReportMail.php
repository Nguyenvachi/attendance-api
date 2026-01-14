<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * PeriodReportMail - Email gửi báo cáo theo kỳ (tuần/quý/năm) cho nhân viên
 * File mẹ: Illuminate\Mail\Mailable
 * View: resources/views/emails/period-report.blade.php
 */
class PeriodReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $employeeData;

    public $meta;

    /**
     * @param  array  $employeeData
     * @param  array  $meta (period + thông tin kỳ)
     */
    public function __construct($employeeData, $meta)
    {
        $this->employeeData = $employeeData;
        $this->meta = $meta;
    }

    public function build()
    {
        $subject = '📊 Báo cáo '.($this->meta['title'] ?? 'theo kỳ').' - NCT Attendance';

        return $this->subject($subject)
            ->view('emails.period-report')
            ->with([
                'employeeData' => $this->employeeData,
                'meta' => $this->meta,
            ]);
    }
}
