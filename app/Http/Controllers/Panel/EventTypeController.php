<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\EventType;
use Illuminate\Http\Request;

class EventTypeController extends Controller
{
    //

    public function index()
    {
        $parentItemActive = 8;
        $itemActive = 4;
        $eventTypes = EventType::all();

        return view('panel.settings.event_types.index', compact('itemActive', 'parentItemActive', 'eventTypes'));
    }

    public function create()
    {
        return view('panel.settings.event_types.create');
    }

    public function store(Request $request)
    {
        $request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:255']]);
        $eventType = new EventType;
        $eventType->name = $request->name;
        $eventType->description = $request->description;
        $eventType->save();

        return redirect()->route('settings.event_types.index');
    }

    public function show($id)
    {
        $record = EventType::findOrFail($id);

        return view('panel.settings.event_types.show', compact('record'));
    }

    public function edit($id)
    {
        $record = EventType::findOrFail($id);

        return view('panel.settings.event_types.edit', compact('record'));
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:255']]);
        $record = EventType::findOrFail($id);
        $record->name = $data['name'];
        $record->description = $data['description'];
        $record->save();

        return redirect()->route('event_types.show', $record->id)->with('status', 'Registro actualizado correctamente.');
    }

    public function destroy($id)
    {
        $record = EventType::findOrFail($id);
        foreach (['events', 'packages'] as $table) {
            if (\Illuminate\Support\Facades\DB::table($table)->where('event_type_id', $record->id)->exists()) {
                return redirect()->route('settings.event_types.index')->with('error', 'No se puede eliminar un registro que está en uso.');
            }
        }
        $record->delete();

        return redirect()->route('settings.event_types.index')->with('status', 'Registro eliminado correctamente.');
    }
}
