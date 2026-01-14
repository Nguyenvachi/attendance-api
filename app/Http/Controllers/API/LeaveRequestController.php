<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    /**
     * GET /api/leaves - Lấy danh sách đơn
     * Staff: Chỉ xem của mình
     * Manager/Admin: Xem tất cả
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'staff') {
            $leaves = LeaveRequest::where('user_id', $user->id)
                ->with('approver')
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $leaves = LeaveRequest::with(['user', 'approver'])
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return response()->json([
            'status' => 'success',
            'data' => $leaves,
        ]);
    }

    /**
     * POST /api/leaves - Tạo đơn mới (Staff)
     */
    public function store(Request $request)
    {
        $leave = LeaveRequest::create([
            'user_id' => $request->user()->id,
            'type' => $request->type,
            'reason' => $request->reason,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date ?? $request->start_date,
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Gửi đơn thành công',
            'data' => $leave,
        ], 201);
    }

    /**
     * PUT /api/leaves/{id}/status - Duyệt/Từ chối (Manager/Admin)
     */
    public function updateStatus(Request $request, $id)
    {
        $leave = LeaveRequest::find($id);

        if (! $leave) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy đơn',
            ], 404);
        }

        $leave->update([
            'status' => $request->status, // 'approved' hoặc 'rejected'
            'approved_by' => $request->user()->id,
            'admin_note' => $request->admin_note,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật trạng thái thành công',
            'data' => $leave->load('approver'),
        ]);
    }
}
