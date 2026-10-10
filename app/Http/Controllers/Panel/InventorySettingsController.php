<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\Panel\InventoryPanelView;
use Illuminate\Http\Request;

class InventorySettingsController extends Controller
{
    public function index(Request $request, InventoryPanelView $panel)
    {
        return $panel->render($request, $request->route('section') ?? 'catalogs', 'settings');
    }
}
