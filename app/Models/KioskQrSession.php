<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class KioskQrSession extends Model
{
    protected $fillable = [
        'kiosk_id',
        'code',
        'expires_at',
        'last_used_at',
        'meta',
        'created_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'meta' => 'array',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    public function isExpired(): bool
    {
        $expiresAt = $this->expires_at;
        return $expiresAt ? $expiresAt->lte(now()) : true;
    }
}
