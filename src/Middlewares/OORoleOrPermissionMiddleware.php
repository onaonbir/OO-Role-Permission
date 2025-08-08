<?php

namespace OnaOnbir\OORolePermission\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class OORoleOrPermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param string $parameters Format: "roles=admin|editor;permissions=user.create|user.delete"
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $parameters = ''): mixed
    {
        if (!auth()->check()) {
            return $this->handleUnauthenticated($request);
        }

        [$roles, $permissions] = $this->parseParameters($parameters);

        // If no roles or permissions specified, allow access
        if (empty($roles) && empty($permissions)) {
            return $next($request);
        }

        // Check if user has required role or permission
        if (!oo_rp()->hasRoleOrCan($roles, $permissions)) {
            Log::warning('Access denied for user', [
                'user_id' => auth()->id(),
                'required_roles' => $roles,
                'required_permissions' => $permissions,
                'route' => $request->route()?->getName(),
                'url' => $request->url(),
            ]);

            return $this->handleUnauthorized($request);
        }

        return $next($request);
    }

    /**
     * Parse middleware parameters
     */
    protected function parseParameters(string $parameters): array
    {
        $roles = [];
        $permissions = [];

        if (empty($parameters)) {
            return [$roles, $permissions];
        }

        foreach (explode(';', $parameters) as $group) {
            $group = trim($group);

            if (str_starts_with($group, 'roles=')) {
                $value = trim(substr($group, 6));
                $roles = strlen($value) > 0 ? explode('|', $value) : [];
            }

            if (str_starts_with($group, 'permissions=')) {
                $value = trim(substr($group, 12));
                $permissions = strlen($value) > 0 ? explode('|', $value) : [];
            }
        }

        return [$roles, $permissions];
    }

    /**
     * Handle unauthenticated user
     */
    protected function handleUnauthenticated(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'error' => 'authentication_required'
            ], 401);
        }

        return redirect()->guest(route('login'));
    }

    /**
     * Handle unauthorized access
     */
    protected function handleUnauthorized(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Bu işlem için yetkiniz yok.',
                'error' => 'insufficient_permissions'
            ], 403);
        }

        abort(403, 'Bu sayfaya erişim yetkiniz yok.');
    }
}
