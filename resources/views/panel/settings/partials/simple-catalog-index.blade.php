@php
    $listRows = \App\Support\PanelPagination::collection($records);
    $withDescription = $records->filter(fn ($record) => filled($record->description))->count();
@endphp
<div class="mx-auto mt-16 w-full max-w-[1680px] space-y-8 px-4 py-6 sm:p-8">
    @foreach (['status' => 'text-accent bg-accent/10 border-accent/20', 'error' => 'text-error bg-error/10 border-error/20'] as $key => $classes)
        @if (session($key))<p role="status" class="rounded-xl border px-5 py-4 text-sm {{ $classes }}">{{ session($key) }}</p>@endif
    @endforeach
    <x-panel.settings.header :title="$catalogTitle" :description="$catalogDescription" :action-url="route($routePrefix.'.create')" :action-label="$createLabel" />
    <x-panel.settings.stats :stats="[
        ['label' => 'Total de registros', 'value' => $records->count(), 'icon' => $catalogIcon, 'hint' => 'Registros del catálogo'],
        ['label' => 'Con descripción', 'value' => $withDescription, 'icon' => 'description', 'hint' => 'Con información registrada', 'tone' => 'text-secondary'],
        ['label' => 'Sin descripción', 'value' => $records->count() - $withDescription, 'icon' => 'edit_note', 'hint' => 'Pendientes de describir', 'tone' => 'text-warning'],
    ]" />
    <section class="overflow-hidden rounded-xl bg-primary-800 shadow-2xl shadow-primary-900/20" aria-label="Listado del catálogo">
        <div class="flex flex-wrap items-center justify-between gap-3 bg-primary-700/50 px-6 py-5">
            <h2 class="text-lg font-bold text-on-primary">Listado de {{ mb_strtolower($catalogTitle) }}</h2>
            <span class="text-xs font-semibold uppercase tracking-widest text-primary-200">{{ $records->count() }} registros en total</span>
        </div>
        @if ($listRows->isEmpty())
            <div class="m-6 rounded-2xl border border-primary-700/60 bg-primary-700/50 p-8 text-center">
                <span class="material-symbols-outlined text-5xl text-primary-200" aria-hidden="true">{{ $catalogIcon }}</span>
                <p class="mt-4 text-lg font-bold text-on-primary">Sin registros para esta consulta</p>
                <p class="mt-2 text-sm text-primary-200">Registra el primer elemento para configurar este catálogo.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="responsive-stack-table responsive-stack-table-dark w-full border-collapse text-left text-sm">
                    <thead><tr class="bg-primary-700/70">
                        @foreach (['Nombre', 'Descripción', 'Acciones'] as $label)<th class="px-6 py-4 text-xs font-extrabold uppercase tracking-widest text-primary-200">{{ $label }}</th>@endforeach
                    </tr></thead>
                    <tbody class="divide-y divide-primary-700/50">
                        @foreach ($listRows as $record)
                            <tr class="transition-colors hover:bg-primary-700/35">
                                <td data-label="Nombre" class="px-6 py-5 font-semibold text-on-primary">{{ $record->name }}</td>
                                <td data-label="Descripción" class="px-6 py-5 text-primary-200">{{ $record->description ?: 'Sin descripción' }}</td>
                                <td data-label="Acciones" class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        @foreach (['show' => ['visibility', 'Ver detalle'], 'edit' => ['edit', 'Editar']] as $action => [$icon, $label])
                                            <a href="{{ route($routePrefix.'.'.$action, $record->id) }}" aria-label="{{ $label }}: {{ $record->name }}" title="{{ $label }}" class="rounded-lg p-2 text-primary-200 transition-colors hover:bg-primary-700/70 hover:text-secondary"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ $icon }}</span></a>
                                        @endforeach
                                        <form action="{{ route($routePrefix.'.destroy', $record->id) }}" method="POST" onsubmit="return confirm('¿Eliminar este registro?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" aria-label="Eliminar: {{ $record->name }}" title="Eliminar" class="rounded-lg p-2 text-primary-200 transition-colors hover:bg-error/10 hover:text-error"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">delete</span></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-panel.pagination :paginator="$listRows" />
    </section>
</div>
