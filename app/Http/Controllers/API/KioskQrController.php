<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\QrKioskSessionService;
use Illuminate\Http\Request;

class KioskQrController extends Controller
{
    public function __construct(
        private QrKioskSessionService $sessions,
    ) {
    }

    /**
     * POST /api/kiosk/qr/session
     * Kiosk xin QR session mới (QR động, TTL ngắn).
     * - Không ảnh hưởng NFC.
     * - Có thể bảo vệ bằng header X-Kiosk-Token (env KIOSK_QR_TOKEN).
     */
    public function createSession(Request $request)
    {
        $tokenExpected = (string) env('KIOSK_QR_TOKEN', '');
        if ($tokenExpected !== '') {
            $token = (string) $request->header('X-Kiosk-Token', '');
            if (! hash_equals($tokenExpected, $token)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Kiosk token không hợp lệ',
                ], 403);
            }
        }

        $validated = $request->validate([
            'kiosk_id' => 'required|string|max:100',
            'meta' => 'nullable|array',
        ]);

        $userId = $request->user()?->id;
        $session = $this->sessions->createSession(
            kioskId: $validated['kiosk_id'],
            meta: $validated['meta'] ?? [],
            createdBy: $userId,
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'kiosk_id' => $session->kiosk_id,
                'code' => $session->code,
                'expires_at' => $session->expires_at?->toIso8601String(),
                'ttl_seconds' => $this->sessions->ttlSeconds(),
            ],
        ], 201);
    }

    /**
     * GET /api/kiosk/qr/session?kiosk_id=KIOSK_01
     * Lấy session đang còn hiệu lực; nếu không có thì tạo mới.
     */
    public function getSession(Request $request)
    {
        $tokenExpected = (string) env('KIOSK_QR_TOKEN', '');
        if ($tokenExpected !== '') {
            $token = (string) $request->header('X-Kiosk-Token', '');
            if (! hash_equals($tokenExpected, $token)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Kiosk token không hợp lệ',
                ], 403);
            }
        }

        $validated = $request->validate([
            'kiosk_id' => 'required|string|max:100',
        ]);

        $userId = $request->user()?->id;
        [$session, $created] = $this->sessions->getOrCreateSession(
            kioskId: $validated['kiosk_id'],
            meta: [],
            createdBy: $userId,
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'kiosk_id' => $session->kiosk_id,
                'code' => $session->code,
                'expires_at' => $session->expires_at?->toIso8601String(),
                'ttl_seconds' => $this->sessions->ttlSeconds(),
            ],
        ], $created ? 201 : 200);
    }
}
