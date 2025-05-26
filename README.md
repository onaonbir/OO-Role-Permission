# 🔐 OORolePermission — Flexible Role & Permission System for Laravel

**OORolePermission** is a dynamic, model-based, and polymorphic role & permission management system for Laravel.  
It supports structured role definitions, JSON-based permissions, wildcard matching, scoped checks, and custom service-driven logic.

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
| `timestamps`             | Created/Updated at                       |

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
