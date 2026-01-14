<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NfcPayloadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Lấy danh sách tất cả users (Admin only)
     * GET /api/users
     */
    public function index()
    {
        $users = User::all();

        return response()->json([
            'status' => 'success',
            'data' => $users,
        ]);
    }

    /**
     * Tạo user mới (Admin only)
     * POST /api/users
     */
    public function store(Request $request)
    {
        // BỔ SUNG: Validation với Password policy (SPRINT 1 Security)
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => [
                'required',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
            'phone' => 'nullable|string|max:20',
            'role' => 'nullable|in:admin,manager,staff',
            'hourly_rate' => 'nullable|numeric|min:0',
        ], [
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự',
            'password.mixed' => 'Mật khẩu phải có chữ hoa và chữ thường',
            'password.numbers' => 'Mật khẩu phải có số',
            'password.symbols' => 'Mật khẩu phải có ký tự đặc biệt',
            'password.uncompromised' => 'Mật khẩu này không an toàn. Vui lòng chọn mật khẩu khác',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role' => $request->role ?? 'staff',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo nhân viên thành công',
            'data' => $user,
        ], 201);
    }

    /**
     * Cập nhật thông tin user (Admin only)
     * PUT /api/users/{id}
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy nhân viên',
            ], 404);
        }

        // Cập nhật các trường được gửi lên
        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }

        if ($request->has('role')) {
            $user->role = $request->role;
        }

        if ($request->has('hourly_rate')) {
            $user->hourly_rate = $request->hourly_rate;
        }

        // Chỉ cập nhật password nếu có gửi lên (không bắt buộc)
        if ($request->has('password') && ! empty($request->password)) {
            // BỔ SUNG: Password policy (SPRINT 1 Security)
            $request->validate([
                'password' => [
                    Password::min(8)
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                        ->uncompromised(),
                ],
            ], [
                'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự',
                'password.mixed' => 'Mật khẩu phải có chữ hoa và chữ thường',
                'password.numbers' => 'Mật khẩu phải có số',
                'password.symbols' => 'Mật khẩu phải có ký tự đặc biệt',
                'password.uncompromised' => 'Mật khẩu không an toàn',
            ]);
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật thông tin nhân viên thành công',
            'data' => $user,
        ]);
    }

    /**
     * Xóa user (Admin only)
     * DELETE /api/users/{id}
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy nhân viên',
            ], 404);
        }

        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Xóa nhân viên thành công',
        ]);
    }

    /**
     * PUT /api/users/{id}/status
     * Khóa / mở khóa tài khoản nhân viên (Admin only)
     * Body: { is_active: boolean, reason?: string }
     */
    public function updateStatus(Request $request, $id)
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy nhân viên',
            ], 404);
        }

        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'reason' => 'nullable|string|max:255',
        ]);

        $isActive = (bool) $validated['is_active'];
        $user->is_active = $isActive;

        if ($isActive) {
            // Mở khóa
            $user->deactivated_at = null;
            $user->deactivated_by = null;
            $user->deactivated_reason = null;
        } else {
            // Khóa
            $user->deactivated_at = now();
            $user->deactivated_by = $request->user() ? $request->user()->id : null;
            $user->deactivated_reason = $validated['reason'] ?? null;
        }

        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => $isActive ? 'Mở khóa tài khoản thành công' : 'Khóa tài khoản thành công',
            'data' => [
                'user_id' => $user->id,
                'is_active' => $user->is_active,
                'deactivated_at' => $user->deactivated_at ? $user->deactivated_at->format('Y-m-d H:i:s') : null,
                'deactivated_by' => $user->deactivated_by,
                'deactivated_reason' => $user->deactivated_reason,
            ],
        ]);
    }

    /**
     * POST /api/users/{id}/nfc/issue
     * Phát hành nội dung thẻ NFC (payload) để app mobile ghi NDEF lên thẻ.
     * Trả về payload dạng: NCTNFC:v1:<user_id>:<token>
     */
    public function issueNfcPayload(Request $request, $id, NfcPayloadService $nfcPayloadService)
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy nhân viên',
            ], 404);
        }

        $validated = $request->validate([
            'version' => 'nullable|integer|min:1|max:10',
        ]);

        $issued = $nfcPayloadService->issuePayloadForUser($user, (int) ($validated['version'] ?? 1));

        return response()->json([
            'status' => 'success',
            'message' => 'Phát hành nội dung thẻ NFC thành công',
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'payload_version' => $issued['version'],
                'payload' => $issued['payload'],
                // token chỉ để app ghi lên thẻ; backend chỉ lưu hash.
                'token' => $issued['token'],
                'issued_at' => $user->nfc_token_issued_at ? $user->nfc_token_issued_at->format('Y-m-d H:i:s') : null,
            ],
        ]);
    }

    /**
     * POST /api/users/biometric/register
     * Đăng ký sinh trắc học (vân tay/khuôn mặt) cho chính user đang đăng nhập.
     * Body: { biometric_id: string }
     */
    public function registerBiometric(Request $request)
    {
        $validated = $request->validate([
            'biometric_id' => 'required|string|max:255|unique:users,biometric_id',
        ]);

        $user = $request->user();
        $user->biometric_id = $validated['biometric_id'];
        $user->biometric_registered_at = now();
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng ký sinh trắc học thành công',
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'biometric_registered_at' => $user->biometric_registered_at ? $user->biometric_registered_at->format('Y-m-d H:i:s') : null,
            ],
        ]);
    }

    /**
     * PUT /api/users/{id}/biometric
     * Admin cập nhật biometric_id cho user (Admin only).
     * Body: { biometric_id: string }
     */
    public function updateBiometric(Request $request, $id)
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy nhân viên',
            ], 404);
        }

        $validated = $request->validate([
            'biometric_id' => 'required|string|max:255|unique:users,biometric_id,'.$id,
        ]);

        $user->biometric_id = $validated['biometric_id'];
        $user->biometric_registered_at = now();
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật sinh trắc học thành công',
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'biometric_id' => $user->biometric_id,
                'biometric_registered_at' => $user->biometric_registered_at ? $user->biometric_registered_at->format('Y-m-d H:i:s') : null,
            ],
        ]);
    }
}
