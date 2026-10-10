<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryAccess;
use App\Services\Mobile\MobileProtocolUpgradeService;
use Illuminate\Http\Request;

class MobileProtocolUpgradeController extends Controller
{
    public function show(Request $request, int $event, MobileProtocolUpgradeService $service)
    {
        app(InventoryAccess::class)->authorize($request->user(), 'mobile.protocol.manage');

        return view('panel.settings.protocol-upgrade', ['plan' => $service->plan($event)]);
    }

    public function store(Request $request, int $event, MobileProtocolUpgradeService $service)
    {
        app(InventoryAccess::class)->authorize($request->user(), 'mobile.protocol.manage');
        $data = $request->validate(['expected_version' => 'required|integer|min:0', 'reason' => 'required|string|max:2000']);
        $service->apply($event, $request->user(), (int) $data['expected_version'], $data['reason']);

        return redirect()->route('settings.protocol-upgrade', $event)->with('status', 'Protocolo actualizado y auditado. El técnico debe actualizar su copia; las operaciones de versiones anteriores se conservarán para revisión.');
    }
}
