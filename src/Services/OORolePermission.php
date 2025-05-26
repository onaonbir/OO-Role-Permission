<?php

namespace OnaOnbir\OORolePermission\Services;

use Illuminate\Support\Facades\Auth;

class OORolePermission
{
    public function can($permission, $guard = 'web'): bool
    {
        $user = Auth::guard($guard)->user();

        if (! $user) {
            return false;
        }

        return $this->modelHasPermission($user, $permission);
    }

    public function canWUser($user, $permission): bool
    {
        return $this->modelHasPermission($user, $permission);
    }

    public function hasRole($role, $guard = 'web'): bool
    {
        $user = Auth::guard($guard)->user();

        if (! $user) {
            return false;
        }

        return $this->modelHasRole($user, $role);
    }

    public function hasRoleOrCan(array $roles, array $permissions, string $guard = 'web'): bool
    {
        return $this->hasRole($roles, $guard) || $this->can($permissions, $guard);
    }

    public function modelHasRole($model, $role): bool
    {
        $roleModel = config('oo-role-permission.models.role');

        if (is_array($role)) {
            return $model->roles()->whereIn('name', $role)->where('status', 'active')->exists();
        }

        return $model->roles()->where('name', $role)->where('status', 'active')->exists();
    }

    public function modelHasPermission($model, $permission): bool
    {
        $permissions = is_array($permission) ? $permission : [$permission];

        foreach ($model->roles as $role) {
            foreach ($permissions as $perm) {
                if ($this->checkPermission($role->permissions, $perm)) {
                    return true;
                }

                $additional = json_decode($role->pivot->additional_permissions ?? '[]', true);
                if ($this->checkPermission($additional, $perm)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function assignRoleToModel($model, $roleName, array $additionalPermissions = []): void
    {
        $roleModel = config('oo-role-permission.models.role');
        $role = $roleModel::where('name', $roleName)->firstOrFail();
        $model->roles()->attach($role, [
            'additional_permissions' => json_encode($additionalPermissions),
        ]);
    }

    public function updateRolePermissionsForModel($model, $roleName, array $additionalPermissions = []): void
    {
        $roleModel = config('oo-role-permission.models.role');
        $role = $roleModel::where('name', $roleName)->firstOrFail();
        $model->roles()->updateExistingPivot($role->id, [
            'additional_permissions' => json_encode($additionalPermissions),
        ]);
    }

    public function removeRoleFromModel($model, $roleName): void
    {
        $roleModel = config('oo-role-permission.models.role');
        $role = $roleModel::where('name', $roleName)->firstOrFail();
        $model->roles()->detach($role);
    }

    private function checkPermission(array $permissions, string $permission): bool
    {
        // Direct match
        if (in_array($permission, $permissions, true)) {
            return true;
        }

        // Wildcard match: defined permission has wildcard (e.g., post.*)
        foreach ($permissions as $perm) {
            if (str_ends_with($perm, '.*')) {
                $wildcard = rtrim(substr($perm, 0, -2), '.');
                if (str_starts_with($permission, $wildcard . '.')) {
                    return true;
                }
            }
        }

        // Reverse wildcard match: requested permission is a wildcard
        if (str_ends_with($permission, '.*')) {
            $wildcard = rtrim(substr($permission, 0, -2), '.');
            foreach ($permissions as $perm) {
                if (str_starts_with($perm, $wildcard . '.')) {
                    return true;
                }
            }
        }

        return false;
    }


    public function flattenPermissions(array $permissions, string $prefix = ''): array
    {
        $flat = [];

        foreach ($permissions as $permission) {
            $string = $prefix.$permission['readable_name'].' || '.($permission['description'] ?? '');
            $flat[$permission['key']] = $string;

            if (isset($permission['sub_permissions'])) {
                $children = $this->flattenPermissions($permission['sub_permissions'], $permission['readable_name'].' || ');
                $flat = array_merge($flat, $children);
            }
        }

        return $flat;
    }

    public function getReadableNameByKey(string $key, array $additionalPermissions = []): ?string
    {
        $permissions = array_merge(config('oo-role-permission.permissions', []), $additionalPermissions);
        $flat = $this->flattenPermissions($permissions);

        return $flat[$key] ?? null;
    }

    public function getReadableNamesByKeys(array $keys, array $additionalPermissions = []): array
    {
        $permissions = array_merge(config('oo-role-permission.permissions', []), $additionalPermissions);
        $flat = $this->flattenPermissions($permissions);

        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $flat[$key] ?? null;
        }

        return $result;
    }

    public function getPermissionsByGroup(string $group): array
    {
        return $this->filterPermissionsByGroup(config('oo-role-permission.permissions', []), $group);
    }

    private function filterPermissionsByGroup(array $permissions, string $group, ?string $parentKey = null): array
    {
        $result = [];

        foreach ($permissions as $permission) {
            $fullKey = $parentKey ? $parentKey.'.'.$permission['key'] : $permission['key'];

            if (($permission['group'] ?? null) === $group) {
                $permission['key'] = $fullKey;
                $result[] = $permission;
            }

            if (isset($permission['sub_permissions'])) {
                $children = $this->filterPermissionsByGroup($permission['sub_permissions'], $group, $fullKey);
                $result = array_merge($result, $children);
            }
        }

        return $result;
    }
}
