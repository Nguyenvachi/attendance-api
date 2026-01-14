<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * Điểm danh (Staff)
     * POST /api/attendance
     *
     * BỔ SUNG: Logic check-in/check-out tự động (giống Kiosk)
     * - Lần 1: Check-in
     * - Lần 2 (quét lại): Check-out
     */
    public function store(Request $request)
    {
        $shift = Shift::where('code', $request->code)->first();

        if (! $shift) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mã QR không hợp lệ',
            ], 404);
        }

        // THÊM MỚI: Kiểm tra GPS nếu ca làm việc có thiết lập vị trí
        if ($shift->latitude && $shift->longitude) {
            if (! $request->latitude || ! $request->longitude) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vui lòng bật GPS để điểm danh',
                ], 400);
            }

            $distance = $shift->calculateDistance(
                $request->latitude,
                $request->longitude
            );

            if ($distance > $shift->radius) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bạn đang ở quá xa địa điểm làm việc',
                    'data' => [
                        'distance' => round($distance, 2),
                        'max_distance' => $shift->radius,
                    ],
                ], 403);
            }
        }

        // SPRINT 2: Wrap logic chấm công trong DB::transaction() + lockForUpdate()
        $user = $request->user();
        $today = Carbon::today();

        $result = DB::transaction(function () use ($user, $today, $shift, $request) {
            $latestAttendance = Attendance::where('user_id', $user->id)
                ->whereDate('check_in_time', $today)
                ->lockForUpdate() // Prevent race condition khi 2 requests đồng thời
                ->latest('check_in_time')
                ->first();

            // Nếu đã check-in chưa check-out (quét QR lần 2) → Tự động CHECK-OUT
            if ($latestAttendance && $latestAttendance->check_out_time === null) {
                $checkOutTime = now();
                $workHours = $checkOutTime->diffInMinutes($latestAttendance->check_in_time, true) / 60;

                $latestAttendance->update([
                    'check_out_time' => $checkOutTime,
                    'work_hours' => round($workHours, 2),
                ]);

                // SPRINT 2: Tự động tính lương với overtime
                $earnedSalary = $latestAttendance->calculateSalary();

                return [
                    'status' => 'success',
                    'type' => 'check_out',
                    'message' => 'Check-out thành công! Tạm biệt và hẹn gặp lại!',
                    'data' => [
                        'attendance_id' => $latestAttendance->id,
                        'shift_name' => $latestAttendance->shift->name,
                        'check_in_time' => $latestAttendance->check_in_time->format('H:i:s'),
                        'check_out_time' => $checkOutTime->format('H:i:s'),
                        'work_hours' => round($workHours, 2),
                        'work_hours_formatted' => floor($workHours).' giờ '.round(($workHours - floor($workHours)) * 60).' phút',
                        'earned_salary' => $earnedSalary,
                        'earned_salary_formatted' => number_format($earnedSalary, 0, ',', '.').' VNĐ',
                        // SPRINT 2: Thêm overtime breakdown
                        'regular_hours' => $latestAttendance->regular_hours,
                        'overtime_hours' => $latestAttendance->overtime_hours,
                        'overtime_double_hours' => $latestAttendance->overtime_double_hours,
                        'break_hours' => $latestAttendance->break_hours,
                    ],
                    'http_code' => 200,
                ];
            }

            // Nếu chưa check-in hoặc đã check-out xong → CHECK-IN MỚI
            $attendance = Attendance::create([
                'user_id' => $user->id,
                'shift_id' => $shift->id,
                'check_in_time' => now(),
                'device_info' => $request->device_info,
                'timezone' => $request->timezone ?? 'Asia/Ho_Chi_Minh', // SPRINT 2: Lưu timezone
            ]);

            return [
                'status' => 'success',
                'type' => 'check_in',
                'message' => 'Điểm danh thành công',
                'data' => $attendance->load('shift'),
                'http_code' => 201,
            ];
        });

        $httpCode = $result['http_code'];
        unset($result['http_code']);

        return response()->json($result, $httpCode);
    }

    /**
     * POST /api/attendance/checkout
     * Staff tự check-out (gọi sau khi đã check-in)
     * Tìm bản ghi chấm công mới nhất chưa check-out của user trong ngày hôm nay và cập nhật.
     */
    public function checkout(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();

        // SPRINT 2: Wrap logic check-out trong DB::transaction() + lockForUpdate()
        $result = DB::transaction(function () use ($user, $today) {
            // Tìm bản ghi check-in mới nhất chưa check-out trong ngày
            $latestAttendance = Attendance::where('user_id', $user->id)
                ->whereDate('check_in_time', $today)
                ->whereNull('check_out_time')
                ->lockForUpdate() // Prevent duplicate checkout
                ->latest('check_in_time')
                ->first();

            if (! $latestAttendance) {
                return [
                    'status' => 'error',
                    'message' => 'Không tìm thấy bản ghi check-in trong ngày hôm nay hoặc bạn đã check-out rồi.',
                    'http_code' => 404,
                ];
            }

            $checkOutTime = now();
            // Tính giờ làm chính xác theo phút
            $workHours = $checkOutTime->diffInMinutes($latestAttendance->check_in_time, true) / 60;

            $latestAttendance->update([
                'check_out_time' => $checkOutTime,
                'work_hours' => round($workHours, 2),
            ]);

            // SPRINT 2: Tự động tính lương với overtime
            $earnedSalary = $latestAttendance->calculateSalary();

            return [
                'status' => 'success',
                'message' => 'Check-out thành công',
                'data' => [
                    'attendance_id' => $latestAttendance->id,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'check_in_time' => $latestAttendance->check_in_time->format('H:i:s'),
                    'check_out_time' => $checkOutTime->format('H:i:s'),
                    'work_hours' => round($workHours, 2),
                    'work_hours_formatted' => floor($workHours).' giờ '.round(($workHours - floor($workHours)) * 60).' phút',
                    'earned_salary' => $earnedSalary,
                    'earned_salary_formatted' => number_format($earnedSalary, 0, ',', '.').' VNĐ',
                    // SPRINT 2: Thêm overtime breakdown
                    'regular_hours' => $latestAttendance->regular_hours,
                    'overtime_hours' => $latestAttendance->overtime_hours,
                    'overtime_double_hours' => $latestAttendance->overtime_double_hours,
                    'break_hours' => $latestAttendance->break_hours,
                ],
                'http_code' => 200,
            ];
        });

        $httpCode = $result['http_code'];
        unset($result['http_code']);

        return response()->json($result, $httpCode);
    }
}
