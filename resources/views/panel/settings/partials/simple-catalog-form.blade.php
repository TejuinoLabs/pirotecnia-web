<div class="mx-auto mt-16 w-full max-w-5xl space-y-8 px-4 py-6 sm:p-8">
    <x-panel.settings.header :title="isset($record) ? 'Editar '.$singular : 'Nuevo '.$singular" :description="$catalogDescription" />
    <form action="{{ isset($record) ? route($routePrefix.'.update', $record->id) : route($routePrefix.'.store') }}" method="POST" class="rounded-xl bg-primary-800 p-6 shadow-2xl shadow-primary-900/20 sm:p-8">
        @csrf
        @isset($record) @method('PUT') @endisset
        <div class="grid gap-6">
            <label class="grid gap-2"><span class="text-sm font-semibold text-primary-200">Nombre</span>
                <input name="name" type="text" required maxlength="255" value="{{ old('name', $record->name ?? '') }}" class="w-full rounded-xl border border-primary-600/50 bg-primary-700/70 px-4 py-3 text-sm text-on-primary outline-none focus:border-secondary">
                @error('name')<span class="text-xs text-error">{{ $message }}</span>@enderror
            </label>
            <label class="grid gap-2"><span class="text-sm font-semibold text-primary-200">Descripción</span>
                <textarea name="description" required maxlength="255" rows="4" class="w-full rounded-xl border border-primary-600/50 bg-primary-700/70 px-4 py-3 text-sm text-on-primary outline-none focus:border-secondary">{{ old('description', $record->description ?? '') }}</textarea>
                @error('description')<span class="text-xs text-error">{{ $message }}</span>@enderror
            </label>
        </div>
        <div class="mt-8 flex flex-wrap gap-3">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-secondary px-6 py-3 font-bold text-on-secondary hover:bg-secondary-600"><span class="material-symbols-outlined" aria-hidden="true">save</span>Guardar</button>
            <a href="{{ route($indexRoute) }}" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-6 py-3 font-semibold text-on-primary hover:bg-primary-600">Cancelar</a>
        </div>
    </form>
</div>
