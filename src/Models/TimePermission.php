<?php

namespace OnaOnbir\OORolePermission\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OnaOnbir\OORolePermission\Support\CacheHelper;

class TimePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id',
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

    // Relations
    public function role(): BelongsTo
    {
        return $this->belongsTo(config('oo-role-permission.models.role'), 'role_id');
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
        // Convert time to the constraint's timezone
        $timeInConstraintTimezone = $time->copy()->setTimezone($this->timezone);
        
        // Check date range
        if (!$this->isValidOnDate($timeInConstraintTimezone)) {
            return false;
        }

        // Check day of week
        if (!$this->isValidOnDay($timeInConstraintTimezone->dayOfWeek)) {
            return false;
        }

        // Check time of day
        if (!$this->isValidAtTimeOfDay($timeInConstraintTimezone)) {
            return false;
        }

        return true;
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
        // If no day constraints, allow all days
        if (empty($this->days_of_week)) {
            return true;
        }

        // Convert Laravel's dayOfWeek (0=Sunday) to ISO format (1=Monday, 7=Sunday)
        $isoDayOfWeek = $dayOfWeek === 0 ? 7 : $dayOfWeek;

        return in_array($isoDayOfWeek, $this->days_of_week);
    }

    public function isValidAtTimeOfDay(Carbon $time): bool
    {
        // If no time constraints, allow all times
        if (!$this->start_time && !$this->end_time) {
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
        // If additional_permissions is null or empty, applies to all role permissions
        if (empty($this->additional_permissions)) {
            return true;
        }

        // Check each permission in additional_permissions array
        foreach ($this->additional_permissions as $constraintPermission) {
            // Direct match
            if ($constraintPermission === $permission) {
                return true;
            }

            // Wildcard match (e.g., "admin.*" matches "admin.users")
            if (str_ends_with($constraintPermission, '.*')) {
                $prefix = rtrim(substr($constraintPermission, 0, -2), '.');
                if (str_starts_with($permission, $prefix . '.')) {
                    return true;
                }
            }

            // Reverse wildcard (requested permission is wildcard)
            if (str_ends_with($permission, '.*')) {
                $prefix = rtrim(substr($permission, 0, -2), '.');
                if (str_starts_with($constraintPermission, $prefix . '.')) {
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
        if (!empty($this->additional_permissions)) {
            $permissions = implode(', ', $this->additional_permissions);
            $parts[] = "Permissions: {$permissions}";
        } else {
            $parts[] = "All role permissions";
        }

        // Date range
        if ($this->start_date || $this->end_date) {
            $start = $this->start_date ? $this->start_date->format('d.m.Y') : '...';
            $end = $this->end_date ? $this->end_date->format('d.m.Y') : '...';
            $parts[] = "{$start} - {$end}";
        }

        // Days of week
        if (!empty($this->days_of_week)) {
            $dayNames = [
                1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 
                4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'
            ];
            $activeDays = array_map(fn($day) => $dayNames[$day], $this->days_of_week);
            $parts[] = implode(', ', $activeDays);
        }

        // Time range
        if ($this->start_time || $this->end_time) {
            $start = $this->start_time ?: '00:00';
            $end = $this->end_time ?: '23:59';
            $parts[] = "{$start} - {$end}";
        }

        // Timezone
        if ($this->timezone !== 'UTC') {
            $parts[] = "({$this->timezone})";
        }

        return implode(' | ', $parts) ?: 'Her zaman aktif';
    }

    // Cache helpers
    public function getCacheKey(string $permission, Carbon $time): string
    {
        $timeKey = $time->format('Y-m-d_H'); // Hour-based caching
        return "time_permission_{$this->id}_{$permission}_{$timeKey}";
    }

    public static function clearCacheForRole(int $roleId): void
    {
        if (!CacheHelper::isEnabled()) {
            return;
        }
        
        $cacheTags = ["time_permissions_role_{$roleId}"];
        CacheHelper::flush($cacheTags);
    }

    // Boot method for cache management
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($timePermission) {
            self::clearCacheForRole($timePermission->role_id);
        });

        static::deleted(function ($timePermission) {
            self::clearCacheForRole($timePermission->role_id);
        });
    }
}
