@props(['paginator'])
<nav aria-label="Paginación del listado" class="mx-auto my-6 flex max-w-[1680px] flex-wrap items-center justify-between gap-4 rounded-xl bg-primary-800 p-5 text-sm text-primary-200">
    <span>Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }} · {{ $paginator->total() }} registros</span>
    <div class="flex gap-3">
        @if ($paginator->onFirstPage())
            <span aria-disabled="true" class="rounded-lg border border-primary-600 px-4 py-2 opacity-50">Anterior</span>
        @else
            <a rel="prev" href="{{ $paginator->previousPageUrl() }}" class="rounded-lg border border-primary-600 px-4 py-2 hover:bg-primary-700">Anterior</a>
        @endif
        @if ($paginator->hasMorePages())
            <a rel="next" href="{{ $paginator->nextPageUrl() }}" class="rounded-lg border border-primary-600 px-4 py-2 hover:bg-primary-700">Siguiente</a>
        @else
            <span aria-disabled="true" class="rounded-lg border border-primary-600 px-4 py-2 opacity-50">Siguiente</span>
        @endif
    </div>
</nav>
