<?php

namespace App\Infraestructure\Persistence\Eloquent\Repositories;

use App\Domain\Employees\Dashboard\Entities\Event;
use App\Domain\Employees\Events\Contracts\EventRepository;
use App\Infraestructure\Persistence\Eloquent\Mappers\EventMapper;
use App\Models\Event as ModelsEvent;
use App\Models\EventType;

class EloquentEvents implements EventRepository
{
    public function eventDetail(int $id): Event
    {
        $event = ModelsEvent::with(['typeEvent', 'employees.experienceLevel', 'packages.experienceLevel', 'packages.products.productRole', 'packages.equipments', 'products.productRole', 'equipments'])->find($id);

        return EventMapper::fromModel($event);
    }

    public function saveEvent(array $request): Event
    {

        $eventModel = new ModelsEvent([
            'date' => $request['date'],
            'phone' => $request['phone'],
            'client_name' => $request['clientName'],
            'client_address' => $request['clientAddress'],
            'event_address' => $request['eventAddress'],
            'event_date' => $request['eventDate'],
            'discount' => $request['discount'],
            'advance' => $request['advance'],
            'travel_expenses' => $request['travelExpenses'],
            'notes' => $request['notes'],
            'price' => $request['price'],
            'reminder_send_date' => null,
            'reminder_send' => false,
        ]);

        $eventModel->typeEvent()->associate(EventType::find($request['eventTypeId']));
        $eventModel->save();

        $packages = collect($request['packages'])
            ->mapWithKeys(fn ($package) => [$package['id'] => ['quantity' => 1, 'price' => $package['price']]]);
        $products = collect($request['products'])
            ->mapWithKeys(fn ($product) => [$product['id'] => ['quantity' => $product['quantity'], 'price' => $product['price']]]);
        $equipments = collect($request['equipments'])
            ->mapWithKeys(fn ($equipment) => [$equipment['id'] => ['quantity' => $equipment['quantity']]]);

        $eventModel->packages()->attach($packages);
        $eventModel->products()->attach($products);
        $eventModel->equipments()->attach($equipments);

        $productsInPackage = $eventModel->packages
            ->flatMap(function ($package) {
                return $package->products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'quantity' => $product->pivot->quantity,
                        'price' => $product->pivot->price,
                    ];
                });
            })
            ->groupBy('id')
            ->map(function ($product) {
                if ($product->duplicates()->count() > 0) {
                    $quantity = $product->sum('quantity');
                    $product = $product->unique('id')->values()->first();
                    $product['quantity'] = $quantity;
                } else {
                    $product = $product->values()->first();
                }
                unset($product['id']);

                return $product;
            })->all();

        $equipmentInPackage = $eventModel->packages
            ->flatMap(function ($package) {
                return $package->equipments->map(function ($equipment) {
                    return [
                        'id' => $equipment->id,
                        'quantity' => $equipment->pivot->quantity,
                    ];
                });
            })
            ->groupBy('id')
            ->map(function ($equipment) {
                if ($equipment->duplicates()->count() > 0) {
                    $quantity = $equipment->sum('quantity');
                    $equipment = $equipment->unique('id')->values()->first();
                    $equipment['quantity'] = $quantity;
                } else {
                    $equipment = $equipment->values()->first();
                }
                unset($equipment['id']);

                return $equipment;
            })->all();
        $eventModel->products()->attach($productsInPackage);
        $eventModel->equipments()->attach($equipmentInPackage);

        return EventMapper::fromModel($eventModel);
    }
}
