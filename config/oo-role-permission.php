<?php

return [

    'tables' => [
        'roles' => 'oo_roles',
        'role_models' => 'oo_role_models',
        'time_permissions' => 'oo_time_permissions',
    ],

    'models' => [
        'role' => \OnaOnbir\OORolePermission\Models\Role::class,
        'role_model' => \OnaOnbir\OORolePermission\Models\RoleModel::class,
        'time_permission' => \OnaOnbir\OORolePermission\Models\TimePermission::class,
    ],

    'cache' => [
        'enabled' => env('OO_ROLE_PERMISSION_CACHE', false), // Default false for testing
        'ttl' => env('OO_ROLE_PERMISSION_CACHE_TTL', 3600),
        'key_prefix' => 'oo_rp:',
        'supports_tagging' => null, // auto-detect
        'fallback_on_error' => true, // clear all cache if tagging fails
    ],

    'time_permissions' => [
        'enabled' => env('OO_ROLE_PERMISSION_TIME_ENABLED', true),
        'default_timezone' => env('OO_ROLE_PERMISSION_DEFAULT_TIMEZONE', 'UTC'),
        'auto_cleanup_expired' => env('OO_ROLE_PERMISSION_AUTO_CLEANUP', false),
        'cleanup_schedule' => '0 2 * * *', // Every night at 02:00
        'cache_ttl' => env('OO_ROLE_PERMISSION_TIME_CACHE_TTL', 1800), // 30 minutes
    ],

    'permissions' => [

        [
            'key' => 'access',
            'readable_name' => 'Panel Erişimleri',
            'description' => 'Sistemdeki panellere erişim izinlerini belirler.',
            'group' => 'Sistem İzinleri',
            'sub_permissions' => [
                [
                    'key' => 'access.panels.admin',
                    'readable_name' => 'Yönetim Paneli Erişimi',
                    'description' => 'Yönetim paneline giriş yapabilme yetkisini tanımlar.',
                    'group' => 'Sistem İzinleri',
                ],
                [
                    'key' => 'access.panels.user',
                    'readable_name' => 'Kullanıcı Paneli Erişimi',
                    'description' => 'Kullanıcı paneline giriş yapabilme yetkisini tanımlar.',
                    'group' => 'Sistem İzinleri',
                ],
            ],
        ],

    ],

];
