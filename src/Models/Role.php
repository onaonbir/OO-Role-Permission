<?php

namespace OnaOnbir\OORolePermission\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use OnaOnbir\OORolePermission\Enums\OORoleStatus;
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
        'status' => OORoleStatus::class,
        'type' => OORoleType::class,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->type) {
                $model->type = OORoleType::default()->value;
            }
            if (!$model->status) {
                $model->status = OORoleStatus::ACTIVE->value;
            }
        });

        // Clear cache when role is modified
        static::saved(function ($role) {
            Cache::tags(['oo_rp_roles'])->flush();
        });

        static::deleted(function ($role) {
            Cache::tags(['oo_rp_roles'])->flush();
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

    // Scopes for better query performance
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', OORoleStatus::ACTIVE);
    }

    public function scopeByType(Builder $query, OORoleType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeWithPermissions(Builder $query): Builder
    {
        return $query->whereNotNull('permissions')
            ->where('permissions', '!=', '[]');
    }

    public function scopeByName(Builder $query, string $name): Builder
    {
        return $query->where('name', $name);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === OORoleStatus::ACTIVE;
    }

    public function isSystemDefault(): bool
    {
        return $this->type === OORoleType::SYSTEM_DEFAULT;
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];
        return in_array($permission, $permissions, true) || in_array('*', $permissions, true);
    }
}
