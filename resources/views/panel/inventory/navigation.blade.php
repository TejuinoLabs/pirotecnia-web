@php
    $links = match ($area) {
        'settings' => [
            'settings.index' => ['Configuración', null],
            'settings.products.index' => ['Productos', 'inventory.view'],
            'settings.packages.index' => ['Paquetes', 'events.view'],
            'settings.inventory.categories' => ['Categorías', 'inventory.view'],
            'settings.inventory.attributes' => ['Atributos', 'inventory.view'],
            'settings.inventory.effects' => ['Efectos', 'inventory.view'],
            'settings.inventory.colors' => ['Colores', 'inventory.view'],
            'settings.inventory.locations' => ['Ubicaciones', 'inventory.view'],
            'settings.inventory.profiles' => ['Perfiles', 'inventory.view'],
            'inventory.rules' => ['Reglas de inventario', 'inventory.view'],
        ],
        'purchases' => ['purchases.index' => ['Compras y recepciones', 'purchases.view']],
        'events' => ['events.index' => ['Eventos', 'events.view'], 'inventory.preparations' => ['Preparación', 'events.prepare']],
        default => ['inventories.index' => ['Inventario', 'inventory.view'], 'inventory.kardex' => ['Kardex', 'inventory.view'], 'inventory.audit' => ['Auditoría', 'audit.view']],
    };
@endphp
<nav class="mt-6 flex flex-wrap gap-3" aria-label="{{ $area === 'settings' ? 'Configuración' : 'Navegación del módulo' }}">
    @foreach ($links as $route => [$label, $permission])
        @if ($permission === null || in_array($permission, $permissions, true))
            <a class="rounded-xl border border-primary-600 px-4 py-2 text-sm hover:bg-primary-700 {{ request()->routeIs($route) ? 'bg-primary-700 text-secondary' : '' }}" href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
        @endif
    @endforeach
</nav>
