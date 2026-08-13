<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InstallationOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(file_exists(storage_path('app/installed.lock')), 404);

        return $next($request);
    }
}
