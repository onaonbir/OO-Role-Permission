<?php

namespace OnaOnbir\OORolePermission\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        return $this->morphedByMany(User::class, 'model', 'oo_role_models', 'role_id', 'model_id')
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

    public function scopeWithTimeConstraints(Builder $query): Builder
    {
        return $query->whereHas('timePermissions');
    }

    public function scopeActiveAtTime(Builder $query, Carbon $time = null): Builder
    {
        $time = $time ?: now();

        return $query->active()
            ->where(function ($q) use ($time) {
                // Roles without time constraints are always active
                $q->whereDoesntHave('timePermissions')
                  // OR roles with valid time constraints
                  ->orWhereHas('timePermissions', function ($timeQuery) use ($time) {
                      $timeQuery->active()->validAt($time);
                  });
            });
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

    // Time-based helper methods
    public function hasTimeConstraints(): bool
    {
        return $this->timePermissions()->active()->exists();
    }

    public function getActiveTimePermissions(Carbon $time = null): Collection
    {
        $time = $time ?: now();

        return $this->timePermissions()
            ->active()
            ->validAt($time)
            ->get();
    }

    public function isPermissionValidAtTime(string $permission, Carbon $time = null): bool
    {
        $time = $time ?: now();

        // If role doesn't have basic permission, return false
        if (!$this->hasPermission($permission)) {
            return false;
        }

        // If no time constraints, permission is always valid
        if (!$this->hasTimeConstraints()) {
            return true;
        }

        // Check if any time permission allows this permission at this time
        $applicableTimePermissions = $this->timePermissions()
            ->active()
            ->forPermission($permission)
            ->get();

        // If no specific time constraints for this permission, check role-level constraints
        if ($applicableTimePermissions->isEmpty()) {
            $applicableTimePermissions = $this->timePermissions()
                ->active()
                ->whereNull('permission_key') // Role-level constraints
                ->get();
        }

        // If still no constraints, allow (shouldn't happen if hasTimeConstraints() is true)
        if ($applicableTimePermissions->isEmpty()) {
            return true;
        }

        // Check if any applicable constraint allows access at this time
        foreach ($applicableTimePermissions as $timePermission) {
            if ($timePermission->isValidAtTime($time)) {
                return true;
            }
        }

        return false;
    }

    public function isActiveAtTime(Carbon $time = null): bool
    {
        $time = $time ?: now();

        // Must be active status
        if (!$this->isActive()) {
            return false;
        }

        // If no time constraints, role is always active
        if (!$this->hasTimeConstraints()) {
            return true;
        }

        // Check if any time constraint allows access at this time
        return $this->getActiveTimePermissions($time)->isNotEmpty();
    }

    public function getTimeConstraintsSummary(): array
    {
        if (!$this->hasTimeConstraints()) {
            return ['type' => 'unrestricted', 'description' => 'Her zaman aktif'];
        }

        $constraints = $this->timePermissions()->active()->get();
        $summaries = [];

        foreach ($constraints as $constraint) {
            $summaries[] = [
                'permission' => $constraint->permission_key ?: 'Tüm izinler',
                'schedule' => $constraint->getReadableSchedule(),
                'timezone' => $constraint->timezone
            ];
        }

        return [
            'type' => 'time_restricted',
            'constraints' => $summaries
        ];
    }

    // Temporary role assignment helpers
    public function createTemporaryAssignment(Model $model, Carbon $expiresAt, array $additionalPermissions = [], string $timezone = 'UTC'): void
    {
        $this->users()->attach($model, [
            'additional_permissions' => json_encode($additionalPermissions),
            'expires_at' => $expiresAt,
            'activated_at' => now(),
            'timezone' => $timezone
        ]);
    }

    public function getExpiredAssignments(): Collection
    {
        return $this->users()
            ->wherePivot('expires_at', '<', now())
            ->wherePivotNotNull('expires_at')
            ->get();
    }

    public function cleanupExpiredAssignments(): int
    {
        $expiredCount = $this->users()
            ->wherePivot('expires_at', '<', now())
            ->wherePivotNotNull('expires_at')
            ->count();

        $this->users()
            ->wherePivot('expires_at', '<', now())
            ->wherePivotNotNull('expires_at')
            ->detach();

        return $expiredCount;
    }
}
