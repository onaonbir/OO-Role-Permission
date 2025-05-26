<?php

namespace OnaOnbir\OORolePermission\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OnaOnbir\OORolePermission\Enums\OORoleType;
use OnaOnbir\OORolePermission\Models\Traits\JsonCast;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'readable_name',
        'description',
        'permissions',
        'type',
        'state',
        'status',
        'attributes',
    ];

    protected $casts = [
        'permissions' => JsonCast::class,
        'attributes' => JsonCast::class,
    ];

    protected static function boot()
    {
        parent::boot();

        Role::creating(function ($model) {
            if (! $model->type) {
                $model->type = OORoleType::default()->value;
            }
        });
    }

    public function getTable(): string
    {
        return config('oo-role-permission.tables.roles');
    }

    public function users()
    {
        return $this->morphedByMany(User::class, 'model', 'oo_role_model', 'role_id', 'model_id')
            ->withPivot('additional_permissions');
    }
}
