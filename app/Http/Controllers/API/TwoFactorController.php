<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    /**
     * GET /api/2fa/setup
     * Tạo mã QR để quét vào Google Authenticator
     */
    public function setup(Request $request)
    {
        $user = $request->user();
        $google2fa = new Google2FA();

        // Tạo secret key mới
        $secret = $google2fa->generateSecretKey();

        // Tạo QR Code URL
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            'AttendanceApp',
            $user->email,
            $secret
        );

        // Lưu secret vào database
        $user->update([
            'google2fa_secret' => $secret,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Vui lòng quét mã QR bằng Google Authenticator',
            'data' => [
                'secret' => $secret,
                'qr_code_url' => $qrCodeUrl,
                'manual_entry_key' => $secret, // Nếu không quét được QR
            ],
        ]);
    }

    /**
     * POST /api/2fa/verify
     * Xác thực mã OTP từ Google Authenticator
     */
    public function verify(Request $request)
    {
        $user = $request->user();
        $google2fa = new Google2FA();

        // Kiểm tra xem user đã setup chưa
        if (! $user->google2fa_secret) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn chưa thiết lập bảo mật 2 lớp',
            ], 400);
        }

        // Xác thực mã OTP
        $valid = $google2fa->verifyKey(
            $user->google2fa_secret,
            $request->otp_code
        );

        if (! $valid) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mã xác thực không đúng',
            ], 401);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Xác thực thành công',
        ]);
    }

    /**
     * POST /api/2fa/disable
     * Tắt bảo mật 2 lớp
     */
    public function disable(Request $request)
    {
        $user = $request->user();
        $google2fa = new Google2FA();

        // Yêu cầu nhập mã OTP để xác nhận tắt
        if (! $request->otp_code) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vui lòng nhập mã xác thực để tắt 2FA',
            ], 400);
        }

        // Xác thực mã OTP
        $valid = $google2fa->verifyKey(
            $user->google2fa_secret,
            $request->otp_code
        );

        if (! $valid) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mã xác thực không đúng',
            ], 401);
        }

        // Xóa secret khỏi database
        $user->update([
            'google2fa_secret' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã tắt bảo mật 2 lớp',
        ]);
    }

    /**
     * GET /api/2fa/status
     * Kiểm tra trạng thái 2FA của user
     */
    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'data' => [
                'is_enabled' => ! is_null($user->google2fa_secret),
                'email' => $user->email,
            ],
        ]);
    }
}
