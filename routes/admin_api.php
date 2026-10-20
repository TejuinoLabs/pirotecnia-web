<?php

use App\Http\Controllers\API\AdminCatalogController;
use App\Http\Controllers\API\AdminIntegrationController;
use App\Http\Controllers\API\AdminMediaController;
use App\Http\Middleware\AdminPermission;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', AdminPermission::class, 'throttle:120,1'])->group(function () {
    Route::delete('events/{id}', [AdminIntegrationController::class, 'destroyEvent'])->whereNumber('id');
    Route::prefix('admin')->group(function () {
        Route::post('media', [AdminMediaController::class, 'store']);
        Route::post('packages', [AdminIntegrationController::class, 'savePackage']);
        Route::put('packages/{id}', [AdminIntegrationController::class, 'savePackage'])->whereNumber('id');
        Route::put('events/{id}', [AdminIntegrationController::class, 'updateEvent'])->whereNumber('id');
        Route::put('events/{id}/employees', [AdminIntegrationController::class, 'employees'])->whereNumber('id');
        foreach (['confirm', 'close', 'reopen', 'cancel'] as $transition) {
            Route::post('events/{id}/'.$transition, [AdminIntegrationController::class, 'transition'])->whereNumber('id')->defaults('transition', $transition);
        }
        foreach (['event-types', 'experience-levels', 'product-roles'] as $catalog) {
            Route::get($catalog, [AdminCatalogController::class, 'index'])->defaults('admin_catalog', $catalog);
            if ($catalog === 'product-roles') {
                continue;
            }
            Route::get($catalog.'/{id}', [AdminCatalogController::class, 'show'])->whereNumber('id')->defaults('admin_catalog', $catalog);
            Route::post($catalog, [AdminCatalogController::class, 'store'])->defaults('admin_catalog', $catalog);
            Route::put($catalog.'/{id}', [AdminCatalogController::class, 'update'])->whereNumber('id')->defaults('admin_catalog', $catalog);
            Route::delete($catalog.'/{id}', [AdminCatalogController::class, 'destroy'])->whereNumber('id')->defaults('admin_catalog', $catalog);
        }
    });
});
