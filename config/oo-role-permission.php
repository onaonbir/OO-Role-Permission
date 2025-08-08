<?php

return [

    'tables' => [
        'roles' => 'oo_roles',
        'role_models' => 'oo_role_models',
    ],

    'models' => [
        'role' => \OnaOnbir\OORolePermission\Models\Role::class,
        'role_model' => \OnaOnbir\OORolePermission\Models\RoleModel::class,
    ],

    'cache' => [
        'enabled' => env('OO_ROLE_PERMISSION_CACHE', true),
        'ttl' => env('OO_ROLE_PERMISSION_CACHE_TTL', 3600),
        'key_prefix' => 'oo_rp:',
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
