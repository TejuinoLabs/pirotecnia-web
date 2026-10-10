<?php

namespace App\Core\UseCases\Events;

use App\Core\Data\Entities\Event;
use App\Core\UseCases\Inventories\CheckLowInventoryByProducts;
use App\Core\UseCases\Reminders\SendReminderToAdmin;
use App\Core\UseCases\Reminders\SendReminderToEmployee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateEvent
{
    public function __construct(
        private StoreEvent $storeEvent,
        private AssignPackagesToEvent $assignPackagesToEvent,
        private AssignEquipmentsToEvent $assignEquipmentsToEvent,
        private AssignProductsToEvent $assignProductsToEvent,
        private AssignEmployeesToEvent $assignEmployeesToEvent,
        private SendReminderToEmployee $sendReminderToEmployee,
        private SendReminderToAdmin $sendReminderToAdmin,
        private CheckLowInventoryByProducts $checkLowInventoryByProducts
    ) {}

    public function execute(Event $event, bool $validateInventory = true): ?Event
    {
        try {
            return DB::transaction(function () use ($event, $validateInventory) {
                $event = $this->storeEvent->execute($event);
                $event = $event->withPackages($this->assignPackagesToEvent->execute($event)->packages ?? new Collection);
                $event = $event->withProducts($this->assignProductsToEvent->execute($event)->products);
                $event = $event->withEquipments($this->assignEquipmentsToEvent->execute($event)->equipments);
                $event = $event->withEmployees($this->assignEmployeesToEvent->execute($event)->employees);

                if ($validateInventory) {
                    $lowInventory = $this->checkLowInventoryByProducts->execute($event->products);
                    $event->lowInventoryProducts = $lowInventory;
                }

                return $event;
            });
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
