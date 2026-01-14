<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// BỔ SUNG

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'google2fa_secret',
        'google_id',
        'nfc_uid',
        'hourly_rate',
        // BỔ SUNG: Khóa/Vô hiệu hóa tài khoản
        'is_active',
        'deactivated_at',
        'deactivated_by',
        'deactivated_reason',

        // BỔ SUNG: NFC nội dung thẻ (NDEF payload)
        'nfc_token_hash',
        'nfc_token_issued_at',
        'nfc_token_version',

        // BỔ SUNG: Sinh trắc học (vân tay/khuôn mặt)
        'biometric_id',
        'biometric_registered_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        // BỔ SUNG
        'is_active' => 'boolean',
        'deactivated_at' => 'datetime',
        'nfc_token_issued_at' => 'datetime',
        'biometric_registered_at' => 'datetime',
        // BỔ SUNG: Encrypt sensitive data at rest (SPRINT 1 Security)
        'nfc_token_hash' => 'encrypted',
        'biometric_id' => 'encrypted',
    ];

    /**
     * Relationship: User có nhiều attendances
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Relationship: User có 1 embedding khuôn mặt theo model_version.
     * (BỔ SUNG - phục vụ face recognition kiosk; không ảnh hưởng NFC)
     */
    public function faceEmbeddings()
    {
        return $this->hasMany(UserFaceEmbedding::class, 'user_id');
    }

    /**
     * SPRINT 2: Relationship - User belongs to Department
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * SPRINT 2: Scope query users theo department cho Manager
     * Admin: Thấy tất cả
     * Manager: Chỉ thấy users trong department của mình và sub-departments
     *
     * Usage: User::accessibleBy($user)->get()
     */
    public function scopeAccessibleBy($query, User $authUser)
    {
        // Admin: Thấy tất cả users
        if ($authUser->role === 'admin') {
            return $query;
        }

        // Manager: Chỉ thấy users trong department của mình và sub-departments
        if ($authUser->role === 'manager' && $authUser->department_id) {
            $managerDepartment = Department::find($authUser->department_id);
            if ($managerDepartment) {
                $allowedDepartmentIds = $managerDepartment->getAllDepartmentIds();

                return $query->whereIn('department_id', $allowedDepartmentIds);
            }
        }

        // Staff: Chỉ thấy chính mình
        return $query->where('id', $authUser->id);
    }
}
