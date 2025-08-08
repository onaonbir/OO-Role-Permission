<?php

namespace OnaOnbir\OORolePermission\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use OnaOnbir\OORolePermission\Models\Role;
use OnaOnbir\OORolePermission\Models\TimePermission;

class TimePermissionValidator
{
    protected int $cacheTtl;
    protected bool $cacheEnabled;
    protected string $defaultTimezone;

    public function __construct()
    {
        $this->cacheTtl = config('oo-role-permission.time_permissions.cache_ttl', 1800);
        $this->cacheEnabled = config('oo-role-permission.time_permissions.enabled', true);
        $this->defaultTimezone = config('oo-role-permission.time_permissions.default_timezone', 'UTC');
    }

    /**
     * Validate if user has permission at specific time
     */
    public function validateUserPermission(Model $user, string $permission, Carbon $time = null): bool
    {
        if (!$this->cacheEnabled) {
            return $this->performUserPermissionCheck($user, $permission, $time);
        }

        $time = $time ?: now();
        $cacheKey = $this->getUserPermissionCacheKey($user, $permission, $time);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user, $permission, $time) {
            return $this->performUserPermissionCheck($user, $permission, $time);
        });
    }

    /**
     * Validate if user has role at specific time
     */
    public function validateUserRole(Model $user, string|array $role, Carbon $time = null): bool
    {
        $time = $time ?: now();
        $roles = is_array($role) ? $role : [$role];

        // Eager load roles with time constraints if not loaded
        if (!$user->relationLoaded('roles')) {
            $user->load(['roles' => function ($query) {
                $query->where('status', 'active')
                      ->with('timePermissions');
            }]);
        }

        foreach ($user->roles as $userRole) {
            if (in_array($userRole->name, $roles) && $this->validateRoleAtTime($userRole, $time)) {
                // Also check if role assignment itself hasn't expired
                if ($this->isRoleAssignmentValidAtTime($userRole->pivot, $time)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Validate if role is active at specific time
     */
    public function validateRoleAtTime(Role $role, Carbon $time = null): bool
    {
        $time = $time ?: now();

        // Role must be active
        if (!$role->isActive()) {
            return false;
        }

        // If no time constraints, role is always valid
        if (!$role->hasTimeConstraints()) {
            return true;
        }

        return $role->isActiveAtTime($time);
    }

    /**
     * Check if role assignment (pivot) is valid at time
     */
    public function isRoleAssignmentValidAtTime($pivot, Carbon $time = null): bool
    {
        $time = $time ?: now();

        // Check activation time
        if ($pivot->activated_at && $time->lt(Carbon::parse($pivot->activated_at))) {
            return false;
        }

        // Check expiration time
        if ($pivot->expires_at && $time->gt(Carbon::parse($pivot->expires_at))) {
            return false;
        }

        return true;
    }

    /**
     * Get user's timezone
     */
    public function getUserTimezone(Model $user): string
    {
        // Try to get from user model
        if (method_exists($user, 'getTimezone')) {
            return $user->getTimezone();
        }

        // Try timezone field
        if (isset($user->timezone)) {
            return $user->timezone;
        }

        // Try from role assignment
        $roleWithTimezone = $user->roles()
            ->wherePivotNotNull('timezone')
            ->first();

        if ($roleWithTimezone) {
            return $roleWithTimezone->pivot->timezone;
        }

        return $this->defaultTimezone;
    }

    /**
     * Convert time to user's timezone
     */
    public function convertToUserTimezone(Carbon $time, string $timezone): Carbon
    {
        return $time->copy()->setTimezone($timezone);
    }

    /**
     * Get all time constraints for user
     */
    public function getUserTimeConstraints(Model $user): Collection
    {
        if (!$user->relationLoaded('roles')) {
            $user->load(['roles.timePermissions' => function ($query) {
                $query->active();
            }]);
        }

        $constraints = collect();

        foreach ($user->roles as $role) {
            foreach ($role->timePermissions as $timePermission) {
                $constraints->push([
                    'role' => $role->name,
                    'role_readable' => $role->readable_name,
                    'permission' => $timePermission->permission_key ?: 'All permissions',
                    'schedule' => $timePermission->getReadableSchedule(),
                    'timezone' => $timePermission->timezone,
                    'is_active' => $timePermission->isValidAtTime(now())
                ]);
            }

            // Check role assignment expiry
            if ($role->pivot->expires_at) {
                $constraints->push([
                    'role' => $role->name,
                    'role_readable' => $role->readable_name,
                    'permission' => 'Role Assignment',
                    'schedule' => 'Expires: ' . Carbon::parse($role->pivot->expires_at)->format('d.m.Y H:i'),
                    'timezone' => $role->pivot->timezone ?: $this->defaultTimezone,
                    'is_active' => $this->isRoleAssignmentValidAtTime($role->pivot)
                ]);
            }
        }

        return $constraints;
    }

    /**
     * Check if user will have permission at future time
     */
    public function willUserHavePermissionAt(Model $user, string $permission, Carbon $futureTime): bool
    {
        return $this->validateUserPermission($user, $permission, $futureTime);
    }

    /**
     * Get next time when permission will be available/unavailable
     */
    public function getNextPermissionChange(Model $user, string $permission): ?Carbon
    {
        $currentStatus = $this->validateUserPermission($user, $permission);
        
        // Check next 24 hours in hourly intervals
        for ($i = 1; $i <= 24; $i++) {
            $checkTime = now()->addHours($i);
            $futureStatus = $this->validateUserPermission($user, $permission, $checkTime);
            
            if ($futureStatus !== $currentStatus) {
                return $checkTime;
            }
        }

        return null; // No change in next 24 hours
    }

    /**
     * Cleanup expired role assignments
     */
    public function cleanupExpiredAssignments(): int
    {
        $expiredCount = 0;
        $roles = Role::with('users')->get();

        foreach ($roles as $role) {
            $expiredCount += $role->cleanupExpiredAssignments();
        }

        return $expiredCount;
    }

    /**
     * Private helper methods
     */
    private function performUserPermissionCheck(Model $user, string $permission, Carbon $time = null): bool
    {
        $time = $time ?: now();

        // Eager load roles with time constraints if not loaded
        if (!$user->relationLoaded('roles')) {
            $user->load(['roles' => function ($query) {
                $query->where('status', 'active')
                      ->with('timePermissions');
            }]);
        }

        foreach ($user->roles as $role) {
            // Check if role assignment is valid at this time
            if (!$this->isRoleAssignmentValidAtTime($role->pivot, $time)) {
                continue;
            }

            // Check if role is valid at this time and has the permission
            if ($this->validateRoleAtTime($role, $time) && $role->isPermissionValidAtTime($permission, $time)) {
                return true;
            }
        }

        return false;
    }

    private function getUserPermissionCacheKey(Model $user, string $permission, Carbon $time): string
    {
        $timeKey = $time->format('Y-m-d_H'); // Hour-based caching
        $prefix = config('oo-role-permission.cache.key_prefix', 'oo_rp:');
        return "{$prefix}time_user_{$user->id}_permission_{$permission}_{$timeKey}";
    }
}
