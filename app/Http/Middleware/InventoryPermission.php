<?php

namespace App\Http\Middleware;

use App\Services\Inventory\InventoryAccess;
use Closure;
use Illuminate\Http\Request;

class InventoryPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        app(InventoryAccess::class)->authorize($request->user(), $permission);

        return $next($request);
    }
}
