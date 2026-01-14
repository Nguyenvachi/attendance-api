<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shift_id',
        'check_in_time',
        'check_out_time',
        'work_hours',
        'device_info',
        // SPRINT 2: Overtime columns
        'regular_hours',
        'overtime_hours',
        'overtime_double_hours',
        'break_hours',
        'timezone',
    ];

    // BỔ SUNG: cast datetime để controller có thể dùng ->format() an toàn
    // SPRINT 2: Thêm timezone cast
    protected $casts = [
        'check_in_time' => 'datetime:Asia/Ho_Chi_Minh',
        'check_out_time' => 'datetime:Asia/Ho_Chi_Minh',
        'work_hours' => 'decimal:2',
        'regular_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'overtime_double_hours' => 'decimal:2',
        'break_hours' => 'decimal:2',
    ];

    /**
     * Relationship: Attendance thuộc về User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Attendance thuộc về Shift
     */
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * SPRINT 2: Tính lương chính xác với overtime + break time + weekend bonus
     *
     * Logic:
     * - Regular hours (0-8h): hourly_rate x 1.0
     * - Overtime (8-10h): hourly_rate x 1.5
     * - Overtime double (>10h): hourly_rate x 2.0
     * - Trừ break time (12:00-13:00 không tính lương)
     * - Weekend bonus: x2.0 nếu là thứ 7/CN
     *
     * @return float Tổng lương
     */
    public function calculateSalary()
    {
        if (! $this->check_in_time || ! $this->check_out_time) {
            return 0;
        }

        $checkIn = Carbon::parse($this->check_in_time);
        $checkOut = Carbon::parse($this->check_out_time);
        $config = config('payroll');

        // 1. Tính tổng giờ làm (chưa trừ nghỉ)
        $totalMinutes = $checkIn->diffInMinutes($checkOut);

        // 2. Trừ giờ nghỉ trưa (12:00-13:00)
        $lunchStart = $checkIn->copy()->setTimeFromTimeString($config['break_time_start']);
        $lunchEnd = $checkIn->copy()->setTimeFromTimeString($config['break_time_end']);

        $breakMinutes = 0;
        if ($checkIn < $lunchEnd && $checkOut > $lunchStart) {
            $breakStart = max($checkIn, $lunchStart);
            $breakEnd = min($checkOut, $lunchEnd);
            $breakMinutes = $breakStart->diffInMinutes($breakEnd);
        }

        $workMinutes = $totalMinutes - $breakMinutes;
        $totalHours = $workMinutes / 60;

        // 3. Phân chia giờ: regular / overtime / overtime_double
        $standardHours = $config['standard_work_hours']; // 8h
        $doubleThreshold = $config['double_overtime_threshold']; // 10h

        $regularHours = min($totalHours, $standardHours);
        $overtimeHours = max(0, min($totalHours - $standardHours, $doubleThreshold - $standardHours)); // 8-10h
        $overtimeDoubleHours = max(0, $totalHours - $doubleThreshold); // >10h

        // 4. Lấy lương theo giờ của user
        $hourlyRate = $this->user->hourly_rate ?? 0;

        // 5. Weekend bonus (thứ 7, CN x2)
        $isWeekend = $checkIn->isWeekend();
        $weekendMultiplier = $isWeekend ? $config['weekend_multiplier'] : 1;

        // 6. Tính lương
        $salary = (
            $regularHours * $hourlyRate * $weekendMultiplier +
            $overtimeHours * $hourlyRate * $config['overtime_rate'] * $weekendMultiplier +
            $overtimeDoubleHours * $hourlyRate * $config['overtime_double_rate'] * $weekendMultiplier
        );

        // 7. Lưu breakdown vào database
        $this->update([
            'work_hours' => round($totalHours, 2),
            'regular_hours' => round($regularHours, 2),
            'overtime_hours' => round($overtimeHours, 2),
            'overtime_double_hours' => round($overtimeDoubleHours, 2),
            'break_hours' => round($breakMinutes / 60, 2),
        ]);

        return round($salary, 2);
    }
}
