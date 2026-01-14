<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SPRINT 2: Overtime & Payroll Configuration
    |--------------------------------------------------------------------------
    |
    | Cấu hình tính lương và tăng ca cho hệ thống chấm công
    |
    */

    /**
     * Hệ số tăng ca (overtime rate)
     * 1.5 = tăng 50% (áp dụng cho giờ 8-10)
     */
    'overtime_rate' => env('OVERTIME_RATE', 1.5),

    /**
     * Hệ số tăng ca gấp đôi (overtime double rate)
     * 2.0 = tăng 100% (áp dụng cho giờ >10)
     */
    'overtime_double_rate' => env('OVERTIME_DOUBLE_RATE', 2.0),

    /**
     * Hệ số cuối tuần (weekend multiplier)
     * 2.0 = gấp đôi lương (áp dụng thứ 7, chủ nhật)
     */
    'weekend_multiplier' => env('WEEKEND_MULTIPLIER', 2.0),

    /**
     * Giờ bắt đầu nghỉ trưa (break time start)
     * Format: H:i
     */
    'break_time_start' => env('BREAK_TIME_START', '12:00'),

    /**
     * Giờ kết thúc nghỉ trưa (break time end)
     * Format: H:i
     */
    'break_time_end' => env('BREAK_TIME_END', '13:00'),

    /**
     * Số giờ làm chuẩn mỗi ngày
     * 8 giờ = ca làm chuẩn (không tính tăng ca)
     */
    'standard_work_hours' => env('STANDARD_WORK_HOURS', 8),

    /**
     * Ngưỡng bắt đầu tính tăng ca gấp đôi
     * 10 giờ = sau 10 giờ làm việc mới tính x2.0
     */
    'double_overtime_threshold' => env('DOUBLE_OVERTIME_THRESHOLD', 10),
];
