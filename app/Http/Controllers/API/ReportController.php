<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\StatisticsReportMail;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReportController extends Controller
{
    /**
     * GET /api/reports/monthly - Báo cáo tháng
     * Params: month, year, user_id (tùy chọn - dành cho admin xem báo cáo nhân viên)
     */
    public function monthly(Request $request)
    {
        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;
        $userId = $request->user_id ?? $request->user()->id;

        // Lấy tất cả bản ghi điểm danh trong tháng
        $attendances = Attendance::where('user_id', $userId)
            ->whereYear('check_in_time', $year)
            ->whereMonth('check_in_time', $month)
            ->with('shift')
            ->get();

        $totalDays = $attendances->count();
        $lateDays = 0;
        $details = [];

        foreach ($attendances as $att) {
            $checkInTime = Carbon::parse($att->check_in_time);
            $shiftStartTime = Carbon::parse($att->shift->start_time);

            $isLate = $checkInTime->greaterThan($shiftStartTime);

            if ($isLate) {
                $lateDays++;
            }

            $details[] = [
                'date' => $checkInTime->format('Y-m-d'),
                'check_in' => $checkInTime->format('H:i'),
                'shift_start' => $shiftStartTime->format('H:i'),
                'is_late' => $isLate,
                'late_minutes' => $isLate ? $checkInTime->diffInMinutes($shiftStartTime) : 0,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_work_days' => $totalDays,
                'late_days' => $lateDays,
                'details' => $details,
            ],
        ]);
    }

    /**
     * GET /api/reports/weekly - Báo cáo tuần
     * Params: week, year, user_id (tùy chọn)
     * Quy ước: week theo ISO-8601 (1-53)
     */
    public function weekly(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);
        $week = (int) ($request->week ?? now()->isoWeek);
        $userId = $request->user_id ?? $request->user()->id;

        if ($week < 1 || $week > 53 || $year < 2000 || $year > 2100) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tuần hoặc năm không hợp lệ',
            ], 400);
        }

        $startOfWeek = Carbon::now()->setISODate($year, $week)->startOfWeek(Carbon::MONDAY);
        $endOfWeek = (clone $startOfWeek)->endOfWeek(Carbon::SUNDAY);

        $attendances = Attendance::where('user_id', $userId)
            ->whereBetween('check_in_time', [$startOfWeek, $endOfWeek])
            ->with('shift')
            ->get();

        return $this->buildReportResponse($attendances, [
            'period' => 'weekly',
            'year' => $year,
            'week' => $week,
            'start_date' => $startOfWeek->toDateString(),
            'end_date' => $endOfWeek->toDateString(),
        ]);
    }

    /**
     * GET /api/reports/quarterly - Báo cáo quý
     * Params: quarter(1-4), year, user_id (tùy chọn)
     */
    public function quarterly(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);
        $quarter = (int) ($request->quarter ?? ceil(now()->month / 3));
        $userId = $request->user_id ?? $request->user()->id;

        if ($quarter < 1 || $quarter > 4 || $year < 2000 || $year > 2100) {
            return response()->json([
                'status' => 'error',
                'message' => 'Quý hoặc năm không hợp lệ',
            ], 400);
        }

        $startMonth = ($quarter - 1) * 3 + 1;
        $start = Carbon::create($year, $startMonth, 1)->startOfDay();
        $end = (clone $start)->addMonths(2)->endOfMonth()->endOfDay();

        $attendances = Attendance::where('user_id', $userId)
            ->whereBetween('check_in_time', [$start, $end])
            ->with('shift')
            ->get();

        return $this->buildReportResponse($attendances, [
            'period' => 'quarterly',
            'year' => $year,
            'quarter' => $quarter,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]);
    }

    /**
     * GET /api/reports/yearly - Báo cáo năm
     * Params: year, user_id (tùy chọn)
     */
    public function yearly(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);
        $userId = $request->user_id ?? $request->user()->id;

        if ($year < 2000 || $year > 2100) {
            return response()->json([
                'status' => 'error',
                'message' => 'Năm không hợp lệ',
            ], 400);
        }

        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();

        $attendances = Attendance::where('user_id', $userId)
            ->whereBetween('check_in_time', [$start, $end])
            ->with('shift')
            ->get();

        return $this->buildReportResponse($attendances, [
            'period' => 'yearly',
            'year' => $year,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]);
    }

    /**
     * BỔ SUNG: Hàm dùng chung để build response report (monthly/weekly/quarterly/yearly).
     * Chỉ thêm để tái sử dụng, không thay đổi hành vi endpoint cũ.
     */
    private function buildReportResponse($attendances, array $meta)
    {
        $totalDays = $attendances->count();
        $lateDays = 0;
        $details = [];

        foreach ($attendances as $att) {
            $checkInTime = Carbon::parse($att->check_in_time);
            $shiftStartTime = $att->shift ? Carbon::parse($att->shift->start_time) : null;
            $isLate = $shiftStartTime ? $checkInTime->greaterThan($shiftStartTime) : false;

            if ($isLate) {
                $lateDays++;
            }

            $details[] = [
                'date' => $checkInTime->format('Y-m-d'),
                'check_in' => $checkInTime->format('H:i'),
                'shift_start' => $shiftStartTime ? $shiftStartTime->format('H:i') : null,
                'is_late' => $isLate,
                'late_minutes' => ($isLate && $shiftStartTime) ? $checkInTime->diffInMinutes($shiftStartTime) : 0,
            ];
        }

        return response()->json([
            'status' => 'success',
            'meta' => $meta,
            'data' => [
                'total_work_days' => $totalDays,
                'late_days' => $lateDays,
                'details' => $details,
            ],
        ]);
    }

    /**
     * GET /api/reports/today - Báo cáo hôm nay (Dành cho Manager)
     */
    public function today(Request $request)
    {
        $today = now()->toDateString();

        // SPRINT 2: Scope users theo department (Manager chỉ thấy team mình)
        $authUser = $request->user();
        $staffQuery = User::where('role', 'staff')->accessibleBy($authUser);

        // Tổng số nhân viên
        $totalStaff = (clone $staffQuery)->count();

        // Số nhân viên đã điểm danh hôm nay
        $attendedStaff = Attendance::whereDate('check_in_time', $today)
            ->distinct('user_id')
            ->count('user_id');

        // Danh sách nhân viên chưa đến
        $attendedUserIds = Attendance::whereDate('check_in_time', $today)
            ->pluck('user_id');

        $absentStaff = (clone $staffQuery)
            ->whereNotIn('id', $attendedUserIds)
            ->get(['id', 'name', 'email', 'phone']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_staff' => $totalStaff,
                'attended' => $attendedStaff,
                'absent' => $totalStaff - $attendedStaff,
                'absent_list' => $absentStaff,
            ],
        ]);
    }

    /**
     * POST /api/reports/send-statistics-email
     * Gửi email báo cáo thống kê (đi trễ/số ngày làm) theo kỳ cho nhân viên
     * Body: { period, week?, quarter?, year, user_ids?: [] }
     * Nếu không truyền user_ids: gửi cho tất cả staff
     */
    public function sendStatisticsEmail(Request $request)
    {
        $validated = $request->validate([
            'period' => 'required|string|in:weekly,monthly,quarterly,yearly',
            'week' => 'nullable|integer|min:1|max:53',
            'month' => 'nullable|integer|min:1|max:12',
            'quarter' => 'nullable|integer|min:1|max:4',
            'year' => 'required|integer|min:2000|max:2100',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $period = $validated['period'];
        $year = (int) $validated['year'];

        // Xác định range
        $start = null;
        $end = null;
        $title = '';
        $range = '';

        if ($period === 'weekly') {
            $week = (int) ($validated['week'] ?? now()->isoWeek);
            $start = Carbon::now()->setISODate($year, $week)->startOfWeek(Carbon::MONDAY);
            $end = (clone $start)->endOfWeek(Carbon::SUNDAY);
            $title = "Báo cáo thống kê tuần {$week}/{$year}";
            $range = "Tuần {$week}/{$year} ({$start->toDateString()} - {$end->toDateString()})";
        } elseif ($period === 'monthly') {
            $month = (int) ($validated['month'] ?? now()->month);
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $end = (clone $start)->endOfMonth()->endOfDay();
            $title = "Báo cáo thống kê tháng {$month}/{$year}";
            $range = "Tháng {$month}/{$year}";
        } elseif ($period === 'quarterly') {
            $quarter = (int) ($validated['quarter'] ?? ceil(now()->month / 3));
            $startMonth = ($quarter - 1) * 3 + 1;
            $start = Carbon::create($year, $startMonth, 1)->startOfDay();
            $end = (clone $start)->addMonths(2)->endOfMonth()->endOfDay();
            $title = "Báo cáo thống kê quý {$quarter}/{$year}";
            $range = "Quý {$quarter}/{$year}";
        } elseif ($period === 'yearly') {
            $start = Carbon::create($year, 1, 1)->startOfDay();
            $end = Carbon::create($year, 12, 31)->endOfDay();
            $title = "Báo cáo thống kê năm {$year}";
            $range = "Năm {$year}";
        }

        // Lấy danh sách user
        // SPRINT 2: Scope users theo department
        $authUser = $request->user();
        $userQuery = User::where('role', 'staff')->accessibleBy($authUser);
        if (isset($validated['user_ids'])) {
            $userQuery->whereIn('id', $validated['user_ids']);
        }
        $users = $userQuery->get();

        $sentCount = 0;
        $failedEmails = [];

        foreach ($users as $user) {
            $attendances = Attendance::where('user_id', $user->id)
                ->whereBetween('check_in_time', [$start, $end])
                ->with('shift')
                ->get();

            $totalDays = $attendances->count();
            $lateDays = 0;
            $details = [];

            foreach ($attendances as $att) {
                $checkInTime = Carbon::parse($att->check_in_time);
                $shiftStartTime = $att->shift ? Carbon::parse($att->shift->start_time) : null;
                $isLate = $shiftStartTime ? $checkInTime->greaterThan($shiftStartTime) : false;

                if ($isLate) {
                    $lateDays++;
                }

                $details[] = [
                    'date' => $checkInTime->format('Y-m-d'),
                    'check_in' => $checkInTime->format('H:i'),
                    'shift_start' => $shiftStartTime ? $shiftStartTime->format('H:i') : null,
                    'is_late' => $isLate,
                    'late_minutes' => ($isLate && $shiftStartTime) ? $checkInTime->diffInMinutes($shiftStartTime) : 0,
                ];
            }

            $reportData = [
                'total_work_days' => $totalDays,
                'late_days' => $lateDays,
                'details' => $details,
            ];

            $meta = [
                'period' => $period,
                'title' => $title,
                'subtitle' => 'Hệ thống chấm công NCT Attendance',
                'range' => $range,
                'user_name' => $user->name,
            ];

            try {
                if ($user->email && $totalDays > 0) {
                    Mail::to($user->email)->send(new StatisticsReportMail($reportData, $meta));
                    $sentCount++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to send statistics email to '.$user->email.': '.$e->getMessage());
                $failedEmails[] = [
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Đã gửi {$sentCount} email thành công",
            'data' => [
                'period' => $period,
                'year' => $year,
                'emails_sent' => $sentCount,
                'emails_failed' => count($failedEmails),
                'failed_list' => $failedEmails,
            ],
        ]);
    }
}
