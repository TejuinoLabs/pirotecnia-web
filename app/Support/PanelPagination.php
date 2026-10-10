<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PanelPagination
{
    public static function collection(Collection $rows, string $pageName = 'page'): LengthAwarePaginator
    {
        $page = max(1, LengthAwarePaginator::resolveCurrentPage($pageName));

        return (new LengthAwarePaginator($rows->forPage($page, 15)->values(), $rows->count(), 15, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]))->withQueryString();
    }
}
