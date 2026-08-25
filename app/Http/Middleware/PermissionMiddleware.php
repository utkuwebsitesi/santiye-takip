<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $permissions = array_filter(explode('|', $permission));
        abort_unless($request->user()?->hasAnyPermission($permissions), 403);

        return $next($request);
    }
}
