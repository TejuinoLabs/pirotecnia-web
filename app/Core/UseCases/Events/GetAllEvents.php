<?php

namespace App\Core\UseCases\Events;

use App\Core\Data\Repositories\EventRepositoryInterface;
use Illuminate\Support\Collection;

class GetAllEvents
{
    public function __construct(
        private EventRepositoryInterface $eventRepository
    ) {}

    public function execute(int $page): Collection
    {
        $perPage = 10;
        $paginator = $this->eventRepository->paginate($page, $perPage);

        return new Collection([
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $perPage,
            'events' => collect($paginator->items()),
        ]);
    }
}
