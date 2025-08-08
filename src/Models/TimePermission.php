<?php

namespace OnaOnbir\OORolePermission\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Log;
use OnaOnbir\OORolePermission\Support\CacheHelper;

class TimePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'constraintable_type',
        'constraintable_id',
        'additional_permissions',
        'start_time',
        'end_time',
        'start_date',
        'end_date',
        'timezone',
        'days_of_week',
        'is_active',
        'description',
    ];

    protected $casts = [
        'additional_permissions' => 'array',
        'days_of_week' => 'array',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function getTable(): string
    {
        return config('oo-role-permission.tables.time_permissions', 'oo_time_permissions');
    }

    // Polymorphic Relations
    public function constraintable(): MorphTo
    {
        return $this->morphTo('constraintable');
    }

    // Helper to get role ID safely
    public function getRoleId(): ?int
    {
        if ($this->constraintable_type === config('oo-role-permission.models.role') && $this->constraintable_id) {
            return (int) $this->constraintable_id;
        }

        return null;
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForPermission(Builder $query, string $permission): Builder
    {
        return $query->where(function ($q) use ($permission) {
            // If additional_permissions is null/empty, applies to all role permissions
            $q->whereNull('additional_permissions')
                ->orWhereJsonLength('additional_permissions', 0)
              // OR check if permission matches any in the additional_permissions array
                ->orWhere(function ($subQuery) use ($permission) {
                    $subQuery->whereJsonContains('additional_permissions', $permission)
                             // Also check for wildcard matches in JSON
                        ->orWhereRaw('JSON_SEARCH(additional_permissions, "one", ?) IS NOT NULL', ["%{$permission}%"]);
                });
        });
    }

    public function scopeValidAt(Builder $query, Carbon $time): Builder
    {
        return $query->where(function ($q) use ($time) {
            // Date range validation
            $q->where(function ($dateQuery) use ($time) {
                $dateQuery->whereNull('start_date')
                    ->orWhere('start_date', '<=', $time->toDateString());
            })->where(function ($dateQuery) use ($time) {
                $dateQuery->whereNull('end_date')
                    ->orWhere('end_date', '>=', $time->toDateString());
            });
        });
    }

    // Validation methods
    public function isValidAtTime(Carbon $time): bool
    {
        try {
            // Use default timezone if not set
            $timezone = $this->timezone ?: config('oo-role-permission.time_permissions.default_timezone', 'UTC');

            // Convert time to the constraint's timezone
            $timeInConstraintTimezone = $time->copy()->setTimezone($timezone);

            // Check date range
            if (! $this->isValidOnDate($timeInConstraintTimezone)) {
                return false;
            }

            // Check day of week
            if (! $this->isValidOnDay($timeInConstraintTimezone->dayOfWeek)) {
                return false;
            }

            // Check time of day
            if (! $this->isValidAtTimeOfDay($timeInConstraintTimezone)) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            // Log error and return false on timezone or other errors
            Log::warning('TimePermission validation error: '.$e->getMessage(), [
                'time_permission_id' => $this->id,
                'timezone' => $this->timezone,
                'time' => $time->toISOString(),
            ]);

            return false;
        }
    }

    public function isValidOnDate(Carbon $date): bool
    {
        $dateString = $date->toDateString();

        // Check start date
        if ($this->start_date && $this->start_date->toDateString() > $dateString) {
            return false;
        }

        // Check end date
        if ($this->end_date && $this->end_date->toDateString() < $dateString) {
            return false;
        }

        return true;
    }

    public function isValidOnDay(int $dayOfWeek): bool
    {
        // Validate input - dayOfWeek should be 0-6
        if ($dayOfWeek < 0 || $dayOfWeek > 6) {
            return false;
        }

        // If no day constraints, allow all days
        if (empty($this->days_of_week) || ! is_array($this->days_of_week)) {
            return true;
        }

        // Convert Laravel's dayOfWeek (0=Sunday) to ISO format (1=Monday, 7=Sunday)
        $isoDayOfWeek = $dayOfWeek === 0 ? 7 : $dayOfWeek;

        return in_array($isoDayOfWeek, $this->days_of_week, true);
    }

    public function isValidAtTimeOfDay(Carbon $time): bool
    {
        // If no time constraints, allow all times
        if (! $this->start_time && ! $this->end_time) {
            return true;
        }

        $currentTime = $time->format('H:i:s');

        // Check start time
        if ($this->start_time && $currentTime < $this->start_time) {
            return false;
        }

        // Check end time
        if ($this->end_time && $currentTime > $this->end_time) {
            return false;
        }

        return true;
    }

    // Helper methods
    public function appliesToPermission(string $permission): bool
    {
        // Validate input
        if (empty($permission)) {
            return false;
        }

        // If additional_permissions is null or empty, applies to all role permissions
        if (empty($this->additional_permissions) || ! is_array($this->additional_permissions)) {
            return true;
        }

        // Check each permission in additional_permissions array
        foreach ($this->additional_permissions as $constraintPermission) {
            // Skip empty or non-string permissions
            if (empty($constraintPermission) || ! is_string($constraintPermission)) {
                continue;
            }

            // Direct match
            if ($constraintPermission === $permission) {
                return true;
            }

            // Wildcard match (e.g., "admin.*" matches "admin.users")
            if (str_ends_with($constraintPermission, '.*')) {
                $prefix = rtrim(substr($constraintPermission, 0, -2), '.');
                if (! empty($prefix) && str_starts_with($permission, $prefix.'.')) {
                    return true;
                }
            }

            // Reverse wildcard (requested permission is wildcard)
            if (str_ends_with($permission, '.*')) {
                $prefix = rtrim(substr($permission, 0, -2), '.');
                if (! empty($prefix) && str_starts_with($constraintPermission, $prefix.'.')) {
                    return true;
                }
            }

            // Universal wildcard
            if ($constraintPermission === '*') {
                return true;
            }
        }

        return false;
    }

    public function getReadableSchedule(): string
    {
        $parts = [];

        // Permissions info
        if (! empty($this->additional_permissions) && is_array($this->additional_permissions)) {
            $validPermissions = array_filter($this->additional_permissions, fn ($p) => ! empty($p) && is_string($p));
            if (! empty($validPermissions)) {
                $permissions = implode(', ', $validPermissions);
                $parts[] = "Permissions: {$permissions}";
            } else {
                $parts[] = 'All role permissions';
            }
        } else {
            $parts[] = 'All role permissions';
        }

        // Date range
        if ($this->start_date || $this->end_date) {
            try {
                $start = $this->start_date ? $this->start_date->format('d.m.Y') : '...';
                $end = $this->end_date ? $this->end_date->format('d.m.Y') : '...';
                $parts[] = "{$start} - {$end}";
            } catch (\Exception $e) {
                $parts[] = 'Date range: Invalid format';
            }
        }

        // Days of week
        if (! empty($this->days_of_week) && is_array($this->days_of_week)) {
            $dayNames = [
                1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba',
                4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar',
            ];
            $validDays = array_filter($this->days_of_week, fn ($day) => is_int($day) && $day >= 1 && $day <= 7);
            if (! empty($validDays)) {
                $activeDays = array_map(fn ($day) => $dayNames[$day] ?? 'Invalid day', $validDays);
                $parts[] = implode(', ', $activeDays);
            }
        }

        // Time range
        if ($this->start_time || $this->end_time) {
            $start = $this->start_time ?: '00:00';
            $end = $this->end_time ?: '23:59';
            $parts[] = "{$start} - {$end}";
        }

        // Timezone
        $timezone = $this->timezone ?: 'UTC';
        if ($timezone !== 'UTC') {
            $parts[] = "({$timezone})";
        }

        return implode(' | ', $parts) ?: 'Her zaman aktif';
    }

    // Cache helpers
    public function getCacheKey(string $permission, ?Carbon $time = null): string
    {
        $time = $time ?? Carbon::now();
        $timeKey = $time->format('Y-m-d_H'); // Hour-based caching
        $permissionKey = str_replace([':', '.', '*'], ['_', '_', 'wildcard'], $permission);

        return CacheHelper::key("time_permission_{$this->id}_{$permissionKey}_{$timeKey}");
    }

    public static function clearCacheForRole(?int $roleId): void
    {
        if (! CacheHelper::isEnabled() || ! $roleId) {
            return;
        }

        $cacheTags = ["time_permissions_role_{$roleId}"];
        CacheHelper::flush($cacheTags);
    }

    public static function clearGeneralTimeCache(): void
    {
        if (! CacheHelper::isEnabled()) {
            return;
        }

        $cacheTags = ['time_permissions', 'time_constraints'];
        CacheHelper::flush($cacheTags);
    }

    // Boot method for cache management
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($timePermission) {
            // Clear role-specific cache if this constraint belongs to a role
            $roleId = $timePermission->getRoleId();
            if ($roleId) {
                self::clearCacheForRole($roleId);
            }

            // Also clear general time permission cache
            self::clearGeneralTimeCache();
        });

        static::deleted(function ($timePermission) {
            // Clear role-specific cache if this constraint belongs to a role
            $roleId = $timePermission->getRoleId();
            if ($roleId) {
                self::clearCacheForRole($roleId);
            }

            // Also clear general time permission cache
            self::clearGeneralTimeCache();
        });
    }
}
