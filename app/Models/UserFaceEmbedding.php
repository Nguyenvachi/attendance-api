<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFaceEmbedding extends Model
{
    use HasFactory;

    protected $table = 'user_face_embeddings';

    protected $fillable = [
        'user_id',
        'embedding',
        'embedding_dim',
        'model_version',
        'sample_count',
        'registered_at',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
