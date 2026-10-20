<?php

namespace App\Core\UseCases\Events;

use App\Core\Data\Repositories\EventRepositoryInterface;
use Illuminate\Support\Collection;

class GetEventsByEmployee
{
    public function __construct(
        private EventRepositoryInterface $eventRepository
    ) {}

    public function execute(int $employeeId, int $page): Collection
    {
        $perPage = 10;
        $paginator = $this->eventRepository->paginateByEmployee($employeeId, $page, $perPage);

        return new Collection([
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $perPage,
            'events' => collect($paginator->items()),
        ]);
    }
}
