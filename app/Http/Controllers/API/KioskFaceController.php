<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * KioskFaceController - chấm công bằng khuôn mặt (Face Recognition).
 * Tách riêng khỏi /kiosk/attendance để KHÔNG ảnh hưởng NFC.
 */
class KioskFaceController extends Controller
{
    /**
     * POST /api/kiosk/attendance-face
     * Body: { user_id, match_score?, model_version? }
     */
    public function attendanceFace(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'match_score' => 'nullable|numeric',
            'model_version' => 'nullable|string|max:64',
        ]);

        $user = User::find((int) $validated['user_id']);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy thông tin nhân viên.',
                'sound' => 'error',
            ], 404);
        }

        if (isset($user->is_active) && $user->is_active === false) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tài khoản đã bị khóa. Không thể chấm công.',
                'sound' => 'error',
            ], 403);
        }

        $today = Carbon::today();
        $latestAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('check_in_time', $today)
            ->latest('check_in_time')
            ->first();

        // CHECK-IN
        if (! $latestAttendance || $latestAttendance->check_out_time !== null) {
            $newAttendance = Attendance::create([
                'user_id' => $user->id,
                'shift_id' => 1,
                'check_in_time' => now(),
                'device_info' => 'Kiosk FACE Terminal - '.$request->ip().
                    ' (score='.($validated['match_score'] ?? 'n/a').
                    ', model='.($validated['model_version'] ?? 'n/a').')',
            ]);

            return response()->json([
                'status' => 'success',
                'type' => 'check_in',
                'message' => "Xin chào {$user->name}! Chúc bạn làm việc vui vẻ! 🌟",
                'data' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'attendance_id' => $newAttendance->id,
                    'check_in_time' => $newAttendance->check_in_time->format('H:i:s'),
                    'date' => $newAttendance->check_in_time->format('d/m/Y'),
                    'day_of_week' => $newAttendance->check_in_time->locale('vi')->dayName,
                ],
                'sound' => 'welcome',
            ], 201);
        }

        // CHECK-OUT
        $checkOutTime = now();
        $workHours = $checkOutTime->diffInMinutes($latestAttendance->check_in_time, true) / 60;

        $latestAttendance->update([
            'check_out_time' => $checkOutTime,
            'work_hours' => round($workHours, 2),
        ]);

        $earnedSalary = round($workHours * $user->hourly_rate, 0);

        return response()->json([
            'status' => 'success',
            'type' => 'check_out',
            'message' => "Tạm biệt {$user->name}! Hẹn gặp lại! 👋",
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'attendance_id' => $latestAttendance->id,
                'check_in_time' => $latestAttendance->check_in_time->format('H:i:s'),
                'check_out_time' => $checkOutTime->format('H:i:s'),
                'work_hours' => round($workHours, 2),
                'work_hours_formatted' => floor($workHours).' giờ '.round(($workHours - floor($workHours)) * 60).' phút',
                'earned_salary' => $earnedSalary,
                'earned_salary_formatted' => number_format($earnedSalary, 0, ',', '.').' VNĐ',
            ],
            'sound' => 'goodbye',
        ], 200);
    }
}
