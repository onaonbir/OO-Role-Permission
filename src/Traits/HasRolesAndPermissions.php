<?php

namespace OnaOnbir\OORolePermission\Traits;

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
        )->withPivot('additional_permissions');
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
        return oo_rp()->modelHasRole($this, $role);
    }

    public function hasPermission(string|array $permission): bool
    {
        return oo_rp()->modelHasPermission($this, $permission);
    }

    public function hasSubPermission(string $key): bool
    {
        return $this->hasPermission($key);
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
