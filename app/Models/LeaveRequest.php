<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'reason',
        'start_date',
        'end_date',
        'status',
        'approved_by',
        'admin_note',
    ];

    /**
     * Relationship: LeaveRequest thuộc về User (người gửi)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: LeaveRequest được duyệt bởi User (admin/manager)
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
