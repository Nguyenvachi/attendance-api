<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'parent_id',
        'manager_id',
        'description',
    ];

    /**
     * Relationships
     */

    // Department có thể có parent department (nested structure)
    public function parent()
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    // Department có nhiều sub-departments
    public function children()
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    // Department có 1 manager
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    // Department có nhiều users (nhân viên)
    public function users()
    {
        return $this->hasMany(User::class, 'department_id');
    }

    /**
     * Helper Methods
     */

    // Lấy toàn bộ department IDs trong cây (bao gồm cả sub-departments)
    public function getAllDepartmentIds(): array
    {
        $ids = [$this->id];
        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->getAllDepartmentIds());
        }

        return $ids;
    }

    // Kiểm tra xem user có quyền quản lý department này không
    public function canBeAccessedBy(User $user): bool
    {
        // Admin: Thấy tất cả
        if ($user->role === 'admin') {
            return true;
        }

        // Manager: Chỉ thấy department của mình và sub-departments
        if ($user->role === 'manager' && $user->department_id) {
            $managerDepartment = Department::find($user->department_id);
            if ($managerDepartment) {
                return in_array($this->id, $managerDepartment->getAllDepartmentIds());
            }
        }

        return false;
    }
}
