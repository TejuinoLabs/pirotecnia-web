@props(['title', 'description', 'actionUrl' => null, 'actionLabel' => null, 'actionsId' => null])
<header class="flex flex-col justify-between gap-6 md:flex-row md:items-end" data-settings-header>
    <div class="space-y-2">
        <nav aria-label="Ruta de navegación" class="mb-3 flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-widest text-primary-200">
            <a href="{{ route('dashboard') }}" class="hover:text-secondary">Inicio</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('settings.index') }}" class="hover:text-secondary">Configuración</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page" class="text-on-primary">{{ $title }}</span>
        </nav>
        <h1 class="text-4xl font-extrabold font-manrope tracking-tighter text-on-primary">{{ $title }}</h1>
        <p class="max-w-3xl font-medium text-primary-200">{{ $description }}</p>
    </div>
    @if ($actionUrl)
        <a href="{{ $actionUrl }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-secondary px-6 py-3 font-bold text-on-secondary shadow-lg shadow-secondary/20 transition-colors hover:bg-secondary-600">
            <span class="material-symbols-outlined" aria-hidden="true">add_circle</span>
            {{ $actionLabel }}
        </a>
    @elseif ($actionsId)
        <div id="{{ $actionsId }}" class="flex shrink-0 flex-wrap gap-3"></div>
    @endif
</header>
