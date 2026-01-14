<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\KioskQrSession;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class QrAttendanceService
{
    public function __construct(
        private QrKioskSessionService $sessions,
) {
    }

    public function handle(User $user, string $qrCode, array $payload = []): array
    {
        $session = $this->sessions->findActiveSessionByCode($qrCode);
        if (! $session) {
            return [
                'http_code' => 404,
                'body' => [
                    'status' => 'error',
                    'message' => 'QR hết hạn hoặc không hợp lệ',
                    'code' => 'QR_INVALID',
                ],
            ];
        }

        if ($session->isExpired()) {
            return [
                'http_code' => 410,
                'body' => [
                    'status' => 'error',
                    'message' => 'QR đã hết hạn. Vui lòng quét lại QR mới trên kiosk.',
                    'code' => 'QR_EXPIRED',
                ],
            ];
        }

        // Resolve shift (recommended): generic kiosk QR -> detect by current time.
        $shiftId = Shift::detectShiftByTime();
        $shift = Shift::find($shiftId);

        if (! $shift) {
            return [
                'http_code' => 500,
                'body' => [
                    'status' => 'error',
                    'message' => 'Không tìm thấy ca làm việc phù hợp.',
                    'code' => 'SHIFT_NOT_FOUND',
                ],
            ];
        }

        $lat = $payload['latitude'] ?? null;
        $lng = $payload['longitude'] ?? null;

        // GPS validation only if shift is configured with location.
        if ($shift->latitude && $shift->longitude) {
            if ($lat === null || $lng === null) {
                return [
                    'http_code' => 400,
                    'body' => [
                        'status' => 'error',
                        'message' => 'Vui lòng bật GPS để điểm danh',
                        'code' => 'GPS_REQUIRED',
                        'data' => [
                            'shift' => [
                                'id' => $shift->id,
                                'name' => $shift->name,
                                'radius' => $shift->radius,
                            ],
                        ],
                    ],
                ];
            }

            $distance = $shift->calculateDistance($lat, $lng);
            if ($distance > $shift->radius) {
                return [
                    'http_code' => 403,
                    'body' => [
                        'status' => 'error',
                        'message' => 'Bạn đang ở quá xa địa điểm làm việc',
                        'code' => 'TOO_FAR',
                        'data' => [
                            'distance' => round($distance, 2),
                            'max_distance' => $shift->radius,
                        ],
                    ],
                ];
            }
        }

        $today = Carbon::today();

        $result = DB::transaction(function () use ($user, $today, $shift, $payload, $session) {
            $latestAttendance = Attendance::where('user_id', $user->id)
                ->whereDate('check_in_time', $today)
                ->lockForUpdate()
                ->latest('check_in_time')
                ->first();

            $now = now();

            // Auto check-out if there is an open attendance.
            if ($latestAttendance && $latestAttendance->check_out_time === null) {
                $workHours = $now->diffInMinutes($latestAttendance->check_in_time, true) / 60;

                $latestAttendance->update([
                    'check_out_time' => $now,
                    'work_hours' => round($workHours, 2),
                ]);

                $earnedSalary = $latestAttendance->calculateSalary();

                $session->update(['last_used_at' => $now]);

                return [
                    'http_code' => 200,
                    'body' => [
                        'status' => 'success',
                        'type' => 'check_out',
                        'message' => 'Check-out thành công',
                        'data' => $this->formatResponseData(
                            attendance: $latestAttendance->fresh()->load('shift'),
                            shift: $latestAttendance->shift,
                            kioskId: $session->kiosk_id,
                            earnedSalary: $earnedSalary,
                        ),
                    ],
                ];
            }

            $attendance = Attendance::create([
                'user_id' => $user->id,
                'shift_id' => $shift->id,
                'check_in_time' => $now,
                'device_info' => (string) ($payload['device_info'] ?? 'QR Kiosk'),
                'timezone' => (string) ($payload['timezone'] ?? 'Asia/Ho_Chi_Minh'),
            ]);

            $session->update(['last_used_at' => $now]);

            return [
                'http_code' => 201,
                'body' => [
                    'status' => 'success',
                    'type' => 'check_in',
                    'message' => 'Điểm danh thành công',
                    'data' => $this->formatResponseData(
                        attendance: $attendance->load('shift'),
                        shift: $shift,
                        kioskId: $session->kiosk_id,
                        earnedSalary: null,
                    ),
                ],
            ];
        });

        return $result;
    }

    private function formatResponseData(
        Attendance $attendance,
        Shift $shift,
        string $kioskId,
        ?float $earnedSalary,
    ): array {
        return [
            'kiosk' => [
                'kiosk_id' => $kioskId,
            ],
            'shift' => [
                'id' => $shift->id,
                'name' => $shift->name,
                'start_time' => (string) $shift->start_time,
                'end_time' => (string) $shift->end_time,
                'requires_gps' => (bool) ($shift->latitude && $shift->longitude),
                'radius' => $shift->radius,
            ],
            'attendance' => [
                'id' => $attendance->id,
                'user_id' => $attendance->user_id,
                'shift_id' => $attendance->shift_id,
                'check_in_time' => optional($attendance->check_in_time)->toIso8601String(),
                'check_out_time' => optional($attendance->check_out_time)->toIso8601String(),
                'work_hours' => $attendance->work_hours,
                'regular_hours' => $attendance->regular_hours,
                'overtime_hours' => $attendance->overtime_hours,
                'overtime_double_hours' => $attendance->overtime_double_hours,
                'break_hours' => $attendance->break_hours,
                'earned_salary' => $earnedSalary,
            ],
        ];
    }
}
