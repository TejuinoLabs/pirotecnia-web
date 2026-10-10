@extends('templates.adminlte')

@section('main-content')
<div class="mx-auto mt-16 max-w-[1680px] space-y-6 px-4 py-8 sm:px-8" id="inventory-panel"
    data-catalog="{{ $selectedCatalog }}" data-package="{{ $selectedPackage }}" data-section="{{ $section }}" data-event="{{ $eventId }}" data-base="{{ url('/inventory/operations') }}">
    <script type="application/json" id="inventory-config">{!! json_encode(['catalogs' => $catalogs, 'permissions' => $permissions], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <input type="hidden" id="inventory-csrf" value="{{ csrf_token() }}">
    @php
        $formatQuantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');
        $catalogLabels = ['categories' => 'Categorías', 'attributes' => 'Atributos', 'effects' => 'Efectos', 'colors' => 'Colores', 'locations' => 'Ubicaciones', 'profiles' => 'Perfiles de producto'];
        $pageTitle = match ($section) {
            'products' => 'Productos', 'catalogs' => $catalogLabels[$selectedCatalog] ?? 'Catálogos',
            'rules' => 'Reglas de inventario', 'requirements' => 'Requerimientos de paquetes',
            'purchases' => 'Compras y recepciones', 'events' => 'Preparación de eventos',
            'preparation' => 'Preparación del evento #'.$eventId, 'audit' => 'Auditoría de inventario',
            'kardex' => 'Kardex', default => 'Inventario',
        };
    @endphp
    <header class="rounded-3xl bg-primary-800 p-6 sm:p-8">
        <p class="text-xs font-bold uppercase tracking-widest text-secondary">{{ $area === 'settings' ? 'Configuración' : 'Pirotecnia San Rafael' }}</p>
        <h1 class="mt-3 text-3xl font-bold">{{ $pageTitle }}</h1>
        <p class="mt-3 text-primary-200">{{ match ($area) { 'settings' => 'Administra los catálogos y las reglas del sistema.', 'purchases' => 'Registra órdenes y recibe mercancía en almacén.', 'events' => 'Asigna productos, confirma el surtido y registra entregas y devoluciones.', default => 'Consulta existencias, proyecciones y movimientos de inventario.' } }}</p>
        @include('panel.inventory.navigation')
    </header>
    @if ($section === 'inventory' && !\Illuminate\Support\Facades\DB::table('inventory_balances')->where('quantity', '>', 0)->exists())
    <aside class="rounded-2xl border border-secondary/40 bg-primary-800 p-6"><h2 class="text-xl font-bold">Todavía no hay existencias registradas</h2><p class="mt-2 text-primary-200">Puedes seguir trabajando con eventos y catálogos. Cuando cuentes tus productos, registra la carga inicial o recibe una compra en la ubicación correspondiente.</p>
        @if(app(\App\Services\Inventory\InventoryAccess::class)->allows(auth()->user(), 'system.setup'))<a href="{{ route('settings.setup') }}" class="mt-4 inline-block font-semibold text-secondary">Abrir asistente de configuración →</a>@endif
    </aside>
    @endif
    @if ($summary)
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores de inventario">
        @foreach (['inventory_value' => 'Valor del inventario', 'low_stock_products' => 'Productos bajo mínimo', 'upcoming_events' => 'Eventos próximos (hasta 50)', 'deficit_events' => 'Eventos con déficit', 'pending_purchases' => 'Compras pendientes', 'equipment_outside' => 'Equipo fuera de almacén', 'stand_stock' => 'Mercancía en puestos', 'protected_low_products' => 'Productos protegidos bajos'] as $key => $label)
        <article class="rounded-2xl bg-primary-800 p-5"><p class="text-sm text-primary-200">{{ $label }}</p><p class="mt-3 text-2xl font-bold">{{ $key === 'inventory_value' ? '$'.number_format($summary[$key], 2) : $formatQuantity($summary[$key]) }}</p></article>
        @endforeach
    </section>
    @if ($summary['event_risks'])
    <section class="rounded-2xl border border-warning/40 bg-primary-800 p-6"><h2 class="text-xl font-bold">Eventos con riesgo</h2>
        @foreach ($summary['event_risks'] as $risk)
        <p class="mt-3"><a class="text-secondary underline" href="{{ route('events.preparation', $risk['event_id']) }}">#{{ $risk['event_id'] }} · {{ $risk['client_name'] }}</a> · {{ $risk['event_date'] }} · {{ $risk['status'] === 'critical' ? 'Déficit' : 'Cobertura ajustada' }}</p>
        @endforeach
    </section>
    @endif
    @if ($criticalProducts->total())
    <section class="overflow-hidden rounded-2xl bg-primary-800 p-6">
        <h2 class="text-xl font-bold">Productos críticos <span class="text-sm font-normal text-primary-200">({{ $criticalProducts->total() }})</span></h2>
        <div class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm">
            <thead><tr>@foreach (['Producto', 'Stock', 'Protegido', 'Disponible', 'Demanda futura', 'Compra pendiente'] as $column)<th class="p-3 text-primary-200">{{ $column }}</th>@endforeach</tr></thead>
            <tbody>@foreach ($criticalProducts as $product)<tr>
                <td class="border-t border-primary-700 p-3">{{ $product['display_name'] ?? $product['name'] }}</td>
                @foreach (['physical_stock', 'protected_stock', 'available_stock', 'future_demand', 'incoming_stock'] as $key)<td class="border-t border-primary-700 p-3">{{ $formatQuantity($product[$key]) }}</td>@endforeach
            </tr>@endforeach</tbody>
        </table></div>
        <x-panel.pagination :paginator="$criticalProducts" />
    </section>
    @endif
    @endif
    <div id="inventory-feedback" role="status" aria-live="polite" class="hidden rounded-xl border border-warning p-4"></div>
    <section class="rounded-3xl bg-primary-800 p-4 sm:p-6">
        <h2 class="text-xl font-bold" id="inventory-title">Cargando módulo…</h2>
        <div id="inventory-toolbar" class="my-5 flex flex-wrap gap-3"></div>
        <div id="inventory-content" class="space-y-5" aria-busy="true"></div>
    </section>
    <dialog id="inventory-dialog" class="m-auto max-h-[90dvh] overflow-y-auto w-[min(95vw,760px)] rounded-2xl border border-primary-600 bg-primary-800 p-6 text-on-primary backdrop:bg-black/70">
        <div class="flex items-center justify-between gap-4"><h2 id="inventory-dialog-title" class="text-xl font-bold"></h2><button type="button" id="inventory-dialog-close" aria-label="Cerrar formulario" class="rounded-lg border px-3 py-2">Cerrar</button></div>
        <div id="inventory-dialog-content" class="mt-5"></div>
    </dialog>
</div>
@endsection
