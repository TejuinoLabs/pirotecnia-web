@extends('templates.adminlte')

@section('main-content')
<div class="mx-auto mt-16 w-full max-w-[1680px] space-y-8 px-4 py-6 sm:p-8" id="inventory-panel"
    data-area="settings" data-catalog="{{ $selectedCatalog }}" data-package="{{ $selectedPackage }}" data-section="{{ $section }}" data-event="{{ $eventId }}" data-base="{{ url('/inventory/operations') }}" data-create-label="{{ $presentation['createLabel'] }}">
    <script type="application/json" id="inventory-config">{!! json_encode(['catalogs' => $catalogs, 'permissions' => $permissions], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <input type="hidden" id="inventory-csrf" value="{{ csrf_token() }}">

    <x-panel.settings.header :title="$presentation['title']" :description="$presentation['description']" actions-id="inventory-actions" />
    <x-panel.settings.stats :stats="$presentation['stats']" />

    <div id="inventory-feedback" role="status" aria-live="polite" class="hidden rounded-xl border border-accent/30 bg-accent/10 px-5 py-4 text-sm text-on-primary"></div>
    <div id="inventory-toolbar" class="flex flex-wrap items-end gap-4 rounded-xl bg-primary-800 p-6 shadow-2xl shadow-primary-900/20 empty:hidden"></div>
    <section class="overflow-hidden rounded-xl bg-primary-800 shadow-2xl shadow-primary-900/20" aria-label="Listado del catálogo">
        <div class="border-b border-primary-700/50 bg-primary-700/50 px-6 py-5">
            <h2 class="text-lg font-bold text-on-primary" id="inventory-title">Listado de {{ mb_strtolower($presentation['title']) }}</h2>
        </div>
        <div id="inventory-content" class="space-y-5" aria-busy="true"></div>
    </section>
    <dialog id="inventory-dialog" class="m-auto max-h-[90dvh] overflow-y-auto w-[min(95vw,760px)] rounded-2xl border border-primary-600/50 bg-primary-800 p-6 text-on-primary shadow-2xl backdrop:bg-primary-900/80 sm:p-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <h2 id="inventory-dialog-title" class="font-manrope text-2xl font-extrabold tracking-tight"></h2>
            <button type="button" id="inventory-dialog-close" aria-label="Cerrar formulario" class="rounded-lg p-2 text-primary-200 transition-colors hover:bg-primary-700 hover:text-on-primary"><span class="material-symbols-outlined" aria-hidden="true">close</span></button>
        </div>
        <div id="inventory-dialog-content"></div>
    </dialog>
</div>
@endsection
