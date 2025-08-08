<?php

namespace OnaOnbir\OORolePermission\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasRolesAndPermissions
{
    public function roles(): MorphToMany
    {
        return $this->morphToMany(
            config('oo-role-permission.models.role'),
            'model',
            config('oo-role-permission.tables.role_models'),
            'model_id',
            'role_id'
        )->withPivot('additional_permissions', 'expires_at', 'activated_at', 'timezone');
    }

    public function assignRole(string $roleName, array $additionalPermissions = []): void
    {
        oo_rp()->assignRoleToModel($this, $roleName, $additionalPermissions);
    }

    public function updateRolePermissions(string $roleName, array $additionalPermissions = []): void
    {
        oo_rp()->updateRolePermissionsForModel($this, $roleName, $additionalPermissions);
    }

    public function removeRole(string $roleName): void
    {
        oo_rp()->removeRoleFromModel($this, $roleName);
    }

    public function hasRole(string|array $role): bool
    {
        // Eager load roles if not already loaded to prevent N+1
        if (!$this->relationLoaded('roles')) {
            $this->load(['roles' => function ($query) {
                $query->where('status', 'active');
            }]);
        }
        
        return oo_rp()->modelHasRole($this, $role);
    }

    public function hasPermission(string|array $permission): bool
    {
        // Eager load roles if not already loaded to prevent N+1
        if (!$this->relationLoaded('roles')) {
            $this->load(['roles' => function ($query) {
                $query->where('status', 'active');
            }]);
        }
        
        return oo_rp()->modelHasPermission($this, $permission);
    }

    public function hasRoleOrCan(array $roles, array $permissions): bool
    {
        return $this->hasRole($roles) || $this->hasPermission($permissions);
    }

    /**
     * @deprecated Use hasPermission() instead
     */
    public function hasSubPermission(string $key): bool
    {
        trigger_error('hasSubPermission is deprecated, use hasPermission instead', E_USER_DEPRECATED);
        return $this->hasPermission($key);
    }

    // Time-based methods
    public function assignTemporaryRole(string $roleName, Carbon $expiresAt, array $additionalPermissions = [], string $timezone = null): void
    {
        oo_rp()->assignTemporaryRole($this, $roleName, $expiresAt, $additionalPermissions, $timezone);
    }

    public function hasRoleAtTime(string|array $role, Carbon $time = null): bool
    {
        return oo_rp()->modelHasRoleAtTime($this, $role, $time);
    }

    public function hasPermissionAtTime(string|array $permission, Carbon $time = null): bool
    {
        return oo_rp()->modelHasPermissionAtTime($this, $permission, $time);
    }

    public function getActiveRoles(Carbon $time = null): \Illuminate\Support\Collection
    {
        $time = $time ?: now();
        
        if (!$this->relationLoaded('roles')) {
            $this->load(['roles' => function ($query) {
                $query->where('status', 'active')
                      ->with('timePermissions');
            }]);
        }
        
        return $this->roles->filter(function ($role) use ($time) {
            // Check if role assignment is valid
            if ($role->pivot->expires_at && $time->gt(Carbon::parse($role->pivot->expires_at))) {
                return false;
            }
            
            if ($role->pivot->activated_at && $time->lt(Carbon::parse($role->pivot->activated_at))) {
                return false;
            }
            
            // Check if role itself is valid at this time
            return $role->isActiveAtTime($time);
        });
    }

    public function getExpiredRoles(): \Illuminate\Support\Collection
    {
        if (!$this->relationLoaded('roles')) {
            $this->load('roles');
        }
        
        return $this->roles->filter(function ($role) {
            return $role->pivot->expires_at && now()->gt(Carbon::parse($role->pivot->expires_at));
        });
    }

    public function getTimeConstraints(): \Illuminate\Support\Collection
    {
        return oo_rp()->getUserTimeConstraints($this);
    }

    public function willHavePermissionAt(string $permission, Carbon $futureTime): bool
    {
        return oo_rp()->willUserHavePermissionAt($this, $permission, $futureTime);
    }

    public function getNextPermissionChange(string $permission): ?Carbon
    {
        return oo_rp()->getNextPermissionChange($this, $permission);
    }

    public function cleanupExpiredRoles(): int
    {
        $expiredCount = 0;
        $expiredRoles = $this->getExpiredRoles();
        
        foreach ($expiredRoles as $role) {
            $this->roles()->detach($role->id);
            $expiredCount++;
        }
        
        return $expiredCount;
    }

    public function hasAnyTimeConstraints(): bool
    {
        if (!$this->relationLoaded('roles')) {
            $this->load(['roles.timePermissions']);
        }
        
        foreach ($this->roles as $role) {
            if ($role->hasTimeConstraints() || $role->pivot->expires_at) {
                return true;
            }
        }
        
        return false;
    }

    public function getReadablePermissionName(string $key): ?string
    {
        return oo_rp()->getReadableNameByKey($key);
    }

    public function getPermissionDescription(string $key): ?string
    {
        return oo_rp()->getReadableNamesByKeys([$key])[$key] ?? null;
    }

    public function getAllPermissions(): array
    {
        return config('oo-role-permission.permissions', []);
    }

    public function getPermissionsByGroup(string $group): array
    {
        return oo_rp()->getPermissionsByGroup($group);
    }
}
