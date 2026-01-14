<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * MonthlyReportMail - Email gửi báo cáo lương hàng tháng cho nhân viên
 * File con của: Illuminate\Mail\Mailable
 * Sử dụng view: resources/views/emails/monthly-report.blade.php
 */
class MonthlyReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $employeeData;

    public $month;

    public $year;

    /**
     * Create a new message instance.
     *
     * @param  array  $employeeData (Dữ liệu nhân viên: name, email, work_hours, salary, details)
     * @param  int  $month (Tháng báo cáo: 1-12)
     * @param  int  $year (Năm báo cáo)
     * @return void
     */
    public function __construct($employeeData, $month, $year)
    {
        $this->employeeData = $employeeData;
        $this->month = $month;
        $this->year = $year;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject("📊 Báo cáo lương tháng {$this->month}/{$this->year} - NCT Attendance")
                    ->view('emails.monthly-report')
                    ->with([
                        'employeeData' => $this->employeeData,
                        'month' => $this->month,
                        'year' => $this->year,
                    ]);
    }
}
