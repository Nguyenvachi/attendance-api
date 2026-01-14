<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     * GET /api/departments
     *
     * Admin: Thấy tất cả departments
     * Manager: Chỉ thấy department của mình và sub-departments
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Department::with(['parent', 'manager', 'children']);

        // Nếu là Manager: Chỉ thấy department của mình và sub-departments
        if ($user->role === 'manager' && $user->department_id) {
            $managerDepartment = Department::find($user->department_id);
            if ($managerDepartment) {
                $allowedDepartmentIds = $managerDepartment->getAllDepartmentIds();
                $query->whereIn('id', $allowedDepartmentIds);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bạn chưa được gán vào department nào',
                ], 403);
            }
        }

        $departments = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $departments,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     * POST /api/departments
     *
     * Chỉ Admin mới được tạo department
     */
    public function store(Request $request)
    {
        // Middleware role:admin đã check rồi, nhưng double-check để chắc chắn
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Chỉ Admin mới được tạo department',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:departments,name',
            'parent_id' => 'nullable|exists:departments,id',
            'manager_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Kiểm tra logic: parent_id không được là chính nó
        if ($request->parent_id && $request->parent_id == $request->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Department không thể là parent của chính nó',
            ], 422);
        }

        $department = Department::create($request->only([
            'name',
            'parent_id',
            'manager_id',
            'description',
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo department thành công',
            'data' => $department->load(['parent', 'manager']),
        ], 201);
    }

    /**
     * Display the specified resource.
     * GET /api/departments/{id}
     */
    public function show(Request $request, $id)
    {
        $department = Department::with(['parent', 'manager', 'children', 'users'])->find($id);

        if (! $department) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy department',
            ], 404);
        }

        // Kiểm tra quyền truy cập
        $user = $request->user();
        if (! $department->canBeAccessedBy($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không có quyền xem department này',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => $department,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     * PUT /api/departments/{id}
     *
     * Chỉ Admin mới được update department
     */
    public function update(Request $request, $id)
    {
        // Middleware role:admin đã check rồi
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Chỉ Admin mới được update department',
            ], 403);
        }

        $department = Department::find($id);

        if (! $department) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy department',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:departments,name,'.$id,
            'parent_id' => 'nullable|exists:departments,id',
            'manager_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Kiểm tra logic: parent_id không được là chính nó
        if ($request->parent_id && $request->parent_id == $id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Department không thể là parent của chính nó',
            ], 422);
        }

        $department->update($request->only([
            'name',
            'parent_id',
            'manager_id',
            'description',
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật department thành công',
            'data' => $department->load(['parent', 'manager']),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     * DELETE /api/departments/{id}
     *
     * Chỉ Admin mới được xóa department
     */
    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Chỉ Admin mới được xóa department',
            ], 403);
        }

        $department = Department::find($id);

        if (! $department) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy department',
            ], 404);
        }

        // Kiểm tra logic: Không xóa được nếu còn sub-departments
        if ($department->children()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể xóa department có sub-departments',
            ], 422);
        }

        // Kiểm tra logic: Không xóa được nếu còn users
        if ($department->users()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể xóa department có nhân viên. Hãy chuyển họ sang department khác trước.',
            ], 422);
        }

        $department->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Xóa department thành công',
        ], 200);
    }
}

