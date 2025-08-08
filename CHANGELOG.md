# Changelog

All notable changes to `oo-role-permission` will be documented in this file.

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
