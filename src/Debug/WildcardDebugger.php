<?php

namespace OnaOnbir\OORolePermission\Debug;

class WildcardDebugger
{
    public static function testWildcardLogic($user, $permission)
    {
        echo "\n🎯 WILDCARD DEBUG for: '{$permission}'\n";
        echo str_repeat('=', 50)."\n";

        foreach ($user->roles as $role) {
            echo "Role: {$role->name}\n";
            echo 'Role Permissions: '.json_encode($role->permissions)."\n";

            $rolePermissions = $role->permissions ?? [];

            foreach ($rolePermissions as $rolePermission) {
                echo "\n  Testing role permission: '{$rolePermission}'\n";

                // 1. Direct match
                $directMatch = ($rolePermission === $permission);
                echo '    Direct match: '.($directMatch ? 'TRUE' : 'FALSE')."\n";

                // 2. Wildcard check
                if (str_ends_with($rolePermission, '.*')) {
                    $wildcard = rtrim(substr($rolePermission, 0, -2), '.');
                    echo "    Wildcard prefix: '{$wildcard}'\n";
                    echo "    Permission starts with '{$wildcard}.': ".(str_starts_with($permission, $wildcard.'.') ? 'TRUE' : 'FALSE')."\n";

                    $wildcardMatch = str_starts_with($permission, $wildcard.'.');
                    echo '    Wildcard match result: '.($wildcardMatch ? 'TRUE' : 'FALSE')."\n";
                } else {
                    echo "    Not a wildcard permission\n";
                }
            }

            // Manual test using the same logic
            echo "\n  🧪 Manual Logic Test:\n";
            $result = self::manualCheckPermission($rolePermissions, $permission);
            echo '    Manual check result: '.($result ? 'TRUE' : 'FALSE')."\n";

            // Test using OO service
            echo "\n  🔧 OO Service Test:\n";
            $ooResult = oo_rp()->checkModelPermissions($user, [$permission]);
            echo "    OO service result: ERROR - checkModelPermissions is private\n";

            echo "\n".str_repeat('-', 30)."\n";
        }

        // Time permissions configuration check
        echo "\n⏰ Time Configuration:\n";
        echo 'Time permissions enabled: '.(config('oo-role-permission.time_permissions.enabled') ? 'YES' : 'NO')."\n";

        if (config('oo-role-permission.time_permissions.enabled')) {
            echo "❗ Time permissions are ENABLED - this might affect results!\n";
            echo "💡 Try disabling: config(['oo-role-permission.time_permissions.enabled' => false])\n";
        }

        echo str_repeat('=', 50)."\n";
    }

    private static function manualCheckPermission(array $permissions, string $permission): bool
    {
        // Direct match
        if (in_array($permission, $permissions, true)) {
            echo "    ✅ Direct match found\n";

            return true;
        }

        // Wildcard match
        foreach ($permissions as $perm) {
            if (str_ends_with($perm, '.*')) {
                $wildcard = rtrim(substr($perm, 0, -2), '.');
                echo "    🔍 Checking wildcard: '{$wildcard}' against '{$permission}'\n";
                if (str_starts_with($permission, $wildcard.'.')) {
                    echo "    ✅ Wildcard match found: '{$perm}' matches '{$permission}'\n";

                    return true;
                }
            }
        }

        echo "    ❌ No match found\n";

        return false;
    }
}
