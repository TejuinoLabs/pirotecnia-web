<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ClientTypeController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\EmployeeController;
use App\Http\Controllers\API\EquipmentController;
use App\Http\Controllers\API\EventController;
use App\Http\Controllers\API\InventoryController;
use App\Http\Controllers\API\PackageController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\PurchaseController;
use App\Http\Controllers\API\SaleController;
use App\Http\Controllers\API\StallController;
use App\Http\Controllers\API\UserController;
use Illuminate\Support\Facades\Route;

require base_path('app/Presentation/Http/Routes/api.php');

// Auth
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/recovery', [AuthController::class, 'recovery']);

Route::middleware(['auth:api'])->group(function () {
    // Firebase
    Route::post('/fcm-token', [AuthController::class, 'saveFCMToken']);

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);

    // Dashboard
    /**
     * Deprecated this function moved V2
     */
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Profile
    Route::get('/profile', [UserController::class, 'profile']);
    Route::post('/change-password', [UserController::class, 'changePassword']);
    Route::post('/update-profile', [UserController::class, 'updateProfile']);

    // Settings - Packages
    Route::get('/packages', [PackageController::class, 'index']);
    Route::get('/package/{id}', [PackageController::class, 'show']);
    Route::post('/package', [PackageController::class, 'store']);
    Route::put('/package/{id}', [PackageController::class, 'update']);
    Route::delete('/package/{id}', [PackageController::class, 'destroy']);

    // Settings - Products
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/product/{id}', [ProductController::class, 'show']);
    Route::post('/product', [ProductController::class, 'store']);
    Route::put('/product/{id}', [ProductController::class, 'update']);
    Route::delete('/product/{id}', [ProductController::class, 'destroy']);

    // Settings - Equipment
    Route::get('/equipments', [EquipmentController::class, 'index']);
    Route::get('/equipment/{id}', [EquipmentController::class, 'show']);
    Route::post('/equipment', [EquipmentController::class, 'store']);
    Route::put('/equipment/{id}', [EquipmentController::class, 'update']);
    Route::delete('/equipment/{id}', [EquipmentController::class, 'destroy']);

    // Settings - Client type
    Route::get('/client-types', [ClientTypeController::class, 'index']);
    Route::get('/client-type/{id}', [ClientTypeController::class, 'show']);
    Route::post('/client-type', [ClientTypeController::class, 'store']);
    Route::put('/client-type/{id}', [ClientTypeController::class, 'update']);
    Route::delete('/client-type/{id}', [ClientTypeController::class, 'destroy']);

    // Employee
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employee/{id}', [EmployeeController::class, 'show']);
    Route::post('/employee', [EmployeeController::class, 'store']);
    Route::put('/employee/{id}', [EmployeeController::class, 'update']);
    Route::delete('/employee/{id}', [EmployeeController::class, 'destroy']);

    // Event
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/event/{id}', [EventController::class, 'show']);
    Route::post('/event', [EventController::class, 'store']);
    Route::put('/event/{id}', [EventController::class, 'update']);
    Route::delete('/event/{id}', [EventController::class, 'destroy']);
    Route::get('/employee-events', [EventController::class, 'getEventsByEmployee']);

    // Inventory
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::get('/inventory/{id}', [InventoryController::class, 'show'])->whereNumber('id');
    Route::post('/inventory', [InventoryController::class, 'store']);
    Route::put('/inventory/{id}', [InventoryController::class, 'update']);
    Route::delete('/inventory/{id}', [InventoryController::class, 'destroy']);

    // Purchase
    Route::get('/purchases', [PurchaseController::class, 'index']);
    Route::get('/purchase/{id}', [PurchaseController::class, 'show']);
    Route::post('/purchase', [PurchaseController::class, 'store']);
    Route::put('/purchase/{id}', [PurchaseController::class, 'update']);
    Route::delete('/purchase/{id}', [PurchaseController::class, 'destroy']);

    // Sale
    Route::get('/sales', [SaleController::class, 'index']);
    Route::get('/sale/{id}', [SaleController::class, 'show']);
    Route::post('/sale', [SaleController::class, 'store']);
    Route::put('/sale/{id}', [SaleController::class, 'update']);
    Route::delete('/sale/{id}', [SaleController::class, 'destroy']);

    // Stall
    Route::get('/stalls', [StallController::class, 'index']);
    Route::get('/stall/{id}', [StallController::class, 'show']);
    Route::post('/stall', [StallController::class, 'store']);
    Route::put('/stall/{id}', [StallController::class, 'update']);
    Route::delete('/stall/{id}', [StallController::class, 'destroy']);

});

$registerInventoryRoutes = require __DIR__.'/inventory_api.php';
$registerInventoryRoutes('auth:api');

require __DIR__.'/mobile_api.php';
