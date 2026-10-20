<?php

namespace App\Core\Data\Services;

use App\Core\Data\Entities\Event;
use App\Models\Event as ModelsEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EventService
{
    public const RELATIONS = ['typeEvent', 'employees', 'packages.products', 'packages.equipments', 'products', 'equipments'];

    public function paginate(int $page, int $perPage = 10): \Illuminate\Pagination\LengthAwarePaginator
    {
        return ModelsEvent::with(self::RELATIONS)->orderByDesc('date')->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', max(1, $page))
            ->through(fn ($event) => Event::fromEvent($event));
    }

    public function paginateByEmployee(int $employeeId, int $page, int $perPage = 10): \Illuminate\Pagination\LengthAwarePaginator
    {
        return ModelsEvent::with(self::RELATIONS)->whereHas('employees', fn ($query) => $query->where('employee_id', $employeeId))
            ->where('event_date', '>', Carbon::today()->subDays(3))
            ->orderByDesc('event_date')->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', max(1, $page))
            ->through(fn ($event) => Event::fromEvent($event));
    }

    public function all(): Collection
    {
        try {
            $eloquentEvents = ModelsEvent::with(self::RELATIONS)->get();
            $events = $eloquentEvents->map(function ($event) {
                return Event::fromEvent($event);
            });

            return $events;
        } catch (\Exception $e) {
            return new Collection;
        }
    }

    public function getEventsByEmployee(int $employeeId): Collection
    {
        try {
            $eloquentEvents = ModelsEvent::with(self::RELATIONS)->whereHas('employees', function ($query) use ($employeeId) {
                $query->where('employee_id', $employeeId);
            })->where('event_date', '>', Carbon::today()->subDays(3))->get();
            $events = $eloquentEvents->map(function ($event) {
                return Event::fromEvent($event);
            });

            return $events;
        } catch (\Exception $e) {
            return new Collection;
        }
    }

    public function find(int $eventId): ?Event
    {
        try {
            $eloquentEvent = ModelsEvent::with(self::RELATIONS)->findOrFail($eventId);
            $event = Event::fromEvent($eloquentEvent);

            return $event;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function create(Event $event): ?Event
    {
        try {
            $eloquentEvent = new ModelsEvent;
            $eloquentEvent->fill([
                'package_id' => $event->packages->first()->id ?? null,
                'date' => $event->date,
                'phone' => $event->phone,
                'client_name' => $event->client_name,
                'client_address' => $event->client_address,
                'event_address' => $event->event_address,
                'event_date' => $event->event_date,
                'discount' => $event->discount,
                'advance' => $event->advance,
                'travel_expenses' => $event->travel_expenses,
                'notes' => $event->notes,
                'contract_description' => $event->contract_description,
                'reminder_send_date' => $event->reminder_send_date,
                'reminder_sent' => $event->reminder_send,
            ]);
            $eloquentEvent->save();
            $event->id = $eloquentEvent->id;

            return $event;
        } catch (\Exception $e) {
            logger($e->getMessage());

            return null;
        }
    }

    public function update(Event $event): ?Event
    {
        try {
            $eloquentEvent = ModelsEvent::find($event->id);
            $eloquentEvent->fill([
                'date' => $event->date,
                'phone' => $event->phone,
                'client_name' => $event->client_name,
                'client_address' => $event->client_address,
                'event_address' => $event->event_address,
                'event_date' => $event->event_date,
                'discount' => $event->discount,
                'advance' => $event->advance,
                'travel_expenses' => $event->travel_expenses,
                'notes' => $event->notes,
                'contract_description' => $event->contract_description,
                'reminder_send_date' => $event->reminder_send_date,
                'reminder_sent' => $event->reminder_send,
            ]);
            $eloquentEvent->save();

            return $event;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function delete(int $eventId): bool
    {
        try {
            ModelsEvent::destroy($eventId);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function searchEvents(string $searchTerm)
    {
        try {
            // TODO: Implement searchEvents() method.
        } catch (\Exception $e) {
            return new Collection;
        }
    }

    public function assignEventType(int $eventId, int $eventTypeId): ?Event
    {
        try {
            $eventEloquent = ModelsEvent::find($eventId);
            $eventEloquent->typeEvent()->associate($eventTypeId);

            $event = Event::fromEvent($eventEloquent);

            return $event;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function unassignEventType(int $eventId, int $eventTypeId): ?Event
    {
        try {
            // TODO: Implement unassignEventType() method.
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function assignEmployeesToEvent(int $eventId, Collection $employees): ?Event
    {
        try {
            $eventEloquent = ModelsEvent::find($eventId);
            $eventEloquent->employees()->attach($employees->pluck('id'));
            $eventEloquent->load('employees');
            $event = Event::fromEvent($eventEloquent);

            return $event;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function unassignEmployeesFromEvent(int $eventId, Collection $employees): ?Event
    {
        try {
            $eventEloquent = ModelsEvent::find($eventId);
            $eventEloquent->employees()->detach($employees->pluck('id'));
            $eventEloquent->load('employees');
            $event = Event::fromEvent($eventEloquent);

            return $event;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function assignEquipmentsToEvent(int $eventId, Collection $equipment): ?Event
    {
        try {
            $eventEloquent = ModelsEvent::find($eventId);
            $eventEloquent->equipments()->attach($equipment->mapWithKeys(function ($equipment) {
                return [$equipment->id => ['quantity' => $equipment->quantity]];
            })->toArray());
            $eventEloquent->load(['equipments']);
            $event = Event::fromEvent($eventEloquent);

            return $event;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function unassignEquipmentsFromEvent(int $eventId, Collection $equipment): ?Event
    {
        try {
            $eventEloquent = ModelsEvent::find($eventId);
            $eventEloquent->equipments()->detach($equipment->pluck('id'));
            $eventEloquent->load('equipments');
            $event = Event::fromEvent($eventEloquent);

            return $event;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function assignProductsToEvent(int $eventId, Collection $products): ?Event
    {
        try {
            $eventEloquent = ModelsEvent::find($eventId);
            // Attach products with quantity to event
            $eventEloquent->products()->attach($products->mapWithKeys(function ($product) {
                return [$product->id => ['quantity' => $product->quantity]];
            })->toArray());
            $eventEloquent->load('products');
            $event = Event::fromEvent($eventEloquent);

            return $event;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function unassignProductsFromEvent(int $eventId, Collection $products): ?Event
    {
        try {
            $eventEloquent = ModelsEvent::find($eventId);
            $eventEloquent->products()->detach($products->pluck('id'));
            $eventEloquent->load('products');
            $event = Event::fromEvent($eventEloquent);

            return $event;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function assignPackagesToEvent(int $eventId, Collection $packages): ?Event
    {
        try {
            $eventEloquent = ModelsEvent::find($eventId);
            $eventEloquent->packages()->attach($packages->pluck('id'));
            $eventEloquent->load('packages');
            $event = Event::fromEvent($eventEloquent);

            return $event;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function unassignPackagesFromEvent(int $eventId, Collection $packages): ?Event
    {
        try {
            $eventEloquent = ModelsEvent::find($eventId);
            $eventEloquent->packages()->detach($packages->pluck('id'));
            $eventEloquent->load('packages');
            $event = Event::fromEvent($eventEloquent);

            return $event;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
