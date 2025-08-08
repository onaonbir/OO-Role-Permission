# 🔐 OORolePermission — Production-Ready Role & Permission System for Laravel

**OORolePermission** is a high-performance, dynamic, model-based, and polymorphic role & permission management system for Laravel.  
It supports structured role definitions, JSON-based permissions, wildcard matching, scoped checks, **time-based constraints**, caching, and custom service-driven logic.

## ✨ **v1.3.1 Features & Bug Fixes**

- 🚀 **High Performance**: Built-in caching and N+1 query prevention
- ⏰ **Polymorphic Time Permissions**: Apply time constraints to both Roles and Users
- 🎯 **Individual User Constraints**: User-level time restrictions without creating new roles
- 🔒 **Production Ready**: Comprehensive error handling and logging
- 📊 **Database Optimized**: Smart indexes and constraints
- 🎯 **Type Safe**: Strict type hints throughout
- 🔄 **Backward Compatible**: No breaking changes from v1.x.x
- 🐛 **Fixed**: Cache-related null pointer exceptions
- 🛡️ **Enhanced**: Robust error handling for timezone and cache operations

### 🔧 **v1.3.1 Bug Fixes**

1. **Fixed Cache Clear Error**: Resolved `clearCacheForRole(): Argument #1 ($roleId) must be of type int, null given` error
2. **Polymorphic Relationship Fix**: Updated boot method to handle polymorphic relationships correctly
3. **Enhanced Error Handling**: Added try-catch blocks for timezone operations
4. **Cache Safety**: Improved cache operations with fallback mechanisms
5. **Validation Improvements**: Added input validation for day of week, permissions, and time operations
6. **Type Safety**: Added nullable type hints where appropriate

---

## 🔧 Installation

1. Install the package via Composer:

```bash
composer require onaonbir/oo-role-permission
````

2. Publish and run migrations:

```bash
php artisan vendor:publish --tag=oo-role-permission-migrations
php artisan migrate
```

3. (Optional) Publish the config file:

```bash
php artisan vendor:publish --tag=oo-role-permission-config
```

4. (Optional) Configure caching in your `.env`:

```bash
# Enable/disable permission caching (default: true)
OO_ROLE_PERMISSION_CACHE=true

# Cache TTL in seconds (default: 3600 = 1 hour)
OO_ROLE_PERMISSION_CACHE_TTL=3600

# Time-based permissions (default: true)
OO_ROLE_PERMISSION_TIME_ENABLED=true

# Default timezone (default: UTC)
OO_ROLE_PERMISSION_DEFAULT_TIMEZONE=Europe/Istanbul

# Time permission cache TTL (default: 1800 = 30 minutes)
OO_ROLE_PERMISSION_TIME_CACHE_TTL=1800
```

---

## ⏰ **Time-based Permissions**

### **User-Level Time Constraints (NEW!)**
```php
// Individual user constraints without creating new roles!

// Geçici contractor access
$contractor->addTemporaryUserPermission(
    ['project.xyz.*', 'documents.read'],
    now()->addWeeks(4), // 4 hafta süreyle
    ['description' => 'Temporary contractor access for Project XYZ']
);

// Stajyer için özel çalışma saatleri
$intern->addUserTimeConstraint(
    ['documents.read', 'meetings.attend'],
    [
        'start_time' => '10:00:00',
        'end_time' => '16:00:00',
        'days_of_week' => [1, 2, 3, 4, 5],
        'description' => 'Intern working hours'
    ]
);

// Emergency admin access (2 saatlik)
$seniorDev->addTemporaryUserPermission(
    ['server.admin', 'database.backup'],
    now()->addHours(2),
    ['description' => 'Emergency server maintenance']
);

// Kullanım - Priority system!
$contractor->hasPermission('project.xyz.edit'); // User constraint öncelikli
$intern->hasPermission('documents.read'); // Çalışma saatleri kontrol edilir
```

### **Role-Level Time Constraints**
```php
// Tüm moderatörler için iş saatleri
$moderatorRole->addTimeConstraint([
    'additional_permissions' => null, // Tüm rol izinleri
    'start_time' => '09:00:00',
    'end_time' => '17:00:00',
    'days_of_week' => [1, 2, 3, 4, 5],
    'timezone' => 'Europe/Istanbul'
]);

// Sadece belirli izinler için zaman kısıtı
$adminRole->addPermissionTimeConstraint(
    ['user.delete', 'system.shutdown'],
    [
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'description' => 'Critical operations only during business hours'
    ]
);
```

### **Priority System Example**
```php
// User hem moderator rolü hem de individual constraint'i varsa:
$user->assignRole('moderator'); // Role: moderate.* permissions
$user->addUserTimeConstraint(['moderate.posts'], [
    'start_time' => '08:00:00',
    'end_time' => '20:00:00' // User constraint daha geniş saat
]);

// Priority: User constraint wins!
$user->hasPermission('moderate.posts'); // 08:00-20:00 arası true
$user->hasPermission('moderate.users'); // Role constraint (09:00-17:00)

// Debug priority
$details = $user->hasPermissionWithPriority('moderate.posts');
/*
[
    'has_permission' => true,
    'source' => 'User Time Constraint',
    'level' => 'user',
    'time_valid' => true
]
*/
```

### **Temporary Roles**
```php
// 1 haftalık geçici moderaötör yetkisi
$user->assignTemporaryRole('moderator', now()->addWeek());

// 3 ay süreyle project lead + extra permissions
$user->assignTemporaryRole('project_lead', now()->addMonths(3), [
    'additional_permissions' => ['project.budget.approve']
]);

// Kontrol
$user->hasRole('moderator'); // 1 hafta sonra otomatik false
```

### **Weekend-only Access**
```php
$weekendRole = Role::create([
    'name' => 'weekend_support',
    'permissions' => ['support.tickets']
]);

$weekendRole->timePermissions()->create([
    'days_of_week' => [6, 7], // Cumartesi, Pazar
    'timezone' => 'Europe/Istanbul'
]);

$user->assignRole('weekend_support');
// Sadece hafta sonu true döner
```

### **Seasonal Permissions**
```php
// Yılbaşı kampanyası yöneticisi
$campaignRole = Role::create([
    'name' => 'holiday_campaign_manager',
    'permissions' => ['campaign.manage', 'discount.create']
]);

$campaignRole->timePermissions()->create([
    'start_date' => '2025-12-01',
    'end_date' => '2025-12-31',
    'timezone' => 'Europe/Istanbul'
]);
```

### **Advanced Time Checks**
```php
// Belirli zamandaki izinleri kontrol et
$user->hasPermissionAtTime('admin.access', Carbon::parse('2025-12-25 15:00'));

// Gelecekteki izinleri öngör
$user->willHavePermissionAt('admin.access', now()->addDays(7));

// Sonraki değişiklik zamanını öğren
$nextChange = $user->getNextPermissionChange('admin.access');

// Kullanıcının tüm zaman kısıtlarını görüntüle
$constraints = $user->getTimeConstraints();

// Süresi dolan rolleri temizle
$expiredCount = oo_rp()->cleanupExpiredRoles();
```

---

## ⚡ **Performance Features**

### **Automatic Caching**
Permission checks are automatically cached to improve performance:

```php
// First call: queries database
$user->hasPermission('post.create');

// Subsequent calls: served from cache
$user->hasPermission('post.create'); // ⚡ Cached!
```

### **N+1 Query Prevention**
Relationships are automatically eager loaded:

```php
// Automatically prevents N+1 queries
foreach ($users as $user) {
    $user->hasRole('admin'); // No additional queries!
}
```

### **Database Optimizations**
- Smart indexes on frequently queried columns
- Unique constraints to prevent duplicate data
- Optimized foreign key relationships

---

## 🗄️ Table Structure

### `oo_roles`

| Column          | Description                        |
| --------------- | ---------------------------------- |
| `id`            | Primary key                        |
| `name`          | Internal role key                  |
| `readable_name` | Display name (optional)            |
| `description`   | Role description (optional)        |
| `permissions`   | JSON array of permission keys      |
| `type`          | Enum: `system_default`, `editable` |
| `status`        | Enum: `active`, `draft`, `passive` |
| `attributes`    | Additional metadata (optional)     |
| `timestamps`    | Created/Updated at                 |

### `oo_role_models`

| Column                   | Description                              |
| ------------------------ | ---------------------------------------- |
| `id`                     | Primary key                              |
| `role_id`                | Foreign key to `oo_roles`                |
| `model_type`             | Target model (polymorphic)               |
| `model_id`               | UUID or integer, depending on your setup |
| `additional_permissions` | JSON array of custom scoped permissions  |
| `expires_at`             | **NEW**: Role assignment expiration      |
| `activated_at`           | **NEW**: Role assignment activation      |
| `timezone`               | **NEW**: User timezone for time checks   |
| `timestamps`             | Created/Updated at                       |

### `oo_time_permissions` **NEW**

| Column          | Description                           |
| --------------- | ------------------------------------- |
| `id`            | Primary key                           |
| `role_id`       | Foreign key to `oo_roles`             |
| `permission_key`| Specific permission (NULL = all)      |
| `start_time`    | Daily start time (e.g., 09:00:00)    |
| `end_time`      | Daily end time (e.g., 17:00:00)      |
| `start_date`    | Date range start (e.g., 2025-01-01)  |
| `end_date`      | Date range end (e.g., 2025-12-31)    |
| `timezone`      | Timezone (e.g., Europe/Istanbul)     |
| `days_of_week`  | JSON array [1,2,3,4,5] (Mon-Fri)     |
| `is_active`     | Boolean: constraint active            |
| `description`   | Human-readable description            |
| `timestamps`    | Created/Updated at                    |

---

## 🚀 Usage

### 1. Add the `HasRolesAndPermissions` Trait

Attach it to any model (typically `User`):

```php
use OnaOnbir\OORolePermission\Traits\HasRolesAndPermissions;

class User extends Model
{
    use HasRolesAndPermissions;
}
```

Now you can use:

```php
$user->assignRole('admin');
$user->hasRole('admin');
$user->hasPermission('post.edit');
```

---

### 2. Use the `OORolePermission` Service

```php
oo_rp()->can('user.manage'); // Auth::user()->hasPermission('user.manage')
oo_rp()->hasRole('editor');
oo_rp()->hasRoleOrCan(['editor'], ['post.create']);
```

You can also pass a user manually:

```php
oo_rp()->canWUser($user, 'access.panels');
```

---

## 🧠 Permission System

* Permissions are defined as simple string keys (e.g., `post.create`, `user.ban`)
* Supports nested permission groups with `sub_permissions`
* Supports wildcard checking:

    * `'post.*'` matches `'post.create'`, `'post.delete'`, etc.
* Merges `permissions` from role + `additional_permissions` from pivot

---

## 🧩 Defining Permissions (Config-based)

In your config file (`oo-role-permission.php`), define permissions like:

```php
'permissions' => [
    [
        'key' => 'access',
        'readable_name' => 'Panel Access',
        'description' => 'Controls access to various system panels.',
        'group' => 'System',
        'sub_permissions' => [
            [
                'key' => 'access.panel.admin',
                'readable_name' => 'Admin Panel Access',
                'description' => 'Allows access to the administration panel.',
                'group' => 'System',
            ],
            [
                'key' => 'access.panel.user',
                'readable_name' => 'User Panel Access',
                'description' => 'Allows access to the user dashboard.',
                'group' => 'System',
            ],
        ],
    ],
    [
        'key' => 'post',
        'readable_name' => 'Post Management',
        'description' => 'Grants post-related permissions.',
        'group' => 'Content',
        'sub_permissions' => [
            [
                'key' => 'post.create',
                'readable_name' => 'Create Post',
                'description' => 'Allows users to create new posts.',
                'group' => 'Content',
            ],
            [
                'key' => 'post.delete',
                'readable_name' => 'Delete Post',
                'description' => 'Allows users to delete posts.',
                'group' => 'Content',
            ],
        ],
    ],
];
```

---

## 🧪 Role Setup Example

```php
use OnaOnbir\OORolePermission\Models\Role;

$role = Role::create([
    'name' => 'admin',
    'readable_name' => 'Administrator',
    'permissions' => ['*'],
    'type' => 'system_default',
    'status' => 'active',
]);

$user->assignRole('admin');
```

---

## 🔄 Runtime Permission Evaluation

```php
$user->hasPermission('user.ban');
$user->hasPermission(['user.ban', 'user.kick']);
$user->getReadablePermissionName('post.create'); // "Create Post"
```

---


## 🧩 Middleware Integration

OORolePermission includes a powerful middleware named `oo_rp` that allows you to restrict access to routes based on roles, permissions, or both.

### 🔧 Setup

The middleware is automatically registered by the package via:

```php
// Inside the service provider
$router->aliasMiddleware('oo_rp', \OnaOnbir\OORolePermission\Middlewares\OORoleOrPermissionMiddleware::class);
```

You don't need to manually register it in `Kernel.php`.

---

### 🚦 Usage Examples

#### ✅ Allow access if the user has one of the listed permissions:

```php
Route::middleware('oo_rp:permissions=user.create|user.delete')->group(function () {
    // Only accessible if user has either permission
});
```

#### ✅ Allow access if the user has one of the listed roles:

```php
Route::middleware('oo_rp:roles=Admin|Editor')->group(function () {
    // Only accessible if user has either role
});
```

#### ✅ Combine roles and permissions:

```php
Route::middleware('oo_rp:roles=Admin;permissions=user.create|user.delete')->group(function () {
    // Access granted if user has Admin role or one of the permissions
});
```

---

### 📌 Default Role Support

If desired, the middleware can be extended to include a default fallback role like `Super Admin`.
You can hardcode or make it configurable via the package config:

```php
$roles = array_merge(['Super Admin'], $roles); // optional enhancement
```




---





## 🧱 Enum Support

* `status` uses `OORoleStatus` enum (`active`, `draft`, `passive`)
* `type` uses `OORoleType` enum (`system_default`, `editable`)

---

## 📚 Example Use Cases

* Admin vs user panel access control
* Feature-level permission toggles
* Multi-role user setups with override permissions
* Workspace-based or tenant-scoped permissions

---

## 🛠️ License

MIT © OnaOnbir
Crafted with precision and clean structure.
