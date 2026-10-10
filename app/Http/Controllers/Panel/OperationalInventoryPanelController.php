<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\Panel\InventoryPanelView;
use Illuminate\Http\Request;

class OperationalInventoryPanelController extends Controller
{
    public function index(Request $request, InventoryPanelView $panel)
    {
        $section = $request->route('section') ?? ($request->route('event') ? 'preparation' : 'inventory');
        $area = match ($section) {
            'purchases' => 'purchases', 'preparation', 'events' => 'events', default => 'inventory'
        };

        return $panel->render($request, $section, $area);
    }
}
