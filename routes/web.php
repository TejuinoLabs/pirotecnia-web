<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Chatbot\ChatbotWaController;
use App\Http\Controllers\Panel\ClientTypeController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\EquipmentController;
use App\Http\Controllers\Panel\EventController;
use App\Http\Controllers\Panel\EventTypeController;
use App\Http\Controllers\Panel\ExperienceLevelController;
use App\Http\Controllers\Panel\InventoryController;
use App\Http\Controllers\Panel\MenuController;
use App\Http\Controllers\Panel\PackageController;
use App\Http\Controllers\Panel\ProductController;
use App\Http\Controllers\Panel\ProductRoleController;
use App\Http\Controllers\Panel\PurchaseController;
use App\Http\Controllers\Panel\SaleController;
use App\Http\Controllers\Panel\SearchController;
use App\Http\Controllers\Panel\UserController;
use App\Presentation\Http\Controllers\Web\SettingsController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing-page');

// Auth
Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login', [AuthController::class, 'loginSubmit'])->name('login.submit');
Route::get('/register', [AuthController::class, 'register'])->name('register');
Route::post('/register', [AuthController::class, 'registerSubmit'])->name('register.submit');

Route::get('eventDetails/{id}', [EventController::class, 'showByWhatsapp'])->name('showByWhatsapp');

Route::get('catalogo', [ProductController::class, 'catalogo']);

Route::get('legal/privacy_policy', function () {
    return view('legal.privacy_policy');
})->name('legal.privacy_policy');

Route::get('legal/terms_of_service', function () {
    return view('legal.terms_of_service');
})->name('legal.terms_of_service');
Route::get('/webhook', [ChatbotWaController::class, 'webhook']);
Route::post('/webhook', [ChatbotWaController::class, 'recibe']);

Route::post('/test/event', [EventController::class, 'test'])->name('test.event');

Route::middleware('auth')->group(function () {
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/home', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/panel/search', [SearchController::class, 'index'])->name('search.global');
    Route::get('/panel/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/panel/employees', [UserController::class, 'indexEmployees'])->name('employees.index');
    Route::get('/panel/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/panel/inventories', [\App\Http\Controllers\Panel\OperationalInventoryPanelController::class, 'index'])->name('inventories.index');
    Route::get('/panel/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('/panel/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/settings', [SettingsController::class, 'indexSettings'])->name('settings.index');
    Route::get('/settings/events/{event}/protocol-upgrade', [\App\Http\Controllers\Panel\MobileProtocolUpgradeController::class, 'show'])->whereNumber('event')->name('settings.protocol-upgrade');
    Route::post('/settings/events/{event}/protocol-upgrade', [\App\Http\Controllers\Panel\MobileProtocolUpgradeController::class, 'store'])->whereNumber('event')->name('settings.protocol-upgrade.store');
    Route::get('/settings/setup', [\App\Http\Controllers\Panel\SetupWizardController::class, 'index'])->name('settings.setup');
    Route::post('/settings/setup', [\App\Http\Controllers\Panel\SetupWizardController::class, 'store'])->name('settings.setup.store');
    Route::get('/settings/menu', [MenuController::class, 'index'])->name('settings.menu.index');
    Route::get('/settings/packages', [PackageController::class, 'index'])->name('settings.packages.index');
    Route::get('/settings/products', [\App\Http\Controllers\Panel\InventorySettingsController::class, 'index'])->defaults('section', 'products')->name('settings.products.index');
    Route::get('/settings/equipments', [equipmentController::class, 'index'])->name('settings.equipments.index');
    Route::get('/settings/client-types', [ClientTypeController::class, 'index'])->name('settings.client-types.index');
    Route::get('/settings/product-roles', [ProductRoleController::class, 'index'])->name('settings.product-roles.index');
    Route::get('/settings/event-types', [EventTypeController::class, 'index'])->name('settings.event_types.index');
    Route::get('/settings/experience-levels', [ExperienceLevelController::class, 'index'])->name('settings.experience-levels.index');

    // Users
    Route::get('panel/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('panel/users', [UserController::class, 'store'])->name('users.store');
    Route::get('panel/users/{id}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('panel/users/{id}', [UserController::class, 'update'])->name('users.update');
    Route::delete('panel/users/{id}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('panel/users/{id}', [UserController::class, 'show'])->name('users.show');

    // Employees
    Route::get('panel/employees/create', [UserController::class, 'createEmployee'])->name('employees.create');
    Route::post('panel/employees', [UserController::class, 'storeEmployee'])->name('employees.store');
    Route::get('panel/employees/{id}/edit', [UserController::class, 'editEmployee'])->name('employees.edit');
    Route::put('panel/employees/{id}', [UserController::class, 'updateEmployee'])->name('employees.update');
    Route::delete('panel/employees/{id}', [UserController::class, 'destroyEmployee'])->name('employees.destroy');
    Route::get('panel/employees/{id}', [UserController::class, 'showEmployee'])->name('employees.show');

    Route::get('/panel/events/preparations', [\App\Http\Controllers\Panel\OperationalInventoryPanelController::class, 'index'])->defaults('section', 'events')->name('inventory.preparations');

    // Events
    Route::get('panel/events/create', [EventController::class, 'create'])->name('events.create');
    Route::get('panel/events/{id}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::delete('panel/events/{id}', [EventController::class, 'destroy'])->name('events.destroy');
    Route::get('panel/events/{id}', [EventController::class, 'show'])->name('events.show');
    Route::get('eventDetailsContract/{id}', [EventController::class, 'showContract'])->name('showContract');

    // Send reminder
    Route::post('panel/events/{id}/reminder', [EventController::class, 'reminder'])->name('events.send-reminder');

    // Inventory
    Route::get('panel/inventory/create', [InventoryController::class, 'create'])->name('inventory.create');
    Route::post('panel/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::get('panel/inventory/{id}/edit', [InventoryController::class, 'edit'])->name('inventory.edit');
    Route::put('panel/inventory/{id}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::delete('panel/inventory/{id}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
    Route::get('panel/inventory/{id}', [InventoryController::class, 'show'])->name('inventory.show');

    // Purchases
    Route::get('panel/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('panel/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::get('panel/purchases/{id}/edit', [PurchaseController::class, 'edit'])->name('purchases.edit');
    Route::put('panel/purchases/{id}', [PurchaseController::class, 'update'])->name('purchases.update');
    Route::delete('panel/purchases/{id}', [PurchaseController::class, 'destroy'])->name('purchases.destroy');
    Route::get('panel/purchases/{id}', [PurchaseController::class, 'show'])->name('purchases.show');

    // Sales
    Route::get('panel/sales/create', [SaleController::class, 'create'])->name('sales.create');
    Route::post('panel/sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('panel/sales/{id}/edit', [SaleController::class, 'edit'])->name('sales.edit');
    Route::put('panel/sales/{id}', [SaleController::class, 'update'])->name('sales.update');
    Route::delete('panel/sales/{id}', [SaleController::class, 'destroy'])->name('sales.destroy');
    Route::get('panel/sales/{id}', [SaleController::class, 'show'])->name('sales.show');

    // Menu
    Route::get('settings/menu/create', [MenuController::class, 'create'])->name('menu.create');
    Route::post('settings/menu', [MenuController::class, 'store'])->name('menu.store');
    Route::get('settings/menu/{id}/edit', [MenuController::class, 'edit'])->name('menu.edit');
    Route::put('settings/menu/{id}', [MenuController::class, 'update'])->name('menu.update');
    Route::delete('settings/menu/{id}', [MenuController::class, 'destroy'])->name('menu.destroy');
    Route::get('settings/menu/{id}', [MenuController::class, 'show'])->name('menu.show');
    // Activate or deactivate menu item
    Route::put('settings/menu/{id}/active', [MenuController::class, 'active'])->name('menu.active');

    // Packages
    Route::get('settings/packages/create', [PackageController::class, 'create'])->name('packages.create');
    Route::post('settings/packages', [PackageController::class, 'store'])->name('packages.store');
    Route::get('settings/packages/{id}/edit', [PackageController::class, 'edit'])->name('packages.edit');
    Route::put('settings/packages/{id}', [PackageController::class, 'update'])->name('packages.update');
    Route::delete('settings/packages/{id}', [PackageController::class, 'destroy'])->name('packages.destroy');
    Route::get('settings/packages/{id}', [PackageController::class, 'show'])->name('packages.show');

    // Import products
    Route::get('settings/products/import', [ProductController::class, 'import'])->name('products.import');
    Route::post('settings/products/import', [ProductController::class, 'importSubmit'])->name('products.import.submit');
    // Products
    Route::get('settings/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('settings/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('settings/products/{id}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('settings/products/{id}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('settings/products/{id}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::get('settings/products/{id}', [ProductController::class, 'show'])->name('products.show');

    // equipments
    Route::get('settings/equipments/create', [equipmentController::class, 'create'])->name('equipments.create');
    Route::post('settings/equipments', [equipmentController::class, 'store'])->name('equipments.store');
    Route::get('settings/equipments/{id}/edit', [equipmentController::class, 'edit'])->name('equipments.edit');
    Route::put('settings/equipments/{id}', [equipmentController::class, 'update'])->name('equipments.update');
    Route::delete('settings/equipments/{id}', [equipmentController::class, 'destroy'])->name('equipments.destroy');
    Route::get('settings/equipments/{id}', [equipmentController::class, 'show'])->name('equipments.show');

    // ClientTypes
    Route::get('settings/client-types/create', [ClientTypeController::class, 'create'])->name('client-types.create');
    Route::post('settings/client-types', [ClientTypeController::class, 'store'])->name('client-types.store');
    Route::get('settings/client-types/{id}/edit', [ClientTypeController::class, 'edit'])->name('client-types.edit');
    Route::put('settings/client-types/{id}', [ClientTypeController::class, 'update'])->name('client-types.update');
    Route::delete('settings/client-types/{id}', [ClientTypeController::class, 'destroy'])->name('client-types.destroy');
    Route::get('settings/client-types/{id}', [ClientTypeController::class, 'show'])->name('client-types.show');

    // ProductRoles
    Route::get('settings/product-roles/create', [ProductRoleController::class, 'create'])->name('product-roles.create');
    Route::post('settings/product-roles', [ProductRoleController::class, 'store'])->name('product-roles.store');
    Route::get('settings/product-roles/{id}/edit', [ProductRoleController::class, 'edit'])->name('product-roles.edit');
    Route::put('settings/product-roles/{id}', [ProductRoleController::class, 'update'])->name('product-roles.update');
    Route::delete('settings/product-roles/{id}', [ProductRoleController::class, 'destroy'])->name('product-roles.destroy');
    Route::get('settings/product-roles/{id}', [ProductRoleController::class, 'show'])->name('product-roles.show');

    // EventTypes
    Route::get('settings/event_types/create', [EventTypeController::class, 'create'])->name('event_types.create');
    Route::post('settings/event_types', [EventTypeController::class, 'store'])->name('event_types.store');
    Route::get('settings/event_types/{id}/edit', [EventTypeController::class, 'edit'])->name('event_types.edit');
    Route::put('settings/event_types/{id}', [EventTypeController::class, 'update'])->name('event_types.update');
    Route::delete('settings/event_types/{id}', [EventTypeController::class, 'destroy'])->name('event_types.destroy');
    Route::get('settings/event_types/{id}', [EventTypeController::class, 'show'])->name('event_types.show');

    // ExperienceLevels
    Route::get('settings/experience-levels/create', [ExperienceLevelController::class, 'create'])->name('experience-levels.create');
    Route::post('settings/experience-levels', [ExperienceLevelController::class, 'store'])->name('experience-levels.store');
    Route::get('settings/experience-levels/{id}/edit', [ExperienceLevelController::class, 'edit'])->name('experience-levels.edit');
    Route::put('settings/experience-levels/{id}', [ExperienceLevelController::class, 'update'])->name('experience-levels.update');
    Route::delete('settings/experience-levels/{id}', [ExperienceLevelController::class, 'destroy'])->name('experience-levels.destroy');
    Route::get('settings/experience-levels/{id}', [ExperienceLevelController::class, 'show'])->name('experience-levels.show');

});

Route::middleware('auth')->group(function () {
    Route::get('/inventory', fn () => redirect()->route('inventories.index'))->name('inventory.operations');
    Route::get('/inventory/kardex', [\App\Http\Controllers\Panel\OperationalInventoryPanelController::class, 'index'])->defaults('section', 'kardex')->name('inventory.kardex');
    Route::get('/inventory/purchases', fn () => redirect()->route('purchases.index'))->name('inventory.purchases');
    Route::get('/inventory/catalogs', fn () => redirect()->route('settings.index'))->name('inventory.catalogs');
    Route::get('/inventory/preparations', fn () => redirect()->route('inventory.preparations'));
    Route::get('/inventory/requirements', fn () => redirect()->route('settings.package-requirements'))->name('inventory.requirements');
    Route::get('/events/{event}/preparation', [\App\Http\Controllers\Panel\OperationalInventoryPanelController::class, 'index'])->name('events.preparation');
    Route::get('/settings/inventory-rules', [\App\Http\Controllers\Panel\InventorySettingsController::class, 'index'])->defaults('section', 'rules')->name('inventory.rules');
    Route::get('/settings/package-requirements', [\App\Http\Controllers\Panel\InventorySettingsController::class, 'index'])->defaults('section', 'requirements')->name('settings.package-requirements');
    Route::get('/settings/packages/{package}/requirements', [\App\Http\Controllers\Panel\InventorySettingsController::class, 'index'])->whereNumber('package')->defaults('section', 'requirements')->name('settings.packages.requirements');
    foreach (['categories', 'attributes', 'effects', 'colors', 'locations', 'profiles'] as $catalog) {
        Route::get('/settings/inventory/'.$catalog, [\App\Http\Controllers\Panel\InventorySettingsController::class, 'index'])->defaults('catalog', $catalog)->defaults('section', 'catalogs')->name('settings.inventory.'.$catalog);
    }
    Route::get('/inventory/audit', [\App\Http\Controllers\Panel\OperationalInventoryPanelController::class, 'index'])->defaults('section', 'audit')->name('inventory.audit');
});
$registerInventoryRoutes = require __DIR__.'/inventory_api.php';
$registerInventoryRoutes('auth', 'inventory/operations');
