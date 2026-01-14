<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserFaceEmbedding;

class FaceEmbeddingService
{
    /**
     * Lưu/ghi đè embedding cho 1 user + model_version.
     * Lưu ý: BE chỉ lưu vector, KHÔNG làm nhận diện ở BE (để nhẹ và an toàn).
     */
    public function upsertEmbeddingForUser(
        User $user,
        string $embeddingJson,
        int $embeddingDim,
        string $modelVersion,
        int $sampleCount = 1
    ): UserFaceEmbedding {
        return UserFaceEmbedding::updateOrCreate(
            [
                'user_id' => $user->id,
                'model_version' => $modelVersion,
            ],
            [
                'embedding' => $embeddingJson,
                'embedding_dim' => $embeddingDim,
                'sample_count' => max(1, $sampleCount),
                'registered_at' => now(),
            ]
        );
    }

    /**
     * Trả về danh sách directory để kiosk cache & match.
     */
    public function getFaceDirectory(string $modelVersion): array
    {
        $rows = UserFaceEmbedding::query()
            ->where('model_version', $modelVersion)
            ->with(['user:id,name,email'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return $rows->map(function (UserFaceEmbedding $row) {
            return [
                'user_id' => $row->user_id,
                'user_name' => $row->user ? $row->user->name : null,
                'user_email' => $row->user ? $row->user->email : null,
                'embedding' => $row->embedding,
                'embedding_dim' => $row->embedding_dim,
                'model_version' => $row->model_version,
                'sample_count' => $row->sample_count,
                'updated_at' => $row->updated_at ? $row->updated_at->format('Y-m-d H:i:s') : null,
            ];
        })->values()->all();
    }
}
