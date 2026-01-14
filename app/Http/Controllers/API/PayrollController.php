<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\MonthlyReportMail;
use App\Mail\PeriodReportMail;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * PayrollController - Controller xử lý báo cáo lương và gửi email
 * File con của: app/Http/Controllers/Controller.php
 * Phục vụ: Tính lương, báo cáo, gửi email tự động
 */
class PayrollController extends Controller
{
    /**
     * GET /api/payroll/weekly
     * Báo cáo lương theo tuần cho tất cả nhân viên (ISO week)
     * Params: week, year
     */
    public function weekly(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);
        $week = (int) ($request->week ?? now()->isoWeek);

        if ($week < 1 || $week > 53 || $year < 2000 || $year > 2100) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tuần hoặc năm không hợp lệ',
            ], 400);
        }

        $startOfWeek = Carbon::now()->setISODate($year, $week)->startOfWeek(Carbon::MONDAY);
        $endOfWeek = (clone $startOfWeek)->endOfWeek(Carbon::SUNDAY);

        // SPRINT 2: Scope users theo department (Manager chỉ thấy team mình)
        $authUser = $request->user();
        $users = User::where('role', 'staff')
            ->accessibleBy($authUser)
            ->get();
        $reportData = [];

        foreach ($users as $user) {
            $attendances = Attendance::where('user_id', $user->id)
                ->whereBetween('check_in_time', [$startOfWeek, $endOfWeek])
                ->whereNotNull('work_hours')
                ->get();

            $totalWorkHours = $attendances->sum('work_hours');
            $totalSalary = $totalWorkHours * $user->hourly_rate;

            $details = $attendances->map(function ($att) {
                return [
                    'date' => Carbon::parse($att->check_in_time)->format('Y-m-d'),
                    'day_of_week' => Carbon::parse($att->check_in_time)->locale('vi')->dayName,
                    'check_in' => Carbon::parse($att->check_in_time)->format('H:i'),
                    'check_out' => $att->check_out_time ? Carbon::parse($att->check_out_time)->format('H:i') : 'Chưa checkout',
                    'work_hours' => $att->work_hours,
                ];
            });

            $reportData[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'hourly_rate' => $user->hourly_rate,
                'total_work_hours' => round($totalWorkHours, 2),
                'total_salary' => round($totalSalary, 0),
                'total_days_worked' => $attendances->count(),
                'details' => $details,
            ];
        }

        return response()->json([
            'status' => 'success',
            'period' => 'weekly',
            'week' => $week,
            'year' => $year,
            'start_date' => $startOfWeek->toDateString(),
            'end_date' => $endOfWeek->toDateString(),
            'total_employees' => count($reportData),
            'total_salary_all' => round(collect($reportData)->sum('total_salary'), 0),
            'data' => $reportData,
        ]);
    }

    /**
     * GET /api/payroll/report
     * Báo cáo lương theo tháng cho tất cả nhân viên
     * Tính toán: Tổng giờ làm x Lương/giờ = Tổng lương
     *
     * @param  Request  $request (month, year)
     * @return \Illuminate\Http\JsonResponse
     */
    public function report(Request $request)
    {
        // Lấy tháng/năm từ request hoặc dùng tháng hiện tại
        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;

        // Validate tháng và năm
        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tháng hoặc năm không hợp lệ',
            ], 400);
        }

        // Lấy danh sách nhân viên (có thể lọc theo role nếu cần)
        // SPRINT 2: Scope users theo department (Manager chỉ thấy team mình)
        $authUser = $request->user();
        $users = User::where('role', 'staff')
            ->accessibleBy($authUser)
            ->get();
        $reportData = [];

        foreach ($users as $user) {
            // Lấy tất cả bản ghi chấm công trong tháng (chỉ những bản ghi đã check-out)
            $attendances = Attendance::where('user_id', $user->id)
                ->whereYear('check_in_time', $year)
                ->whereMonth('check_in_time', $month)
                ->whereNotNull('work_hours')
                ->get();

            // Tính tổng giờ làm việc
            $totalWorkHours = $attendances->sum('work_hours');

            // Tính tổng lương = Tổng giờ x Lương/giờ
            $totalSalary = $totalWorkHours * $user->hourly_rate;

            // Chi tiết từng ngày
            $details = $attendances->map(function ($att) {
                return [
                    'date' => Carbon::parse($att->check_in_time)->format('Y-m-d'),
                    'day_of_week' => Carbon::parse($att->check_in_time)->locale('vi')->dayName,
                    'check_in' => Carbon::parse($att->check_in_time)->format('H:i'),
                    'check_out' => $att->check_out_time ? Carbon::parse($att->check_out_time)->format('H:i') : 'Chưa checkout',
                    'work_hours' => $att->work_hours,
                ];
            });

            $reportData[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'hourly_rate' => $user->hourly_rate,
                'total_work_hours' => round($totalWorkHours, 2),
                'total_salary' => round($totalSalary, 0),
                'total_days_worked' => $attendances->count(),
                'details' => $details,
            ];
        }

        return response()->json([
            'status' => 'success',
            'month' => $month,
            'year' => $year,
            'month_name' => Carbon::create($year, $month)->locale('vi')->monthName,
            'total_employees' => count($reportData),
            'total_salary_all' => round(collect($reportData)->sum('total_salary'), 0),
            'data' => $reportData,
        ]);
    }

    /**
     * POST /api/payroll/send-email
     * Gửi email báo cáo lương cho từng nhân viên
     * Email chứa: Bảng chi tiết chấm công + Tổng giờ + Lương
     *
     * @param  Request  $request (month, year)
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendEmail(Request $request)
    {
        // Lấy tháng/năm từ request
        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;

        // Validate
        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tháng hoặc năm không hợp lệ',
            ], 400);
        }

        // Lấy dữ liệu báo cáo từ method report()
        $reportRequest = new Request(['month' => $month, 'year' => $year]);
        $reportResponse = $this->report($reportRequest);
        $reportData = json_decode($reportResponse->getContent(), true)['data'];

        // Gửi email cho từng nhân viên
        $sentCount = 0;
        $failedEmails = [];

        foreach ($reportData as $employeeData) {
            try {
                // Chỉ gửi nếu có email và có giờ làm
                if ($employeeData['email'] && $employeeData['total_work_hours'] > 0) {
                    Mail::to($employeeData['email'])->send(
                        new MonthlyReportMail($employeeData, $month, $year)
                    );
                    $sentCount++;
                }
            } catch (\Exception $e) {
                // Log lỗi để debug
                Log::error('Failed to send email to '.$employeeData['email'].': '.$e->getMessage());
                $failedEmails[] = [
                    'email' => $employeeData['email'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Đã gửi {$sentCount} email thành công",
            'data' => [
                'month' => $month,
                'year' => $year,
                'total_employees' => count($reportData),
                'emails_sent' => $sentCount,
                'emails_failed' => count($failedEmails),
                'failed_list' => $failedEmails,
            ],
        ]);
    }

    /**
     * POST /api/payroll/send-period-email
     * Gửi email báo cáo theo kỳ: weekly | quarterly | yearly
     * Body: { period, week?, quarter?, year }
     */
    public function sendPeriodEmail(Request $request)
    {
        $validated = $request->validate([
            'period' => 'required|string|in:weekly,quarterly,yearly',
            'week' => 'nullable|integer|min:1|max:53',
            'quarter' => 'nullable|integer|min:1|max:4',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $period = $validated['period'];
        $year = (int) ($validated['year'] ?? now()->year);

        // Lấy dữ liệu report theo kỳ
        $reportData = [];
        $meta = [
            'period' => $period,
            'title' => 'Báo cáo theo kỳ',
            'subtitle' => 'Hệ thống chấm công NCT Attendance',
            'range' => '',
        ];

        if ($period === 'weekly') {
            $week = (int) ($validated['week'] ?? now()->isoWeek);
            $weeklyReq = new Request(['week' => $week, 'year' => $year]);
            $weeklyRes = $this->weekly($weeklyReq);
            $decoded = json_decode($weeklyRes->getContent(), true);
            $reportData = $decoded['data'] ?? [];
            $meta['title'] = "Báo cáo lương tuần {$week}/{$year}";
            $meta['range'] = ($decoded['start_date'] ?? '').' → '.($decoded['end_date'] ?? '');
        }

        if ($period === 'quarterly') {
            $quarter = (int) ($validated['quarter'] ?? ceil(now()->month / 3));
            $startMonth = ($quarter - 1) * 3 + 1;
            $start = Carbon::create($year, $startMonth, 1)->startOfDay();
            $end = (clone $start)->addMonths(2)->endOfMonth()->endOfDay();

            // SPRINT 2: Scope users theo department
            $authUser = $request->user();
            $users = User::where('role', 'staff')
                ->accessibleBy($authUser)
                ->get();
            foreach ($users as $user) {
                $attendances = Attendance::where('user_id', $user->id)
                    ->whereBetween('check_in_time', [$start, $end])
                    ->whereNotNull('work_hours')
                    ->get();

                $totalWorkHours = $attendances->sum('work_hours');
                $totalSalary = $totalWorkHours * $user->hourly_rate;
                $details = $attendances->map(function ($att) {
                    return [
                        'date' => Carbon::parse($att->check_in_time)->format('Y-m-d'),
                        'day_of_week' => Carbon::parse($att->check_in_time)->locale('vi')->dayName,
                        'check_in' => Carbon::parse($att->check_in_time)->format('H:i'),
                        'check_out' => $att->check_out_time ? Carbon::parse($att->check_out_time)->format('H:i') : 'Chưa checkout',
                        'work_hours' => $att->work_hours,
                    ];
                });

                $reportData[] = [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'hourly_rate' => $user->hourly_rate,
                    'total_work_hours' => round($totalWorkHours, 2),
                    'total_salary' => round($totalSalary, 0),
                    'total_days_worked' => $attendances->count(),
                    'details' => $details,
                ];
            }

            $meta['title'] = "Báo cáo lương quý {$quarter}/{$year}";
            $meta['range'] = $start->toDateString().' → '.$end->toDateString();
        }

        if ($period === 'yearly') {
            $start = Carbon::create($year, 1, 1)->startOfDay();
            $end = Carbon::create($year, 12, 31)->endOfDay();

            // SPRINT 2: Scope users theo department
            $authUser = $request->user();
            $users = User::where('role', 'staff')
                ->accessibleBy($authUser)
                ->get();
            foreach ($users as $user) {
                $attendances = Attendance::where('user_id', $user->id)
                    ->whereBetween('check_in_time', [$start, $end])
                    ->whereNotNull('work_hours')
                    ->get();

                $totalWorkHours = $attendances->sum('work_hours');
                $totalSalary = $totalWorkHours * $user->hourly_rate;
                $details = $attendances->map(function ($att) {
                    return [
                        'date' => Carbon::parse($att->check_in_time)->format('Y-m-d'),
                        'day_of_week' => Carbon::parse($att->check_in_time)->locale('vi')->dayName,
                        'check_in' => Carbon::parse($att->check_in_time)->format('H:i'),
                        'check_out' => $att->check_out_time ? Carbon::parse($att->check_out_time)->format('H:i') : 'Chưa checkout',
                        'work_hours' => $att->work_hours,
                    ];
                });

                $reportData[] = [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'hourly_rate' => $user->hourly_rate,
                    'total_work_hours' => round($totalWorkHours, 2),
                    'total_salary' => round($totalSalary, 0),
                    'total_days_worked' => $attendances->count(),
                    'details' => $details,
                ];
            }

            $meta['title'] = "Báo cáo lương năm {$year}";
            $meta['range'] = $start->toDateString().' → '.$end->toDateString();
        }

        // Gửi email
        $sentCount = 0;
        $failedEmails = [];

        foreach ($reportData as $employeeData) {
            try {
                if (($employeeData['email'] ?? null) && ($employeeData['total_work_hours'] ?? 0) > 0) {
                    Mail::to($employeeData['email'])->send(new PeriodReportMail($employeeData, $meta));
                    $sentCount++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to send period email to '.($employeeData['email'] ?? '').': '.$e->getMessage());
                $failedEmails[] = [
                    'email' => $employeeData['email'] ?? null,
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
                'meta' => $meta,
            ],
        ]);
    }

    /**
     * GET /api/payroll/employee/{id}
     * Xem báo cáo lương của 1 nhân viên cụ thể
     *
     * @param  int  $id (User ID)
     * @return \Illuminate\Http\JsonResponse
     */
    public function employeeReport(Request $request, $id)
    {
        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;

        // Tìm user
        $user = User::findOrFail($id);

        // Lấy attendances
        $attendances = Attendance::where('user_id', $user->id)
            ->whereYear('check_in_time', $year)
            ->whereMonth('check_in_time', $month)
            ->whereNotNull('work_hours')
            ->get();

        $totalWorkHours = $attendances->sum('work_hours');
        $totalSalary = $totalWorkHours * $user->hourly_rate;

        return response()->json([
            'status' => 'success',
            'data' => [
                'user_name' => $user->name,
                'email' => $user->email,
                'month' => $month,
                'year' => $year,
                'hourly_rate' => $user->hourly_rate,
                'total_work_hours' => round($totalWorkHours, 2),
                'total_salary' => round($totalSalary, 0),
                'attendances' => $attendances->map(function ($att) {
                    return [
                        'date' => $att->check_in_time->format('Y-m-d'),
                        'check_in' => $att->check_in_time->format('H:i'),
                        'check_out' => $att->check_out_time ? $att->check_out_time->format('H:i') : null,
                        'work_hours' => $att->work_hours,
                    ];
                }),
            ],
        ]);
    }

    /**
     * GET /api/payroll/statistics
     * Thống kê tổng quan về lương (Admin dashboard)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics(Request $request)
    {
        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;

        // Lấy tất cả attendances trong tháng
        $attendances = Attendance::whereYear('check_in_time', $year)
            ->whereMonth('check_in_time', $month)
            ->whereNotNull('work_hours')
            ->get();

        $totalWorkHours = $attendances->sum('work_hours');

        // Tính tổng lương cho tất cả nhân viên
        $totalSalary = 0;
        $userIds = $attendances->pluck('user_id')->unique();

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            $userHours = $attendances->where('user_id', $userId)->sum('work_hours');
            $totalSalary += $userHours * $user->hourly_rate;
        }

        return response()->json([
            'status' => 'success',
            'month' => $month,
            'year' => $year,
            'statistics' => [
                'total_employees_worked' => $userIds->count(),
                'total_attendances' => $attendances->count(),
                'total_work_hours' => round($totalWorkHours, 2),
                'total_salary' => round($totalSalary, 0),
                'average_hours_per_employee' => $userIds->count() > 0 ? round($totalWorkHours / $userIds->count(), 2) : 0,
                'average_salary_per_employee' => $userIds->count() > 0 ? round($totalSalary / $userIds->count(), 0) : 0,
            ],
        ]);
    }
}
