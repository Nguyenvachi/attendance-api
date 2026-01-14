<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
// BỔ SUNG: Password policy (SPRINT 1 Security)
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    /**
     * Xử lý đăng nhập
     * POST /api/login
     */
    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email hoặc mật khẩu không đúng',
            ], 401);
        }

        // BỔ SUNG: Chặn đăng nhập nếu tài khoản bị khóa/vô hiệu hóa
        // (Tương thích ngược: nếu DB chưa migrate thì thuộc tính có thể null -> vẫn cho đăng nhập)
        if (isset($user->is_active) && $user->is_active === false) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tài khoản đã bị khóa. Vui lòng liên hệ quản lý.',
                'data' => [
                    'deactivated_at' => $user->deactivated_at ? $user->deactivated_at->format('Y-m-d H:i:s') : null,
                    'deactivated_reason' => $user->deactivated_reason,
                ],
            ], 403);
        }

        // THÊM MỚI: Kiểm tra xem user có bật 2FA không
        if ($user->google2fa_secret) {
            // Yêu cầu nhập mã OTP
            if (! $request->otp_code) {
                return response()->json([
                    'status' => 'require_2fa',
                    'message' => 'Vui lòng nhập mã xác thực Google Authenticator',
                    'data' => [
                        'user_id' => $user->id,
                        'email' => $user->email,
                    ],
                ], 200);
            }

            // Xác thực mã OTP
            $google2fa = new Google2FA();
            $valid = $google2fa->verifyKey($user->google2fa_secret, $request->otp_code);

            if (! $valid) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Mã xác thực không đúng',
                ], 401);
            }
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng nhập thành công',
            'data' => [
                'token' => $token,
                'user' => $user,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Đăng nhập bằng Google
     * POST /api/auth/google
     * Body: { email, google_id, name }
     */
    public function googleLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'google_id' => 'required|string',
            'name' => 'required|string',
        ]);

        // Tìm user bằng email hoặc google_id
        $user = User::where('email', $request->email)
                    ->orWhere('google_id', $request->google_id)
                    ->first();

        if ($user) {
            // User đã tồn tại, update google_id nếu chưa có
            if (! $user->google_id) {
                $user->google_id = $request->google_id;
                $user->save();
            }
        } else {
            // Tạo user mới với role mặc định là 'staff'
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'google_id' => $request->google_id,
                'password' => Hash::make(uniqid()), // Tạo password random
                'role' => 'staff', // Mặc định role staff
            ]);
        }

        // Tạo token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng nhập Google thành công',
            'data' => [
                'token' => $token,
                'user' => $user,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Đặt mật khẩu cho user Google
     * POST /api/auth/set-password
     * Body: { password }
     * Yêu cầu: Phải đăng nhập (có token)
     */
    public function setPassword(Request $request)
    {
        // BỔ SUNG: Password policy (SPRINT 1 Security)
        $request->validate([
            'password' => [
                'required',
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
            'password.symbols' => 'Mật khẩu phải có ký tự đặc biệt (@, $, !, %, *, ?, &)',
            'password.uncompromised' => 'Mật khẩu này đã bị rò rỉ trong các vụ hack. Vui lòng chọn mật khẩu khác',
        ]);

        $user = $request->user();

        // Cập nhật mật khẩu
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Đặt mật khẩu thành công. Giờ bạn có thể đăng nhập bằng email/password',
        ]);
    }
}
