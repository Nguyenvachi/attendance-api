<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * AuditLog Model
 * BỔ SUNG: Track mọi thay đổi dữ liệu trong hệ thống (who, what, when, where)
 */
class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'model',
        'model_id',
        'old_data',
        'new_data',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Relationship: AuditLog belongs to User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Filter by model type
     */
    public function scopeForModel($query, $model)
    {
        return $query->where('model', $model);
    }

    /**
     * Scope: Filter by action
     */
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Scope: Filter by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
