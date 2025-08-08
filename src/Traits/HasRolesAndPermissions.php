<?php

namespace OnaOnbir\OORolePermission\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use OnaOnbir\OORolePermission\Traits\HasTimeConstraints;

trait HasRolesAndPermissions
{
    use HasTimeConstraints; // Add time constraints support for users


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
        
        $permissions = is_array($permission) ? $permission : [$permission];
        
        foreach ($permissions as $perm) {
            // Priority 1: Check user-level time constraints first
            if ($this->hasTimeConstraints()) {
                $userConstraints = $this->getTimeConstraintsForPermission($perm);
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
            
            // Priority 2: Check role-based permissions
            if (oo_rp()->modelHasPermission($this, $perm)) {
                return true;
            }
        }
        
        return false;
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

    // Polymorphic Time Constraints Methods
    public function addUserTimeConstraint(array $permissions, array $timeRules): \OnaOnbir\OORolePermission\Models\TimePermission
    {
        return $this->addPermissionTimeConstraint($permissions, $timeRules);
    }

    public function addTemporaryUserPermission(array $permissions, Carbon $expiresAt, array $options = []): \OnaOnbir\OORolePermission\Models\TimePermission
    {
        return $this->addTemporaryPermission($permissions, $expiresAt, $options);
    }

    public function hasUserPermissionConstraint(string $permission): bool
    {
        return $this->getTimeConstraintsForPermission($permission)->isNotEmpty();
    }

    public function getAllTimeConstraints(): \Illuminate\Support\Collection
    {
        // Combine both user-level and role-level time constraints
        $constraints = collect();

        // User-level constraints
        $userConstraints = $this->getTimeConstraintsSummary();
        if ($userConstraints['type'] === 'time_restricted') {
            foreach ($userConstraints['constraints'] as $constraint) {
                $constraints->push(array_merge($constraint, [
                    'level' => 'user',
                    'source' => 'User Direct'
                ]));
            }
        }

        // Role-level constraints
        if (!$this->relationLoaded('roles')) {
            $this->load(['roles.timePermissions']);
        }

        foreach ($this->roles as $role) {
            $roleConstraints = $role->getTimeConstraintsSummary();
            if ($roleConstraints['type'] === 'time_restricted') {
                foreach ($roleConstraints['constraints'] as $constraint) {
                    $constraints->push(array_merge($constraint, [
                        'level' => 'role',
                        'source' => "Role: {$role->name}"
                    ]));
                }
            }
        }

        return $constraints;
    }

    public function hasPermissionWithPriority(string $permission): array
    {
    $result = [
    'has_permission' => false,
    'source' => null,
    'level' => null,
    'time_valid' => null
    ];
    
    // Priority 1: User-level time constraints
    if ($this->hasTimeConstraints()) {
    $userConstraints = $this->getTimeConstraintsForPermission($permission);
    if ($userConstraints->isNotEmpty()) {
    foreach ($userConstraints as $constraint) {
    if ($constraint->isValidAtTime(now())) {
    $result = [
    'has_permission' => true,
    'source' => 'User Time Constraint',
    'level' => 'user',
    'time_valid' => true
    ];
    break;
    }
    }
    // If user has constraints but none are valid, check role permissions
    if (!$result['has_permission']) {
    // Still check role permissions as fallback
    $hasRolePermission = $this->checkRolePermissionAtTime($permission);
    if ($hasRolePermission) {
    $result = [
        'has_permission' => true,
            'source' => 'Role Permission (User constraints not valid)',
                'level' => 'role',
                'time_valid' => true
                ];
                } else {
                    $result = [
                        'has_permission' => false,
                        'source' => 'User Time Constraint',
                    'level' => 'user',
                    'time_valid' => false
            ];
        }
    }
    return $result;
    }
    }
    
    // Priority 2: Role-based permissions
        $hasRolePermission = $this->checkRolePermissionAtTime($permission);
        $result = [
            'has_permission' => $hasRolePermission,
            'source' => 'Role Permission',
            'level' => 'role',
            'time_valid' => $hasRolePermission
        ];
        
        return $result;
    }

    private function checkRolePermissionAtTime(string $permission, ?Carbon $time = null): bool
    {
        $time = $time ?: now();
        
        if (!$this->relationLoaded('roles')) {
            $this->load(['roles' => function ($query) {
                $query->where('status', 'active')
                      ->with('timePermissions');
            }]);
        }
        
        foreach ($this->roles as $role) {
            // Check if role assignment is valid at this time
            if ($role->pivot->expires_at && $time->gt(Carbon::parse($role->pivot->expires_at))) {
                continue;
            }
            
            if ($role->pivot->activated_at && $time->lt(Carbon::parse($role->pivot->activated_at))) {
                continue;
            }
            
            // Check if role has the permission and is valid at this time
            if ($role->isPermissionValidAtTime($permission, $time)) {
                return true;
            }
            
            // Also check additional permissions from pivot
            $additionalPerms = json_decode($role->pivot->additional_permissions ?? '[]', true);
            if (!empty($additionalPerms)) {
                foreach ($additionalPerms as $perm) {
                    if ($this->checkWildcardMatch($perm, $permission)) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    private function checkWildcardMatch(string $definedPermission, string $requestedPermission): bool
    {
        // Direct match
        if ($definedPermission === $requestedPermission) {
            return true;
        }
        
        // Universal wildcard
        if ($definedPermission === '*') {
            return true;
        }
        
        // Wildcard match (e.g., "admin.*" matches "admin.users")
        if (str_ends_with($definedPermission, '.*')) {
            $prefix = rtrim(substr($definedPermission, 0, -2), '.');
            if (!empty($prefix) && str_starts_with($requestedPermission, $prefix . '.')) {
                return true;
            }
        }
        
        // Reverse wildcard (requested permission is wildcard)
        if (str_ends_with($requestedPermission, '.*')) {
            $prefix = rtrim(substr($requestedPermission, 0, -2), '.');
            if (!empty($prefix) && str_starts_with($definedPermission, $prefix . '.')) {
                return true;
            }
        }
        
        return false;
    }
}
