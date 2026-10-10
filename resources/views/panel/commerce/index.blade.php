@extends('templates.adminlte')

@section('main-content')
<div id="inventory-panel" class="mx-auto mt-16 w-full max-w-[1680px] space-y-8 px-4 py-6 sm:p-8"
    data-today="{{ now('America/Mexico_City')->toDateString() }}" data-area="commerce" data-section="{{ $section }}" data-catalog="" data-package="" data-event=""
    data-base="{{ url('/inventory/operations') }}" data-action="{{ $recordAction }}" data-record="{{ $recordId }}">
    <script type="application/json" id="inventory-config">{!! json_encode(['catalogs' => $catalogs, 'permissions' => $permissions], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <input type="hidden" id="inventory-csrf" value="{{ csrf_token() }}">
    <header class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
        <div class="space-y-2">
            <nav class="mb-3 flex gap-2 text-xs font-semibold uppercase tracking-widest text-primary-200" aria-label="Ruta de navegación"><a href="{{ route('dashboard') }}" class="hover:text-secondary">Inicio</a><span>/</span><a href="{{ route($section.'.index') }}" class="text-on-primary">{{ $section === 'sales' ? 'Ventas' : 'Compras' }}</a></nav>
            <h1 class="font-manrope text-4xl font-extrabold tracking-tighter text-on-primary">{{ $section === 'sales' ? 'Ventas' : 'Compras y recepciones' }}</h1>
            <p class="max-w-3xl font-medium text-primary-200">{{ $section === 'sales' ? 'Vende productos y materiales desde las existencias disponibles del almacén.' : 'Registra compras y recibe productos y materiales para actualizar el inventario.' }}</p>
        </div>
        <div id="inventory-actions" class="flex shrink-0 flex-wrap gap-3"></div>
    </header>
    <x-panel.settings.stats :stats="$commerceStats" />
    <div id="inventory-feedback" role="status" aria-live="polite" class="hidden rounded-xl border border-accent/30 bg-accent/10 px-5 py-4 text-sm text-on-primary"></div>
    <div id="inventory-toolbar" class="flex flex-wrap items-end gap-4 rounded-xl bg-primary-800 p-6 empty:hidden"></div>
    <section class="overflow-hidden rounded-xl bg-primary-800 shadow-2xl shadow-primary-900/20" aria-label="Paginación del listado">
        <div class="border-b border-primary-700/50 bg-primary-700/50 px-6 py-5"><h2 id="inventory-title" class="text-lg font-bold text-on-primary">Listado de {{ $section === 'sales' ? 'ventas' : 'compras' }}</h2></div>
        <div id="inventory-content" class="space-y-5" aria-busy="true"></div>
    </section>
    <dialog id="inventory-dialog" class="m-auto max-h-[90dvh] overflow-y-auto w-[min(95vw,900px)] rounded-2xl border border-primary-600/50 bg-primary-800 p-6 text-on-primary shadow-2xl backdrop:bg-primary-900/80 sm:p-8">
        <div class="mb-6 flex items-center justify-between gap-4"><h2 id="inventory-dialog-title" class="font-manrope text-2xl font-extrabold"></h2><button id="inventory-dialog-close" type="button" aria-label="Cerrar formulario" class="rounded-lg p-2 text-primary-200 hover:bg-primary-700"><span class="material-symbols-outlined" aria-hidden="true">close</span></button></div>
        <div id="inventory-dialog-content"></div>
    </dialog>
</div>
@endsection
