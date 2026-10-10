@extends('templates.adminlte')

@section('main-content')
    @php
        $toneClasses = [
            'secondary' => 'bg-secondary/10 text-secondary border border-secondary/20',
            'warning' => 'bg-warning/10 text-warning border border-warning/20',
        ];
    @endphp

    <div class="mx-auto mt-16 w-full max-w-[1680px] space-y-8 px-4 py-6 sm:p-8">
        @if (session('status'))
            <div class="rounded-2xl border border-accent/20 bg-accent/10 px-5 py-4 text-sm font-semibold text-accent">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-2xl border border-error/20 bg-error/10 px-5 py-4 text-sm font-semibold text-error">
                {{ session('error') }}
            </div>
        @endif

        <x-panel.settings.header title="Tipos de cliente" description="Administra las categorías de clientes y su uso en las ventas del sistema."
            :action-url="route('client-types.create')" action-label="Nuevo tipo de cliente" />
        <x-panel.settings.stats :stats="[
            ['label' => 'Tipos de cliente', 'value' => $clientTypeStats['total'], 'icon' => 'groups', 'hint' => 'Categorías registradas'],
            ['label' => 'Con ventas', 'value' => $clientTypeStats['active'], 'icon' => 'check_circle', 'hint' => 'Con registros comerciales', 'tone' => 'text-secondary'],
            ['label' => 'Sin ventas', 'value' => $clientTypeStats['inactive'], 'icon' => 'person', 'hint' => 'Sin registros comerciales', 'tone' => 'text-warning'],
        ]" />

        <div class="overflow-hidden rounded-xl bg-primary-800 shadow-2xl shadow-primary-900/20">
            <div class="flex items-center justify-between bg-primary-700/50 p-6">
                <h4 class="text-lg font-headline font-bold text-on-primary">Listado de tipos de cliente</h4>
                <div class="text-xs font-semibold uppercase tracking-widest text-primary-200">
                    {{ number_format($clientTypeRows->count()) }} registros en total
                </div>
            </div>

            @if ($clientTypeRows->isEmpty())
                <div class="p-10">
                    <div class="rounded-2xl border border-primary-700/60 bg-primary-700/50 p-8 text-center">
                        <span class="material-symbols-outlined text-5xl text-primary-200">groups</span>
                        <p class="mt-4 text-lg font-bold text-on-primary">Sin tipos de cliente registrados</p>
                        <p class="mt-2 text-sm text-primary-200">
                            Crea el primero para comenzar a categorizar los registros comerciales.
                        </p>
                    </div>
                </div>
            @else
                <table class="responsive-stack-table responsive-stack-table-dark w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-primary-700/60">
                            <th
                                class="px-6 py-4 font-label text-[11px] font-bold uppercase tracking-wider text-primary-200">
                                Nombre</th>
                            <th
                                class="px-6 py-4 font-label text-[11px] font-bold uppercase tracking-wider text-primary-200">
                                Descripción</th>
                            <th
                                class="px-6 py-4 font-label text-[11px] font-bold uppercase tracking-wider text-primary-200">
                                Clientes</th>
                            <th
                                class="px-6 py-4 font-label text-[11px] font-bold uppercase tracking-wider text-primary-200">
                                Estatus</th>
                            <th
                                class="px-6 py-4 font-label text-[11px] font-bold uppercase tracking-wider text-primary-200">
                                Fecha Creación</th>
                            <th
                                class="px-6 py-4 text-right font-label text-[11px] font-bold uppercase tracking-wider text-primary-200">
                                Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-primary-700/40">
                        @php
                            $listRows = \App\Support\PanelPagination::collection($clientTypeRows);
                        @endphp
                        @foreach ($listRows as $clientType)
                            <tr class="group transition-colors hover:bg-primary-700/35">
                                <td class="px-6 py-5" data-label="Nombre">
                                    <span class="font-semibold text-on-primary">{{ $clientType['name'] }}</span>
                                </td>
                                <td class="px-6 py-5" data-label="Descripción">
                                    <p class="max-w-xs truncate text-sm text-primary-200">{{ $clientType['description'] }}
                                    </p>
                                </td>
                                <td class="px-6 py-5" data-label="Clientes">
                                    <div class="flex items-center space-x-2">
                                        <span class="material-symbols-outlined text-sm text-primary">person</span>
                                        <span
                                            class="font-medium text-on-primary">{{ number_format($clientType['sales_count']) }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-5" data-label="Estatus">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $toneClasses[$clientType['status_tone']] }}">
                                        <span
                                            class="mr-1.5 h-1 w-1 rounded-full {{ $clientType['status_tone'] === 'secondary' ? 'bg-secondary' : 'bg-warning' }}"></span>
                                        {{ $clientType['status_label'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-5" data-label="Fecha Creación">
                                    <span class="text-sm text-primary-200">{{ $clientType['created_at_label'] }}</span>
                                </td>
                                <td class="px-6 py-5 text-right" data-label="Acciones">
                                    <div
                                        class="flex items-center justify-end space-x-1 ">
                                        <a class="p-1.5 transition-colors hover:text-primary"
                                            href="{{ route('client-types.show', $clientType['id']) }}" title="Ver detalle">
                                            <span class="material-symbols-outlined text-lg">visibility</span>
                                        </a>
                                        <a class="p-1.5 transition-colors hover:text-primary"
                                            href="{{ route('client-types.edit', $clientType['id']) }}" title="Editar">
                                            <span class="material-symbols-outlined text-lg">edit</span>
                                        </a>
                                        <button class="p-1.5 transition-colors hover:text-error" title="Eliminar"
                                            type="button" onclick="confirmDelete({{ $clientType['id'] }})">
                                            <span class="material-symbols-outlined text-lg">delete</span>
                                        </button>
                                        <form action="{{ route('client-types.destroy', $clientType['id']) }}"
                                            class="hidden" id="deleteForm-{{ $clientType['id'] }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="flex items-center justify-between bg-primary-700/40 p-6">
                <p class="text-xs text-primary-200">Mostrando <span
                        class="font-semibold text-on-primary">{{ ($listRows ?? \App\Support\PanelPagination::collection($clientTypeRows))->count() }}</span> de {{ $clientTypeRows->count() }} categorías</p>
                <a class="rounded-lg bg-primary-800 px-3 py-2 text-xs font-semibold text-primary-200 transition-colors hover:bg-primary hover:text-on-primary"
                    href="{{ route('client-types.create') }}">
                    Crear nuevo
                </a>
            </div>
        </div>
    </div>
    <x-panel.pagination :paginator="$listRows ?? \App\Support\PanelPagination::collection($clientTypeRows)" />
@endsection
