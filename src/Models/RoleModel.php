<?php

namespace OnaOnbir\OORolePermission\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class RoleModel extends Pivot
{
    use HasFactory;

    protected $fillable = ['role_id', 'model_type','model_id', 'additional_permissions'];

    protected $casts = [
        'additional_permissions' => 'array',
    ];

    public function getTable(): string
    {
        return config('oo-role-permission.tables.role_models');
    }
}
