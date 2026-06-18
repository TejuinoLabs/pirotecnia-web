<?php

namespace App\Providers;

use App\Core\Data\Repositories\EmployeeRepositoryInterface;
use App\Core\Data\Repositories\EquipmentRepositoryInterface;
use App\Core\Data\Repositories\EventRepositoryInterface;
use App\Core\Data\Repositories\InventoryRepositoryInterface;
use App\Core\Data\Repositories\PackageRepositoryInterface;
use App\Core\Data\Repositories\ProductRepositoryInterface;
use App\Core\Data\Repositories\PurchaseRepositoryInterface;
use App\Core\Domains\Repositories\EloquentEmployeeRepository;
use App\Core\Domains\Repositories\EloquentEquipmentRepository;
use App\Core\Domains\Repositories\EloquentEventRepository;
use App\Core\Domains\Repositories\EloquentInventoryRepository;
use App\Core\Domains\Repositories\EloquentPackageRepository;
use App\Core\Domains\Repositories\EloquentProductRepository;
use App\Core\Domains\Repositories\EloquentPurchaseRepository;
use App\Domain\Employees\Dashboard\Contracts\DashboardRepository;
use App\Domain\Employees\Events\Contracts\EventRepository;
use App\Infraestructure\Persistence\Eloquent\Repositories\EloquentDashboard;
use App\Infraestructure\Persistence\Eloquent\Repositories\EloquentEvents;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->bind(EventRepositoryInterface::class, EloquentEventRepository::class);
        $this->app->bind(EmployeeRepositoryInterface::class, EloquentEmployeeRepository::class);
        $this->app->bind(EquipmentRepositoryInterface::class, EloquentEquipmentRepository::class);
        $this->app->bind(InventoryRepositoryInterface::class, EloquentInventoryRepository::class);
        $this->app->bind(PackageRepositoryInterface::class, EloquentPackageRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
        $this->app->bind(PurchaseRepositoryInterface::class, EloquentPurchaseRepository::class);
        $this->app->bind(DashboardRepository::class, EloquentDashboard::class);
        $this->app->bind(EventRepository::class, EloquentEvents::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
