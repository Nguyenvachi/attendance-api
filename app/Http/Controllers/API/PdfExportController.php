<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PdfExportController extends Controller
{
    /**
     * GET /api/reports/weekly/pdf
     * Params: week, year, user_id? (chỉ admin/manager)
     */
    public function attendanceWeekly(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);
        $week = (int) ($request->week ?? now()->isoWeek);

        if ($week < 1 || $week > 53 || $year < 2000 || $year > 2100) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tuần hoặc năm không hợp lệ',
            ], 400);
        }

        $start = Carbon::now()->setISODate($year, $week)->startOfWeek(Carbon::MONDAY);
        $end = (clone $start)->endOfWeek(Carbon::SUNDAY);

        $meta = [
            'title' => "Báo cáo thống kê tuần {$week}/{$year}",
            'period' => 'weekly',
            'year' => $year,
            'week' => $week,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];

        return $this->renderAttendanceStatisticsPdf($request, $meta, $start, $end);
    }

    /**
     * GET /api/reports/monthly/pdf
     * Params: month, year, user_id? (chỉ admin/manager)
     */
    public function attendanceMonthly(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);
        $month = (int) ($request->month ?? now()->month);

        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tháng hoặc năm không hợp lệ',
            ], 400);
        }

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = (clone $start)->endOfMonth()->endOfDay();

        $meta = [
            'title' => "Báo cáo thống kê tháng {$month}/{$year}",
            'period' => 'monthly',
            'year' => $year,
            'month' => $month,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];

        return $this->renderAttendanceStatisticsPdf($request, $meta, $start, $end);
    }

    /**
     * GET /api/reports/quarterly/pdf
     * Params: quarter, year, user_id? (chỉ admin/manager)
     */
    public function attendanceQuarterly(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);
        $quarter = (int) ($request->quarter ?? ceil(now()->month / 3));

        if ($quarter < 1 || $quarter > 4 || $year < 2000 || $year > 2100) {
            return response()->json([
                'status' => 'error',
                'message' => 'Quý hoặc năm không hợp lệ',
            ], 400);
        }

        $startMonth = ($quarter - 1) * 3 + 1;
        $start = Carbon::create($year, $startMonth, 1)->startOfDay();
        $end = (clone $start)->addMonths(2)->endOfMonth()->endOfDay();

        $meta = [
            'title' => "Báo cáo thống kê quý {$quarter}/{$year}",
            'period' => 'quarterly',
            'year' => $year,
            'quarter' => $quarter,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];

        return $this->renderAttendanceStatisticsPdf($request, $meta, $start, $end);
    }

    /**
     * GET /api/reports/yearly/pdf
     * Params: year, user_id? (chỉ admin/manager)
     */
    public function attendanceYearly(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);

        if ($year < 2000 || $year > 2100) {
            return response()->json([
                'status' => 'error',
                'message' => 'Năm không hợp lệ',
            ], 400);
        }

        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();

        $meta = [
            'title' => "Báo cáo thống kê năm {$year}",
            'period' => 'yearly',
            'year' => $year,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];

        return $this->renderAttendanceStatisticsPdf($request, $meta, $start, $end);
    }

    /**
     * GET /api/payroll/weekly/pdf
     * Params: week, year
     */
    public function payrollWeekly(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);
        $week = (int) ($request->week ?? now()->isoWeek);

        if ($week < 1 || $week > 53 || $year < 2000 || $year > 2100) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tuần hoặc năm không hợp lệ',
            ], 400);
        }

        $start = Carbon::now()->setISODate($year, $week)->startOfWeek(Carbon::MONDAY);
        $end = (clone $start)->endOfWeek(Carbon::SUNDAY);

        $meta = [
            'title' => "Báo cáo lương tuần {$week}/{$year}",
            'period' => 'weekly',
            'year' => $year,
            'week' => $week,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];

        return $this->renderPayrollPdf($request, $meta, $start, $end);
    }

    /**
     * GET /api/payroll/report/pdf
     * Params: month, year
     */
    public function payrollMonthly(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);
        $month = (int) ($request->month ?? now()->month);

        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tháng hoặc năm không hợp lệ',
            ], 400);
        }

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = (clone $start)->endOfMonth()->endOfDay();

        $meta = [
            'title' => "Báo cáo lương tháng {$month}/{$year}",
            'period' => 'monthly',
            'year' => $year,
            'month' => $month,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];

        return $this->renderPayrollPdf($request, $meta, $start, $end);
    }

    /**
     * GET /api/payroll/quarterly/pdf
     * Params: quarter, year
     */
    public function payrollQuarterly(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);
        $quarter = (int) ($request->quarter ?? ceil(now()->month / 3));

        if ($quarter < 1 || $quarter > 4 || $year < 2000 || $year > 2100) {
            return response()->json([
                'status' => 'error',
                'message' => 'Quý hoặc năm không hợp lệ',
            ], 400);
        }

        $startMonth = ($quarter - 1) * 3 + 1;
        $start = Carbon::create($year, $startMonth, 1)->startOfDay();
        $end = (clone $start)->addMonths(2)->endOfMonth()->endOfDay();

        $meta = [
            'title' => "Báo cáo lương quý {$quarter}/{$year}",
            'period' => 'quarterly',
            'year' => $year,
            'quarter' => $quarter,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];

        return $this->renderPayrollPdf($request, $meta, $start, $end);
    }

    /**
     * GET /api/payroll/yearly/pdf
     * Params: year
     */
    public function payrollYearly(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);

        if ($year < 2000 || $year > 2100) {
            return response()->json([
                'status' => 'error',
                'message' => 'Năm không hợp lệ',
            ], 400);
        }

        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();

        $meta = [
            'title' => "Báo cáo lương năm {$year}",
            'period' => 'yearly',
            'year' => $year,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];

        return $this->renderPayrollPdf($request, $meta, $start, $end);
    }

    private function renderAttendanceStatisticsPdf(Request $request, array $meta, Carbon $start, Carbon $end)
    {
        $authUser = $request->user();
        $targetUserId = (int) ($authUser?->id ?? 0);

        $requestedUserId = $request->integer('user_id');
        if ($requestedUserId && $authUser && in_array($authUser->role, ['admin', 'manager'], true)) {
            $targetUserId = $requestedUserId;
        }

        $user = User::find($targetUserId);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy nhân viên',
            ], 404);
        }

        $attendances = Attendance::query()
            ->where('user_id', $user->id)
            ->whereBetween('check_in_time', [$start, $end])
            ->with('shift')
            ->orderBy('check_in_time')
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

        $viewData = [
            'meta' => $meta,
            'user' => $user,
            'data' => [
                'total_work_days' => $totalDays,
                'late_days' => $lateDays,
                'details' => $details,
            ],
        ];

        $pdf = Pdf::loadView('pdf.attendance_statistics', $viewData)
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans');

        $filename = $this->safeFilename('attendance_statistics_'.($meta['period'] ?? 'period').'_'.now()->format('Ymd_His').'.pdf');

        return $this->pdfResponse($request, $pdf, $filename);
    }

    private function renderPayrollPdf(Request $request, array $meta, Carbon $start, Carbon $end)
    {
        $users = User::query()->where('role', 'staff')->orderBy('id')->get();

        $rows = [];
        $totalSalaryAll = 0.0;

        foreach ($users as $user) {
            $attendances = Attendance::query()
                ->where('user_id', $user->id)
                ->whereBetween('check_in_time', [$start, $end])
                ->whereNotNull('work_hours')
                ->orderBy('check_in_time')
                ->get();

            $totalWorkHours = (float) $attendances->sum('work_hours');
            $totalSalary = $totalWorkHours * (float) $user->hourly_rate;
            $totalSalaryAll += $totalSalary;

            $totalDaysWorked = (int) $attendances
                ->map(fn ($a) => Carbon::parse($a->check_in_time)->toDateString())
                ->unique()
                ->count();

            $rows[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'hourly_rate' => (float) $user->hourly_rate,
                'total_days_worked' => $totalDaysWorked,
                'total_work_hours' => round($totalWorkHours, 2),
                'total_salary' => round($totalSalary, 0),
            ];
        }

        $viewData = [
            'meta' => $meta,
            'data' => [
                'total_employees' => count($rows),
                'total_salary_all' => round($totalSalaryAll, 0),
                'rows' => $rows,
            ],
        ];

        $pdf = Pdf::loadView('pdf.payroll_report', $viewData)
            ->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'DejaVu Sans');

        $filename = $this->safeFilename('payroll_'.($meta['period'] ?? 'period').'_'.now()->format('Ymd_His').'.pdf');

        return $this->pdfResponse($request, $pdf, $filename);
    }

    private function pdfResponse(Request $request, $pdf, string $filename)
    {
        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    private function safeFilename(string $filename): string
    {
        return preg_replace('/[^A-Za-z0-9_\-.]+/', '_', $filename) ?? 'report.pdf';
    }
}
