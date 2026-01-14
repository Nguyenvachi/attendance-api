<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\QrAttendanceService;
use Illuminate\Http\Request;

class QrAttendanceController extends Controller
{
    public function __construct(
        private QrAttendanceService $attendance,
    ) {
    }

    /**
     * POST /api/attendance/qr (auth required)
     * Staff quét QR trên kiosk để check-in/out.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'qr_code' => 'required|string|max:64',
            'device_info' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|max:64',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated',
            ], 401);
        }

        $result = $this->attendance->handle(
            user: $user,
            qrCode: $validated['qr_code'],
            payload: [
                'device_info' => $validated['device_info'] ?? null,
                'timezone' => $validated['timezone'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
            ],
        );

        return response()->json($result['body'], $result['http_code']);
    }
}
