<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceProof extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attendance_id',
        'method',
        'action',
        'captured_at',
        'image_path',
        'meta',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }
}
