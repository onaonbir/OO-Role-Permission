<?php

namespace OnaOnbir\OORolePermission\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use OnaOnbir\OORolePermission\Models\Role;
use OnaOnbir\OORolePermission\Services\TimePermissionValidator;
use OnaOnbir\OORolePermission\Support\CacheHelper;

class OORolePermission
{
    protected TimePermissionValidator $timeValidator;

    public function __construct(TimePermissionValidator $timeValidator = null)
    {
        $this->timeValidator = $timeValidator ?: app(TimePermissionValidator::class);
    }
    public function can(string|array $permission, string $guard = 'web'): bool
    {
        $user = Auth::guard($guard)->user();

        if (!$user) {
            return false;
        }

        return $this->modelHasPermission($user, $permission);
    }

    public function canWUser(Model $user, string|array $permission): bool
    {
        return $this->modelHasPermission($user, $permission);
    }

    public function hasRole(string|array $role, string $guard = 'web'): bool
    {
        $user = Auth::guard($guard)->user();

        if (!$user) {
            return false;
        }

        return $this->modelHasRole($user, $role);
    }

    public function hasRoleOrCan(array $roles, array $permissions, string $guard = 'web'): bool
    {
        return $this->hasRole($roles, $guard) || $this->can($permissions, $guard);
    }

    public function modelHasRole(Model $model, string|array $role): bool
    {
        // If time permissions are enabled, use time-aware validation
        if (config('oo-role-permission.time_permissions.enabled', true)) {
            return $this->timeValidator->validateUserRole($model, $role);
        }

        // Fallback to basic validation
        return $this->basicModelHasRole($model, $role);
    }

    private function basicModelHasRole(Model $model, string|array $role): bool
    {
        // Eager load roles if not already loaded
        if (!$model->relationLoaded('roles')) {
            $model->load(['roles' => function ($query) {
                $query->where('status', 'active');
            }]);
        }

        $roleNames = is_array($role) ? $role : [$role];
        
        return $model->roles->whereIn('name', $roleNames)->isNotEmpty();
    }

    public function modelHasPermission(Model $model, string|array $permission): bool
    {
        // If time permissions are enabled, use time-aware validation
        if (config('oo-role-permission.time_permissions.enabled', true)) {
            $permissions = is_array($permission) ? $permission : [$permission];
            foreach ($permissions as $perm) {
                // Priority 1: Check user-level time constraints first
                if (method_exists($model, 'hasTimeConstraints') && $model->hasTimeConstraints()) {
                    $userConstraints = $model->getTimeConstraintsForPermission($perm);
                    if ($userConstraints->isNotEmpty()) {
                        // If user has specific constraints for this permission, check them
                        foreach ($userConstraints as $constraint) {
                            if ($constraint->isValidAtTime(now())) {
                                return true; // User constraint allows access
                            }
                        }
                        // User has constraints but none are valid, still check role permissions as fallback
                    }
                }
                
                // Priority 2: Check role-based permissions via time validator
                if ($this->timeValidator->validateUserPermission($model, $perm)) {
                    return true;
                }
            }
            return false;
        }

        // Fallback to basic validation
        return $this->basicModelHasPermission($model, $permission);
    }

    private function basicModelHasPermission(Model $model, string|array $permission): bool
    {
        // Eager load roles if not already loaded
        if (!$model->relationLoaded('roles')) {
            $model->load(['roles' => function ($query) {
                $query->where('status', 'active');
            }]);
        }

        $permissions = is_array($permission) ? $permission : [$permission];
        
        // Check cache first if enabled
        if (CacheHelper::isEnabled()) {
            $cacheKey = $this->getPermissionCacheKey($model, $permissions);
            $cacheTags = ["user_permissions_{$model->id}"];
            
            return CacheHelper::remember($cacheKey, config('oo-role-permission.cache.ttl', 3600), function () use ($model, $permissions) {
                return $this->checkModelPermissions($model, $permissions);
            }, $cacheTags);
        }

        return $this->checkModelPermissions($model, $permissions);
    }

    private function checkModelPermissions(Model $model, array $permissions): bool
    {
        foreach ($model->roles as $role) {
            foreach ($permissions as $perm) {
                if ($this->checkPermission($role->permissions ?? [], $perm)) {
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

    private function getPermissionCacheKey(Model $model, array $permissions): string
    {
        $prefix = config('oo-role-permission.cache.key_prefix', 'oo_rp:');
        $permissionHash = md5(implode('|', $permissions));
        return "{$prefix}user_{$model->id}_permissions_{$permissionHash}";
    }

    public function assignRoleToModel(Model $model, string $roleName, array $additionalPermissions = []): void
    {
        $this->validateRoleAssignment($roleName);
        
        $roleModel = config('oo-role-permission.models.role');
        $role = $roleModel::where('name', $roleName)->where('status', 'active')->firstOrFail();
        
        $model->roles()->attach($role, [
            'additional_permissions' => json_encode($additionalPermissions),
        ]);
        
        $this->clearModelCache($model);
    }

    // Time-based methods
    public function modelHasRoleAtTime(Model $model, string|array $role, Carbon $time = null): bool
    {
        return $this->timeValidator->validateUserRole($model, $role, $time);
    }

    public function modelHasPermissionAtTime(Model $model, string|array $permission, Carbon $time = null): bool
    {
        $permissions = is_array($permission) ? $permission : [$permission];
        foreach ($permissions as $perm) {
            if ($this->timeValidator->validateUserPermission($model, $perm, $time)) {
                return true;
            }
        }
        return false;
    }

    public function assignTemporaryRole(Model $model, string $roleName, Carbon $expiresAt, array $additionalPermissions = [], string $timezone = null): void
    {
        $this->validateRoleAssignment($roleName);
        
        $roleModel = config('oo-role-permission.models.role');
        $role = $roleModel::where('name', $roleName)->where('status', 'active')->firstOrFail();
        
        $timezone = $timezone ?: $this->timeValidator->getUserTimezone($model);
        
        $role->createTemporaryAssignment($model, $expiresAt, $additionalPermissions, $timezone);
        
        $this->clearModelCache($model);
    }

    public function getUserTimeConstraints(Model $model): \Illuminate\Support\Collection
    {
        return $this->timeValidator->getUserTimeConstraints($model);
    }

    public function willUserHavePermissionAt(Model $model, string $permission, Carbon $futureTime): bool
    {
        return $this->timeValidator->willUserHavePermissionAt($model, $permission, $futureTime);
    }

    public function getNextPermissionChange(Model $model, string $permission): ?Carbon
    {
        return $this->timeValidator->getNextPermissionChange($model, $permission);
    }

    public function cleanupExpiredRoles(): int
    {
        return $this->timeValidator->cleanupExpiredAssignments();
    }

    public function getRoleTimeConstraints(string $roleName): \Illuminate\Support\Collection
    {
        $roleModel = config('oo-role-permission.models.role');
        $role = $roleModel::where('name', $roleName)->with('timePermissions')->first();
        
        if (!$role) {
            return collect();
        }
        
        return $role->timePermissions->map(function ($timePermission) {
            $permissions = !empty($timePermission->additional_permissions)
                ? implode(', ', $timePermission->additional_permissions)
                : 'All role permissions';
                
            return [
                'permissions' => $permissions,
                'schedule' => $timePermission->getReadableSchedule(),
                'timezone' => $timePermission->timezone,
                'is_active' => $timePermission->isValidAtTime(now())
            ];
        });
    }

    public function isRoleActiveAtTime(string $roleName, Carbon $time = null): bool
    {
        $roleModel = config('oo-role-permission.models.role');
        $role = $roleModel::where('name', $roleName)->with('timePermissions')->first();
        
        if (!$role) {
            return false;
        }
        
        return $this->timeValidator->validateRoleAtTime($role, $time);
    }

    public function updateRolePermissionsForModel(Model $model, string $roleName, array $additionalPermissions = []): void
    {
        $this->validateRoleAssignment($roleName);
        
        $roleModel = config('oo-role-permission.models.role');
        $role = $roleModel::where('name', $roleName)->where('status', 'active')->firstOrFail();
        
        $model->roles()->updateExistingPivot($role->id, [
            'additional_permissions' => json_encode($additionalPermissions),
        ]);
        
        $this->clearModelCache($model);
    }

    public function removeRoleFromModel(Model $model, string $roleName): void
    {
        $this->validateRoleAssignment($roleName);
        
        $roleModel = config('oo-role-permission.models.role');
        $role = $roleModel::where('name', $roleName)->firstOrFail();
        
        $model->roles()->detach($role);
        
        $this->clearModelCache($model);
    }

    private function validateRoleAssignment(string $roleName): void
    {
        if (empty($roleName)) {
            throw new InvalidArgumentException('Role name cannot be empty');
        }
    }

    private function clearModelCache(Model $model): void
    {
        if (!CacheHelper::isEnabled()) {
            return;
        }
        
        // Clear cache using our helper
        $cacheTags = ["user_permissions_{$model->id}"];
        CacheHelper::flush($cacheTags);
    }

    private function checkPermission(array $permissions, string $permission): bool
    {
        // Direct match check
        if ($this->hasDirectPermission($permissions, $permission)) {
            return true;
        }

        // Wildcard permission checks
        if ($this->hasWildcardPermission($permissions, $permission)) {
            return true;
        }

        // Reverse wildcard check
        if ($this->hasReverseWildcardPermission($permissions, $permission)) {
            return true;
        }

        return false;
    }

    private function hasDirectPermission(array $permissions, string $permission): bool
    {
        return in_array($permission, $permissions, true);
    }

    private function hasWildcardPermission(array $permissions, string $permission): bool
    {
        foreach ($permissions as $perm) {
            if (str_ends_with($perm, '.*')) {
                $wildcard = rtrim(substr($perm, 0, -2), '.');
                if (str_starts_with($permission, $wildcard.'.')) {
                    return true;
                }
            }
        }
        
        return false;
    }

    private function hasReverseWildcardPermission(array $permissions, string $permission): bool
    {
        if (!str_ends_with($permission, '.*')) {
            return false;
        }
        
        $wildcard = rtrim(substr($permission, 0, -2), '.');
        foreach ($permissions as $perm) {
            if (str_starts_with($perm, $wildcard.'.')) {
                return true;
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
