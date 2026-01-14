<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FaceEmbeddingService;
use Illuminate\Http\Request;

class FaceEnrollmentController extends Controller
{
    /**
     * POST /api/users/face/enroll
     * Staff tự đăng ký khuôn mặt (embedding) cho chính mình.
     */
    public function enrollSelf(Request $request, FaceEmbeddingService $service)
    {
        $validated = $request->validate([
            'embedding' => 'required|string',
            'embedding_dim' => 'nullable|integer|min:64|max:1024',
            'model_version' => 'nullable|string|max:64',
            'sample_count' => 'nullable|integer|min:1|max:20',
        ]);

        $user = $request->user();

        $defaultModel = (string) env('FACE_MODEL_VERSION', 'mobilefacenet_v1');
        $row = $service->upsertEmbeddingForUser(
            $user,
            (string) $validated['embedding'],
            (int) ($validated['embedding_dim'] ?? 128),
            (string) ($validated['model_version'] ?? $defaultModel),
            (int) ($validated['sample_count'] ?? 1)
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng ký khuôn mặt (embedding) thành công',
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'model_version' => $row->model_version,
                'embedding_dim' => $row->embedding_dim,
                'sample_count' => $row->sample_count,
                'registered_at' => $row->registered_at ? $row->registered_at->format('Y-m-d H:i:s') : null,
            ],
        ]);
    }

    /**
     * PUT /api/users/{id}/face/enroll
     * Admin/Manager đăng ký khuôn mặt cho 1 user cụ thể.
     */
    public function enrollForUser(Request $request, $id, FaceEmbeddingService $service)
    {
        $validated = $request->validate([
            'embedding' => 'required|string',
            'embedding_dim' => 'nullable|integer|min:64|max:1024',
            'model_version' => 'nullable|string|max:64',
            'sample_count' => 'nullable|integer|min:1|max:20',
        ]);

        $user = User::find($id);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy nhân viên',
            ], 404);
        }

        $defaultModel = (string) env('FACE_MODEL_VERSION', 'mobilefacenet_v1');
        $row = $service->upsertEmbeddingForUser(
            $user,
            (string) $validated['embedding'],
            (int) ($validated['embedding_dim'] ?? 128),
            (string) ($validated['model_version'] ?? $defaultModel),
            (int) ($validated['sample_count'] ?? 1)
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng ký khuôn mặt (embedding) thành công',
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'model_version' => $row->model_version,
                'embedding_dim' => $row->embedding_dim,
                'sample_count' => $row->sample_count,
                'registered_at' => $row->registered_at ? $row->registered_at->format('Y-m-d H:i:s') : null,
            ],
        ]);
    }
}
