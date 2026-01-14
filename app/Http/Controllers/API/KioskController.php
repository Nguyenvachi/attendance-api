<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Services\NfcPayloadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * KioskController - Controller xử lý chấm công Kiosk NFC/Biometric
 * File con của: app/Http/Controllers/Controller.php
 * Phục vụ: Máy chấm công cố định (Kiosk Mode)
 */
class KioskController extends Controller
{
    /**
     * POST /api/kiosk/attendance
     * Xử lý chấm công qua NFC/Biometric (Không cần authentication)
     * Logic: Tự động phát hiện Check-in hoặc Check-out dựa vào lịch sử
     *
     * @param  Request  $request (nfc_code hoặc biometric_id)
     * @return \Illuminate\Http\JsonResponse
     */
    public function attendance(Request $request)
    {
        // Validate: Phải có ít nhất 1 trong 2 (nfc_code hoặc biometric_id)
        $request->validate([
            'nfc_code' => 'required_without:biometric_id|string',
            'biometric_id' => 'required_without:nfc_code|string',
        ]);

        // Tìm user theo NFC hoặc Biometric ID
        $user = null;
        if ($request->nfc_code) {
            // BỔ SUNG: ưu tiên parse NFC payload (NDEF) nếu có
            // Payload dạng: NCTNFC:v1:<user_id>:<token>
            $nfcPayloadService = app(NfcPayloadService::class);
            $user = $nfcPayloadService->resolveUserFromNfcCode($request->nfc_code);

            // BỔ SUNG: normalize nfc_code để tránh lỗi whitespace/null (NDEF)
            $nfcCodeRaw = (string) $request->nfc_code;
            $nfcCode = trim(str_replace("\0", '', $nfcCodeRaw));
            $user = $nfcPayloadService->resolveUserFromNfcCode($nfcCode);

            // Fallback tương thích ngược: lookup theo UID như cũ
            if (! $user) {
                $user = User::where('nfc_uid', $request->nfc_code)->first();
            }

            // BỔ SUNG: fallback UID với nfc_code đã normalize
            if (! $user) {
                $user = User::where('nfc_uid', $nfcCode)->first();
            }
        } elseif ($request->biometric_id) {
            // BỔ SUNG: Lookup user theo biometric_id (vân tay/khuôn mặt)
            $user = User::where('biometric_id', $request->biometric_id)->first();
        }

        // Kiểm tra user có tồn tại không
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy thông tin nhân viên. Vui lòng liên hệ quản lý.',
                'sound' => 'error',
            ], 404);
        }

        // BỔ SUNG: Nếu tài khoản bị khóa thì không cho chấm công
        if (isset($user->is_active) && $user->is_active === false) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tài khoản đã bị khóa. Không thể chấm công.',
                'sound' => 'error',
            ], 403);
        }

        // Tìm bản ghi chấm công mới nhất trong ngày hôm nay
        // SPRINT 2: Wrap trong DB::transaction() + lockForUpdate() để prevent race condition
        $today = Carbon::today();

        $result = DB::transaction(function () use ($user, $today, $request) {
            $latestAttendance = Attendance::where('user_id', $user->id)
                ->whereDate('check_in_time', $today)
                ->lockForUpdate() // Lock row để prevent concurrent check-in
                ->latest('check_in_time')
                ->first();

            // TRƯỜNG HỢP 1: CHECK-IN (Chưa có bản ghi hoặc bản ghi cũ đã check-out đầy đủ)
            if (! $latestAttendance || $latestAttendance->check_out_time !== null) {
                // BỔ SUNG: Tự động phát hiện ca làm việc dựa vào giờ check-in
                // Thay shift_id = 1 hardcode bằng auto-detect để chính xác hơn
                $detectedShiftId = \App\Models\Shift::detectShiftByTime();

                $newAttendance = Attendance::create([
                    'user_id' => $user->id,
                    'shift_id' => $detectedShiftId, // BỔ SUNG: Auto-detect thay vì hardcode = 1
                    'check_in_time' => now(),
                    'device_info' => 'Kiosk NFC Terminal - '.$request->ip(),
                    'timezone' => $request->timezone ?? 'Asia/Ho_Chi_Minh', // SPRINT 2: Lưu timezone
                ]);

                return [
                    'status' => 'success',
                    'type' => 'check_in',
                    'message' => "Xin chào {$user->name}! Chúc bạn làm việc vui vẻ! 🌟",
                    'data' => [
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'user_email' => $user->email,
                        'attendance_id' => $newAttendance->id,
                        'check_in_time' => $newAttendance->check_in_time->format('H:i:s'),
                        'date' => $newAttendance->check_in_time->format('d/m/Y'),
                        'day_of_week' => $newAttendance->check_in_time->locale('vi')->dayName,
                    ],
                    'sound' => 'welcome',
                    'http_code' => 201,
                ];
            }

            // TRƯỜNG HỢP 2: CHECK-OUT (Đã check-in nhưng chưa check-out)
            if ($latestAttendance && $latestAttendance->check_out_time === null) {
                $checkOutTime = now();

                // BỔ SUNG (không thay thế code cũ): Tính giờ làm chính xác theo phút
                // Tránh làm tròn theo giờ nguyên khi dùng diffInHours()
                $workHours = $checkOutTime->diffInMinutes($latestAttendance->check_in_time, true) / 60;

                // Cập nhật bản ghi: thêm giờ ra và tổng giờ làm
                $latestAttendance->update([
                    'check_out_time' => $checkOutTime,
                    'work_hours' => round($workHours, 2),
                ]);

                // SPRINT 2: Tự động tính lương với overtime
                $earnedSalary = $latestAttendance->calculateSalary();

                return [
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
                        // SPRINT 2: Thêm overtime breakdown
                        'regular_hours' => $latestAttendance->regular_hours,
                        'overtime_hours' => $latestAttendance->overtime_hours,
                        'overtime_double_hours' => $latestAttendance->overtime_double_hours,
                        'break_hours' => $latestAttendance->break_hours,
                    ],
                    'sound' => 'goodbye',
                    'http_code' => 200,
                ];
            }
        });

        $httpCode = $result['http_code'];
        unset($result['http_code']);

        return response()->json($result, $httpCode);
    }

    /**
     * POST /api/admin/manual-attendance
     * Chấm công thủ công cho nhân viên (Admin/Manager only)
     * Sử dụng khi: Nhân viên quên chấm công, máy hỏng, điều chỉnh sai sót
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function manualAttendance(Request $request)
    {
        // Validate input
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'check_in' => 'required|date_format:Y-m-d H:i:s',
            'check_out' => 'nullable|date_format:Y-m-d H:i:s|after:check_in',
            'shift_id' => 'nullable|exists:shifts,id',
        ]);

        // Parse datetime
        $checkIn = Carbon::parse($request->check_in);
        $checkOut = $request->check_out ? Carbon::parse($request->check_out) : null;
        $workHours = $checkOut ? $checkOut->diffInHours($checkIn, true) : null;

        // BỔ SUNG (không thay thế code cũ): Tính giờ làm theo phút để chính xác
        $workHoursPrecise = $checkOut ? ($checkOut->diffInMinutes($checkIn, true) / 60) : null;

        // Tạo bản ghi chấm công thủ công
        $attendance = Attendance::create([
            'user_id' => $request->user_id,
            'shift_id' => $request->shift_id ?? 1,
            'check_in_time' => $checkIn,
            'check_out_time' => $checkOut,
            'work_hours' => $workHours ? round($workHours, 2) : null,
            // BỔ SUNG: override work_hours bằng giá trị precise (cho phép 0.0)
            'work_hours' => is_null($workHoursPrecise) ? null : round($workHoursPrecise, 2),
            'device_info' => 'Manual Entry by Admin: '.$request->user()->name.' (ID: '.$request->user()->id.')',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Chấm công thủ công thành công',
            'data' => $attendance->load('user', 'shift'),
        ], 201);
    }

    /**
     * PUT /api/users/{id}/nfc
     * Cập nhật mã thẻ NFC cho nhân viên
     * Sử dụng khi: Cấp thẻ mới, thay thẻ, đăng ký thẻ lần đầu
     *
     * @param  int  $id (User ID)
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateNFC(Request $request, $id)
    {
        // Validate: NFC phải là string và unique
        try {
            $validated = $request->validate([
                'nfc_uid' => 'required|string|max:100|unique:users,nfc_uid,'.$id,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
                'hint' => 'nfc_uid phải là chuỗi (string), không được gửi object',
            ], 422);
        }

        // Tìm user
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy nhân viên với ID: '.$id,
            ], 404);
        }

        $oldNFC = $user->nfc_uid;

        $user->update([
            'nfc_uid' => $validated['nfc_uid'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng ký thẻ NFC thành công',
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'old_nfc_uid' => $oldNFC ?? 'Chưa có',
                'new_nfc_uid' => $user->nfc_uid,
                'updated_at' => $user->updated_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * GET /api/kiosk/status
     * Kiểm tra trạng thái hệ thống Kiosk
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status()
    {
        $totalUsers = User::count();
        $todayAttendances = Attendance::whereDate('check_in_time', today())->count();

        return response()->json([
            'status' => 'online',
            'message' => 'Kiosk System đang hoạt động bình thường',
            'system_info' => [
                'total_users' => $totalUsers,
                'today_attendances' => $todayAttendances,
                'server_time' => now()->format('Y-m-d H:i:s'),
                'timezone' => config('app.timezone'),
            ],
        ]);
    }
}
