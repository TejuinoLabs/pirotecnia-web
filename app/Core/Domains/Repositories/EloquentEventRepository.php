<?php

namespace App\Core\Domains\Repositories;

use App\Core\Data\Entities\Event;
use App\Core\Data\Repositories\EventRepositoryInterface;
use App\Core\Data\Services\EventService;
use Illuminate\Support\Collection;

class EloquentEventRepository implements EventRepositoryInterface
{
    public function __construct(
        private EventService $eventService
    ) {}

    public function paginate(int $page, int $perPage = 10): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->eventService->paginate($page, $perPage);
    }

    public function all(): Collection
    {
        return $this->eventService->all();
    }

    public function getEventsByEmployee(int $employeeId): Collection
    {
        return $this->eventService->getEventsByEmployee($employeeId);
    }

    public function paginateByEmployee(int $employeeId, int $page, int $perPage = 10): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->eventService->paginateByEmployee($employeeId, $page, $perPage);
    }

    public function find(int $eventId): ?Event
    {
        return $this->eventService->find($eventId);
    }

    public function create(Event $event): ?Event
    {
        return $this->eventService->create($event);
    }

    public function update(Event $event): ?Event
    {
        return $this->eventService->update($event);
    }

    public function delete(int $eventId): bool
    {
        return $this->eventService->delete($eventId);
    }

    public function searchEvents(string $searchTerm): Collection
    {
        return $this->eventService->searchEvents($searchTerm);
    }

    public function assignEventType($eventId, $eventTypeId): ?Event
    {
        return $this->eventService->assignEventType($eventId, $eventTypeId);
    }

    public function unassignEventType($eventId, $eventTypeId): ?Event
    {
        return $this->eventService->unassignEventType($eventId, $eventTypeId);
    }

    public function assignEmployeesToEvent(int $eventId, Collection $employees): ?Event
    {
        return $this->eventService->assignEmployeesToEvent($eventId, $employees);
    }

    public function unassignEmployeesFromEvent(int $eventId, Collection $employees): ?Event
    {
        return $this->eventService->unassignEmployeesFromEvent($eventId, $employees);
    }

    public function assignEquipmentsToEvent(int $eventId, Collection $equipment): ?Event
    {
        return $this->eventService->assignEquipmentsToEvent($eventId, $equipment);
    }

    public function unassignEquipmentsFromEvent(int $eventId, Collection $equipment): ?Event
    {
        return $this->eventService->unassignEquipmentsFromEvent($eventId, $equipment);
    }

    public function assignProductsToEvent(int $eventId, Collection $products): ?Event
    {
        return $this->eventService->assignProductsToEvent($eventId, $products);
    }

    public function unassignProductsFromEvent(int $eventId, Collection $products): ?Event
    {
        return $this->eventService->unassignProductsFromEvent($eventId, $products);
    }

    public function assignPackagesToEvent(int $eventId, Collection $packages): ?Event
    {
        return $this->eventService->assignPackagesToEvent($eventId, $packages);
    }

    public function unassignPackagesFromEvent(int $eventId, Collection $packages): ?Event
    {
        return $this->eventService->unassignPackagesFromEvent($eventId, $packages);
    }
}
