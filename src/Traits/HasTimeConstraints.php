<?php

namespace OnaOnbir\OORolePermission\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use OnaOnbir\OORolePermission\Models\TimePermission;

trait HasTimeConstraints
{
    /**
     * Get all time constraints for this model
     */
    public function timePermissions(): MorphMany
    {
        $timePermissionModel = config('oo-role-permission.models.time_permission');

        // Ensure we have a valid class name string
        if (is_object($timePermissionModel)) {
            $timePermissionModel = get_class($timePermissionModel);
        }

        // Fallback to default if config is empty
        if (! $timePermissionModel || ! class_exists($timePermissionModel)) {
            $timePermissionModel = \OnaOnbir\OORolePermission\Models\TimePermission::class;
        }

        return $this->morphMany($timePermissionModel, 'constraintable');
    }

    /**
     * Get active time constraints
     */
    public function activeTimePermissions(): MorphMany
    {
        return $this->timePermissions()->where('is_active', true);
    }

    /**
     * Check if this model has time constraints
     */
    public function hasTimeConstraints(): bool
    {
        return $this->timePermissions()->where('is_active', true)->exists();
    }

    /**
     * Create time constraint for this model
     */
    public function addTimeConstraint(array $constraint): TimePermission
    {
        return $this->timePermissions()->create($constraint);
    }

    /**
     * Create time constraint for specific permissions
     */
    public function addPermissionTimeConstraint(array $permissions, array $timeRules): TimePermission
    {
        $constraint = array_merge($timeRules, [
            'additional_permissions' => $permissions,
        ]);

        return $this->addTimeConstraint($constraint);
    }

    /**
     * Add temporary permission (user-level temporary access)
     */
    public function addTemporaryPermission(array $permissions, Carbon $expiresAt, array $options = []): TimePermission
    {
        $constraint = array_merge($options, [
            'additional_permissions' => $permissions,
            'start_date' => now()->toDateString(),
            'end_date' => $expiresAt->toDateString(),
            'timezone' => $options['timezone'] ?? config('oo-role-permission.time_permissions.default_timezone', 'UTC'),
            'description' => $options['description'] ?? 'Temporary permission access',
        ]);

        return $this->addTimeConstraint($constraint);
    }

    /**
     * Get time constraints for specific permission
     */
    public function getTimeConstraintsForPermission(string $permission): \Illuminate\Support\Collection
    {
        return $this->timePermissions()
            ->where('is_active', true)
            ->get()
            ->filter(function ($constraint) use ($permission) {
                return $constraint->appliesToPermission($permission);
            });
    }

    /**
     * Check if model has permission at specific time (considering time constraints)
     */
    public function hasPermissionAtTime(string $permission, ?Carbon $time = null): bool
    {
        $time = $time ?: now();

        // Get constraints that apply to this permission
        $constraints = $this->getTimeConstraintsForPermission($permission);

        // If no time constraints, check base permission (for Users with roles)
        if ($constraints->isEmpty()) {
            // For User models, check role permissions
            if (method_exists($this, 'hasPermission')) {
                return $this->hasPermission($permission);
            }
            // For Role models, check role permissions
            if (method_exists($this, 'hasPermission')) {
                return $this->hasPermission($permission);
            }

            return false;
        }

        // Check if any constraint allows access at this time
        foreach ($constraints as $constraint) {
            if ($constraint->isValidAtTime($time)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get readable time constraint summary
     */
    public function getTimeConstraintsSummary(): array
    {
        if (! $this->hasTimeConstraints()) {
            return [
                'type' => 'unrestricted',
                'description' => 'No time constraints',
            ];
        }

        $constraints = $this->timePermissions()->where('is_active', true)->get();
        $summaries = [];

        foreach ($constraints as $constraint) {
            $permissions = ! empty($constraint->additional_permissions)
                ? implode(', ', $constraint->additional_permissions)
                : 'All permissions';

            $summaries[] = [
                'permissions' => $permissions,
                'schedule' => $constraint->getReadableSchedule(),
                'timezone' => $constraint->timezone,
                'is_active' => $constraint->isValidAtTime(now()),
            ];
        }

        return [
            'type' => 'time_restricted',
            'constraints' => $summaries,
        ];
    }

    /**
     * Clean up expired time constraints
     */
    public function cleanupExpiredTimeConstraints(): int
    {
        $expired = $this->timePermissions()
            ->where('end_date', '<', now()->toDateString())
            ->whereNotNull('end_date');

        $count = $expired->count();
        $expired->delete();

        return $count;
    }
}
