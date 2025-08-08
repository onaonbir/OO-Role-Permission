<?php

namespace OnaOnbir\OORolePermission\Debug;

use OnaOnbir\OORolePermission\Support\CacheHelper;

class TimePermissionDebugger
{
    public static function debugUserPermissions($user, $permission)
    {
        echo "\n🔍 DEBUG: Time Permission Check for '{$permission}'\n";
        echo str_repeat('=', 60)."\n";

        // 0. Cache status kontrolü
        echo "💾 Cache Status:\n";
        echo '  Cache Enabled: '.(CacheHelper::isEnabled() ? 'YES' : 'NO')."\n";
        echo '  Cache Driver: '.config('cache.default')."\n";
        echo '  Supports Tagging: '.(CacheHelper::supportsTagging() ? 'YES' : 'NO')."\n\n";

        // 1. User roles kontrolü
        echo "👤 User Roles:\n";
        foreach ($user->roles as $role) {
            echo "  - Role: {$role->name}\n";
            echo "    Status: {$role->status}\n";
            echo '    Permissions: '.json_encode($role->permissions)."\n";
            echo '    Pivot expires_at: '.($role->pivot->expires_at ?: 'NULL')."\n";
            echo '    Pivot activated_at: '.($role->pivot->activated_at ?: 'NULL')."\n";
            echo '    Pivot additional_permissions: '.($role->pivot->additional_permissions ?: 'NULL')."\n";

            // Time constraints kontrolü
            if (method_exists($role, 'timePermissions')) {
                $timeConstraints = $role->timePermissions;
                echo '    Time Constraints: '.$timeConstraints->count()." found\n";
                foreach ($timeConstraints as $constraint) {
                    echo '      - Permission: '.($constraint->permission_key ?: 'ALL')."\n";
                    echo '        Schedule: '.$constraint->getReadableSchedule()."\n";
                    echo '        Valid now: '.($constraint->isValidAtTime(now()) ? 'YES' : 'NO')."\n";
                }
            }
            echo "\n";
        }

        // 2. Time permissions enabled kontrolü
        $timeEnabled = config('oo-role-permission.time_permissions.enabled', true);
        echo '⏰ Time Permissions Enabled: '.($timeEnabled ? 'YES' : 'NO')."\n";

        // 3. Permission check simulation
        echo "\n🧪 Permission Check Simulation:\n";

        // Basic permission check
        echo 'Basic hasPermission(): '.($user->hasPermission($permission) ? 'TRUE' : 'FALSE')."\n";

        // Time-aware check if available
        if (method_exists($user, 'hasPermissionAtTime')) {
            echo 'Time-aware check (now): '.($user->hasPermissionAtTime($permission) ? 'TRUE' : 'FALSE')."\n";
        }

        // 4. Wildcard analysis
        echo "\n🎯 Wildcard Analysis:\n";
        foreach ($user->roles as $role) {
            $permissions = $role->permissions ?: [];
            foreach ($permissions as $rolePermission) {
                if ($rolePermission === '*') {
                    echo "  ⚠️  FOUND WILDCARD '*' in role '{$role->name}' - matches EVERYTHING!\n";
                } elseif (str_ends_with($rolePermission, '.*')) {
                    $prefix = rtrim(substr($rolePermission, 0, -2), '.');
                    if (str_starts_with($permission, $prefix.'.')) {
                        echo "  ⚠️  FOUND WILDCARD '{$rolePermission}' in role '{$role->name}' - matches '{$permission}'!\n";
                    }
                } elseif ($rolePermission === $permission) {
                    echo "  ✅ FOUND EXACT MATCH '{$rolePermission}' in role '{$role->name}'\n";
                }
            }
        }

        // 5. Recommendation
        echo "\n💡 Recommendations:\n";
        $hasWildcard = false;
        foreach ($user->roles as $role) {
            $permissions = $role->permissions ?: [];
            if (in_array('*', $permissions) || in_array('admin.*', $permissions)) {
                $hasWildcard = true;
                break;
            }
        }

        if ($hasWildcard) {
            echo "  ⚠️  You have wildcard permissions! Create a test role without wildcards:\n";
            echo "     \$testRole = Role::create([\n";
            echo "         'name' => 'limited_test_role',\n";
            echo "         'permissions' => ['project.budget.approve'] // Specific permissions only\n";
            echo "     ]);\n";
            echo "     \$user->removeRole('admin');\n";
            echo "     \$user->assignTemporaryRole('limited_test_role', now()->addHour());\n";
        } else {
            echo "  ✅ No wildcard permissions found - time constraints should work correctly\n";
        }

        // 6. Cache debug
        echo "\n🔧 Cache Debug:\n";
        if (! CacheHelper::isEnabled()) {
            echo "  ℹ️  Cache is disabled - no caching issues\n";
        } else {
            echo '  Cache Store: '.config('cache.default')."\n";
            if (! CacheHelper::supportsTagging()) {
                echo "  ⚠️  Cache store doesn't support tagging - using fallback methods\n";
            } else {
                echo "  ✅ Cache store supports tagging\n";
            }
        }

        echo str_repeat('=', 60)."\n";
    }

    public static function testCacheConfiguration()
    {
        echo "\n🔧 Cache Configuration Test\n";
        echo str_repeat('=', 40)."\n";

        echo 'Cache Driver: '.config('cache.default')."\n";
        echo 'OO Cache Enabled: '.(config('oo-role-permission.cache.enabled') ? 'YES' : 'NO')."\n";

        try {
            echo "Testing basic cache...\n";
            \Illuminate\Support\Facades\Cache::put('oo_test', 'value', 60);
            $value = \Illuminate\Support\Facades\Cache::get('oo_test');
            echo 'Basic cache: '.($value === 'value' ? '✅ OK' : '❌ FAILED')."\n";

            echo "Testing cache tagging...\n";
            \Illuminate\Support\Facades\Cache::tags(['test_tag'])->put('oo_test_tag', 'tagged_value', 60);
            $taggedValue = \Illuminate\Support\Facades\Cache::tags(['test_tag'])->get('oo_test_tag');
            echo 'Tagged cache: '.($taggedValue === 'tagged_value' ? '✅ OK' : '❌ FAILED')."\n";

            \Illuminate\Support\Facades\Cache::tags(['test_tag'])->flush();
            echo "Tag flush: ✅ OK\n";

        } catch (\Exception $e) {
            echo '❌ Cache Error: '.$e->getMessage()."\n";
            echo "\n💡 Solution: Use 'array' or 'redis' cache driver for testing\n";
            echo "   In .env: CACHE_DRIVER=array\n";
        }

        // Cleanup
        \Illuminate\Support\Facades\Cache::forget('oo_test');

        echo str_repeat('=', 40)."\n";
    }
}
