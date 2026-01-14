<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GoogleSheetsExport;
use App\Services\GoogleSheetsReportExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleSheetsExportController extends Controller
{
    /**
     * POST /api/google-sheets/attendance-statistics
     * Body: { period: weekly|monthly|quarterly|yearly, year, week?, month?, quarter?, user_ids?: [] }
     * Quy ước period giống ReportController.
     */
    public function exportAttendanceStatistics(Request $request, GoogleSheetsReportExportService $exporter)
    {
        $validated = $request->validate([
            'period' => 'required|string|in:weekly,monthly,quarterly,yearly',
            'year' => 'required|integer|min:2000|max:2100',
            'week' => 'nullable|integer|min:1|max:53',
            'month' => 'nullable|integer|min:1|max:12',
            'quarter' => 'nullable|integer|min:1|max:4',
            // append: luôn thêm dòng | replace: thay thế dữ liệu trong kỳ
            'mode' => 'nullable|string|in:append,replace',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $result = $exporter->exportAttendanceStatistics($validated);

        $userId = $request->user()?->id;
        GoogleSheetsExport::create([
            'user_id' => $userId,
            'type' => 'attendance-statistics',
            'period' => $result['meta']['period'] ?? ($validated['period'] ?? null),
            'year' => $result['meta']['year'] ?? ($validated['year'] ?? null),
            'week' => $result['meta']['week'] ?? ($validated['week'] ?? null),
            'month' => $result['meta']['month'] ?? ($validated['month'] ?? null),
            'quarter' => $result['meta']['quarter'] ?? ($validated['quarter'] ?? null),
            'start_date' => $result['meta']['start_date'] ?? null,
            'end_date' => $result['meta']['end_date'] ?? null,
            'mode' => $result['mode'] ?? ($validated['mode'] ?? null),
            'sheet' => $result['sheet'] ?? null,
            'deleted_rows' => $result['deleted_rows'] ?? null,
            'written_rows' => $result['written_rows'] ?? null,
            'status' => $result['status'] ?? null,
            'message' => $result['message'] ?? null,
            'request_params' => $validated,
            'google_response' => $result['google'] ?? null,
        ]);

        Log::info('GoogleSheets export attendance-statistics', [
            'user_id' => $userId,
            'mode' => $result['mode'] ?? null,
            'meta' => $result['meta'] ?? null,
            'sheet' => $result['sheet'] ?? null,
            'deleted_rows' => $result['deleted_rows'] ?? null,
            'written_rows' => $result['written_rows'] ?? null,
        ]);

        return response()->json($result);
    }

    /**
     * POST /api/google-sheets/payroll
     * Body: { period: weekly|monthly|quarterly|yearly, year, week?, month?, quarter? }
     * Quy ước period giống PayrollController.
     */
    public function exportPayroll(Request $request, GoogleSheetsReportExportService $exporter)
    {
        $validated = $request->validate([
            'period' => 'required|string|in:weekly,monthly,quarterly,yearly',
            'year' => 'required|integer|min:2000|max:2100',
            'week' => 'nullable|integer|min:1|max:53',
            'month' => 'nullable|integer|min:1|max:12',
            'quarter' => 'nullable|integer|min:1|max:4',
            // append: luôn thêm dòng | replace: thay thế dữ liệu trong kỳ
            'mode' => 'nullable|string|in:append,replace',
        ]);

        $result = $exporter->exportPayroll($validated);

        $userId = $request->user()?->id;
        GoogleSheetsExport::create([
            'user_id' => $userId,
            'type' => 'payroll',
            'period' => $result['meta']['period'] ?? ($validated['period'] ?? null),
            'year' => $result['meta']['year'] ?? ($validated['year'] ?? null),
            'week' => $result['meta']['week'] ?? ($validated['week'] ?? null),
            'month' => $result['meta']['month'] ?? ($validated['month'] ?? null),
            'quarter' => $result['meta']['quarter'] ?? ($validated['quarter'] ?? null),
            'start_date' => $result['meta']['start_date'] ?? null,
            'end_date' => $result['meta']['end_date'] ?? null,
            'mode' => $result['mode'] ?? ($validated['mode'] ?? null),
            'sheet' => $result['sheet'] ?? null,
            'deleted_rows' => $result['deleted_rows'] ?? null,
            'written_rows' => $result['written_rows'] ?? null,
            'status' => $result['status'] ?? null,
            'message' => $result['message'] ?? null,
            'request_params' => $validated,
            'google_response' => $result['google'] ?? null,
        ]);

        Log::info('GoogleSheets export payroll', [
            'user_id' => $userId,
            'mode' => $result['mode'] ?? null,
            'meta' => $result['meta'] ?? null,
            'sheet' => $result['sheet'] ?? null,
            'deleted_rows' => $result['deleted_rows'] ?? null,
            'written_rows' => $result['written_rows'] ?? null,
        ]);

        return response()->json($result);
    }
}
