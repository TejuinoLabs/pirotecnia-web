<div class="mx-auto mt-16 w-full max-w-5xl space-y-8 px-4 py-6 sm:p-8">
    <x-panel.settings.header :title="$record->name" :description="$catalogDescription" />
    @if (session('status'))<p role="status" class="rounded-xl border border-accent/20 bg-accent/10 px-5 py-4 text-accent">{{ session('status') }}</p>@endif
    <section class="rounded-xl bg-primary-800 p-8 shadow-2xl shadow-primary-900/20">
        <h2 class="text-xs font-bold uppercase tracking-widest text-primary-200">Descripción</h2>
        <p class="mt-4 whitespace-pre-line text-on-primary">{{ $record->description ?: 'Sin descripción' }}</p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route($routePrefix.'.edit', $record->id) }}" class="inline-flex items-center gap-2 rounded-xl bg-secondary px-6 py-3 font-bold text-on-secondary hover:bg-secondary-600"><span class="material-symbols-outlined" aria-hidden="true">edit</span>Editar</a>
            <a href="{{ route($indexRoute) }}" class="rounded-xl bg-primary-700 px-6 py-3 font-semibold text-on-primary hover:bg-primary-600">Volver al catálogo</a>
        </div>
    </section>
</div>
