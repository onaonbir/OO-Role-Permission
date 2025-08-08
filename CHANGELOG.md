# Changelog

All notable changes to `oo-role-permission` will be documented in this file.

## [1.3.0] - 2025-08-08

### 🚀 Added
- **Enhanced Time Permissions**: Complete refactor to `additional_permissions` approach
- **Multiple Permission Constraints**: Apply time constraints to multiple permissions at once
- **Wildcard Support in Time Constraints**: Full wildcard support in time permission definitions
- **Consistent Permission Handling**: Unified permission system across all features

### 🔧 Enhanced
- **Database Schema**: `permission_key` → `additional_permissions` (JSON array)
- **Improved Flexibility**: Multiple permissions per time constraint
- **Better Wildcard Logic**: Consistent wildcard matching across time and basic permissions
- **Performance**: Optimized permission checking with array-based constraints

### 📚 New Usage Patterns
```php
// Multiple specific permissions
$role->timePermissions()->create([
    'additional_permissions' => ['user.delete', 'admin.settings'],
    'start_time' => '09:00:00',
    'end_time' => '17:00:00'
]);

// Wildcard permissions
$role->timePermissions()->create([
    'additional_permissions' => ['admin.*', 'user.manage.*'],
    'days_of_week' => [1, 2, 3, 4, 5]
]);

// All role permissions (default)
$role->timePermissions()->create([
    'additional_permissions' => null, // or empty array
    'start_time' => '09:00:00',
    'end_time' => '17:00:00'
]);
```

### 🔄 Migration
- **Automatic Migration**: `permission_key` column replaced with `additional_permissions`
- **Backward Compatibility**: Existing time constraints converted automatically
- **Database Optimization**: JSON-based permission storage for better flexibility

### 📊 Improvements
- **Unified Permission Logic**: Same wildcard matching in all contexts
- **Better Performance**: Array-based permission checking
- **Enhanced Debug Tools**: Updated debug classes for new structure
- **Comprehensive Documentation**: Updated examples and usage patterns

---

## [1.2.0] - 2025-08-08

### 🚀 Added
- **Time-based Permissions**: Complete time constraints system
  - Daily time ranges (e.g., 09:00-17:00)
  - Weekly patterns (e.g., Monday-Friday only)
  - Date ranges (e.g., seasonal permissions)
  - Temporary role assignments with expiration
  - Timezone support for global applications
- **New Models**: TimePermission model with comprehensive validation
- **Enhanced Role Model**: Time-aware methods and relationships
- **Time Permission Validator**: Dedicated service for time validation
- **Cache Integration**: Hour-based caching for time-sensitive permissions
- **New Migration**: Database support for time constraints
- **Configuration**: Time permissions configuration section

### 🔧 Enhanced
- **Service Integration**: OORolePermission service now time-aware
- **Traits**: HasRolesAndPermissions enhanced with time methods
- **Performance**: Smart caching with automatic invalidation
- **Database**: Optimized indexes for time-based queries
- **User Experience**: Intuitive API for time constraints

### 📚 New Methods
- `assignTemporaryRole()` - Assign roles with expiration
- `hasRoleAtTime()` - Check role at specific time
- `hasPermissionAtTime()` - Check permission at specific time
- `getActiveRoles()` - Get roles active at given time
- `getTimeConstraints()` - Get user's time constraints
- `willHavePermissionAt()` - Check future permissions
- `getNextPermissionChange()` - Find next permission change
- `cleanupExpiredRoles()` - Remove expired role assignments

### 🎯 Use Cases
- Business hours restrictions (banking, healthcare)
- Temporary access (contractors, interns)
- Seasonal permissions (holiday campaigns)
- Shift-based access (night/day shifts)
- Weekend-only roles (support staff)

### ⚙️ Configuration
```php
'time_permissions' => [
    'enabled' => env('OO_ROLE_PERMISSION_TIME_ENABLED', true),
    'default_timezone' => env('OO_ROLE_PERMISSION_DEFAULT_TIMEZONE', 'UTC'),
    'auto_cleanup_expired' => env('OO_ROLE_PERMISSION_AUTO_CLEANUP', true),
    'cache_ttl' => env('OO_ROLE_PERMISSION_TIME_CACHE_TTL', 1800),
],
```

### 🔄 Migration
- Fully backward compatible
- Optional feature activation
- Automatic fallback to basic validation
- No breaking changes

---

## [1.1.0] - 2025-08-08

### 🚀 Added
- **Caching System**: Added comprehensive caching layer for permission checks
- **Performance Optimizations**: Eager loading to prevent N+1 query problems
- **Service Registration**: Singleton service binding for better performance
- **Enhanced Middleware**: Better error handling and logging for unauthorized access
- **Model Scopes**: Added useful scopes for Role model (active, byType, withPermissions, byName)
- **Helper Methods**: Added convenience methods to Role model (isActive, isSystemDefault, hasPermission)
- **Database Indexes**: Added performance indexes to migrations
- **Cache Configuration**: New cache settings in config file

### 🔧 Improved
- **Type Safety**: Added strict type hints throughout the codebase
- **Method Separation**: Refactored large methods into smaller, focused ones
- **Code Quality**: Improved error handling and validation
- **Middleware**: Enhanced with proper authentication checks and JSON response support
- **Documentation**: Added comprehensive inline documentation

### 🐛 Fixed
- **Config Duplicate Key**: Fixed duplicate `access.panels` key in permissions config
- **Permission Checks**: Improved wildcard permission matching logic
- **Migration Constraints**: Added unique constraints to prevent duplicate role assignments
- **Cache Invalidation**: Automatic cache clearing when roles are modified

### ⚠️ Deprecated
- `hasSubPermission()` method is deprecated, use `hasPermission()` instead

### 📦 Dependencies
- Added `illuminate/cache` dependency
- Added `illuminate/database` dependency

### 🔄 Breaking Changes
- None! This version maintains full backward compatibility

---

## [1.0.2] - Previous Release
- Initial stable release with basic role and permission functionality
