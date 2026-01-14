<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * AuditLogController
 * BỔ SUNG: API để Admin xem lịch sử thay đổi dữ liệu
 */
class AuditLogController extends Controller
{
    /**
     * GET /api/audit-logs
     * Lấy danh sách audit logs với filters
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'model' => 'nullable|string|max:100',
            'action' => 'nullable|string|in:post,put,patch,delete,login,logout',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = AuditLog::with('user:id,name,email,role')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->user_id) {
            $query->byUser($request->user_id);
        }

        if ($request->model) {
            $query->forModel($request->model);
        }

        if ($request->action) {
            $query->action($request->action);
        }

        if ($request->date_from && $request->date_to) {
            $query->dateRange(
                Carbon::parse($request->date_from)->startOfDay(),
                Carbon::parse($request->date_to)->endOfDay()
            );
        } elseif ($request->date_from) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        } elseif ($request->date_to) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        $perPage = $request->get('per_page', 20);
        $logs = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * GET /api/audit-logs/{id}
     * Xem chi tiết 1 audit log
     */
    public function show($id)
    {
        $log = AuditLog::with('user:id,name,email,role')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $log,
        ]);
    }
}
