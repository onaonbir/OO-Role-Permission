<?php

namespace OnaOnbir\OORolePermission\Middlewares;

use Closure;
use Illuminate\Http\Request;

class OORoleOrPermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $permissions = '')
    {

        $permissionsArray = explode('|', $permissions);
        if (! oo_rp()->hasRoleOrCan(['Super Admin'] ?? [], $permissionsArray ?? [])) {

            abort(403, 'Bu sayfaya erişim yetkiniz yok.');
        }

        return $next($request);
    }
}
