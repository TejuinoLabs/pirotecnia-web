<?php

namespace App\Http\Middleware;

use App\Services\Admin\AdminAccess;
use Closure;
use Illuminate\Http\Request;

class AdminPermission
{
    public function handle(Request $request, Closure $next)
    {
        app(AdminAccess::class)->authorize($request->user());

        return $next($request);
    }
}
