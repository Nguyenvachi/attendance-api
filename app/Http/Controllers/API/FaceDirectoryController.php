<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\FaceEmbeddingService;
use Illuminate\Http\Request;

class FaceDirectoryController extends Controller
{
    /**
     * GET /api/kiosk/face-directory
     * Kiosk tải danh sách embedding để cache/match.
     * Có thể bảo vệ bằng X-Kiosk-Token (env KIOSK_FACE_TOKEN) nếu muốn.
     */
    public function index(Request $request, FaceEmbeddingService $service)
    {
        $requiredToken = (string) env('KIOSK_FACE_TOKEN', '');
        if ($requiredToken !== '') {
            $token = (string) $request->header('X-Kiosk-Token', '');
            if (! hash_equals($requiredToken, $token)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Kiosk token không hợp lệ',
                ], 401);
            }
        }

        $defaultModel = (string) env('FACE_MODEL_VERSION', 'mobilefacenet_v1');
        $modelVersion = (string) $request->query('model_version', $defaultModel);
        $data = $service->getFaceDirectory($modelVersion);

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'model_version' => $modelVersion,
                'count' => count($data),
            ],
        ]);
    }
}
