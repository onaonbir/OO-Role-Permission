<?php

namespace OnaOnbir\OORolePermission\Middlewares;

use Closure;
use Illuminate\Http\Request;

class OORoleOrPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $parameters = ''): mixed
    {
        [$roles, $permissions] = $this->parseParameters($parameters);


        if (! oo_rp()->hasRoleOrCan($roles, $permissions)) {
            abort(403, 'Bu sayfaya erişim yetkiniz yok.');
        }

        return $next($request);
    }

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
                $value = trim(substr($group, 11));
                $permissions = strlen($value) > 0 ? explode('|', $value) : [];
            }
        }

        return [$roles, $permissions];
    }
}
