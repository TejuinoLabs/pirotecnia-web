<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ExperienceLevel;
use Illuminate\Http\Request;

class ExperienceLevelController extends Controller
{
    //

    public function index()
    {
        $experienceLevels = ExperienceLevel::all();
        $parentItemActive = 8;
        $itemActive = 5;

        return view('panel.settings.experience_levels.index', compact('parentItemActive', 'itemActive', 'experienceLevels'));
    }

    public function create()
    {
        return view('panel.settings.experience_levels.create');
    }

    public function store(Request $request)
    {
        $request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:255']]);
        $experienceLevel = new ExperienceLevel;
        $experienceLevel->name = $request->name;
        $experienceLevel->description = $request->description;
        $experienceLevel->save();

        return redirect()->route('settings.experience-levels.index');
    }

    public function show($id)
    {
        $record = ExperienceLevel::findOrFail($id);

        return view('panel.settings.experience_levels.show', compact('record'));
    }

    public function edit($id)
    {
        $record = ExperienceLevel::findOrFail($id);

        return view('panel.settings.experience_levels.edit', compact('record'));
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:255']]);
        $record = ExperienceLevel::findOrFail($id);
        $record->name = $data['name'];
        $record->description = $data['description'];
        $record->save();

        return redirect()->route('experience-levels.show', $record->id)->with('status', 'Registro actualizado correctamente.');
    }

    public function destroy($id)
    {
        $record = ExperienceLevel::findOrFail($id);
        foreach (['employees', 'packages'] as $table) {
            if (\Illuminate\Support\Facades\DB::table($table)->where('experience_level_id', $record->id)->exists()) {
                return redirect()->route('settings.experience-levels.index')->with('error', 'No se puede eliminar un registro que está en uso.');
            }
        }
        $record->delete();

        return redirect()->route('settings.experience-levels.index')->with('status', 'Registro eliminado correctamente.');
    }
}
