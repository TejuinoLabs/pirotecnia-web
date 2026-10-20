<?php 

namespace App\Infraestructure\Persistence\Eloquent\Repositories;

use App\Domain\Employees\Dashboard\Contracts\DashboardRepository;
use App\Infraestructure\Persistence\Eloquent\Mappers\EventMapper;
use App\Models\Event as ModelsEvent;

class EloquentDashboard implements DashboardRepository {
    public function dashboard(): array {
        $events = ModelsEvent::with(['typeEvent', 'employees.experienceLevel', 'packages.experienceLevel', 'packages.products.productRole', 'packages.equipments', 'products.productRole', 'equipments'])->get();
        
        return array_map(fn($event) => EventMapper::fromModel($event),$events->all());
    }
}