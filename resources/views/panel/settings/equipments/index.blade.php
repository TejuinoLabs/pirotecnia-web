@extends('templates.adminlte')

@section('content-header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Equipo</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Configuración</a></li>
                        <li class="breadcrumb-item active">Equipo</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('main-content')
    @php
        $toneClasses = [
            'secondary' => 'bg-secondary/10 text-secondary',
            'accent' => 'bg-accent/10 text-accent',
            'warning' => 'bg-warning/10 text-warning',
            'error' => 'bg-error/10 text-error',
        ];

        $hasActiveFilters =
            filled($selectedType) ||
            filled($selectedAvailability) ||
            filled($selectedOperational) ||
            filled($selectedPackageUsage);
    @endphp

    <div class="mx-auto mt-16 w-full max-w-[1680px] space-y-8 px-4 py-6 sm:p-8">
        <div class="mb-12 flex flex-col justify-between gap-6 md:flex-row md:items-end">
            <div class="space-y-1">
                <h2 class="text-4xl font-extrabold font-manrope tracking-tighter text-on-primary">Equipamiento</h2>
                <p class="font-medium text-primary-200">
                    Administra el equipo operativo, técnico y de seguridad utilizado en los eventos.
                </p>
            </div>
            <a href="{{ route('equipments.create') }}"
                class="flex items-center gap-2 rounded-xl bg-secondary px-6 py-3 font-bold text-on-secondary shadow-lg shadow-secondary/20 transition-all hover:bg-secondary-600">
                <span class="material-symbols-outlined">add_circle</span>
                Nuevo equipamiento
            </a>
        </div>

        <div class="mb-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div class="group relative overflow-hidden rounded-xl bg-primary-800 p-8 shadow-2xl shadow-primary-900/20">
                <div
                    class="absolute right-0 top-0 h-24 w-24 rounded-bl-full bg-primary/5 transition-transform group-hover:scale-110">
                </div>
                <p class="mb-2 text-xs font-bold uppercase tracking-widest text-primary-200">Total equipos</p>
                <h3 class="font-manrope text-5xl font-extrabold text-primary">{{ number_format($equipmentStats['total']) }}</h3>
                <div class="mt-4 flex items-center gap-1 text-xs font-semibold text-secondary">
                    <span class="material-symbols-outlined text-sm">precision_manufacturing</span>
                    <span>Catálogo visible</span>
                </div>
            </div>

            <div class="group relative overflow-hidden rounded-xl bg-primary-800 p-8 shadow-2xl shadow-primary-900/20">
                <div
                    class="absolute right-0 top-0 h-24 w-24 rounded-bl-full bg-secondary/5 transition-transform group-hover:scale-110">
                </div>
                <p class="mb-2 text-xs font-bold uppercase tracking-widest text-primary-200">Equipos disponibles</p>
                <h3 class="font-manrope text-5xl font-extrabold text-secondary">{{ number_format($equipmentStats['available']) }}</h3>
                <div class="mt-4 flex items-center gap-1 text-xs font-semibold text-accent">
                    <span class="material-symbols-outlined text-sm">check_circle</span>
                    <span>Sin eventos próximos asignados</span>
                </div>
            </div>

            <div class="group relative overflow-hidden rounded-xl bg-primary-800 p-8 shadow-2xl shadow-primary-900/20">
                <div
                    class="absolute right-0 top-0 h-24 w-24 rounded-bl-full bg-warning/5 transition-transform group-hover:scale-110">
                </div>
                <p class="mb-2 text-xs font-bold uppercase tracking-widest text-primary-200">Asignados a eventos</p>
                <h3 class="font-manrope text-5xl font-extrabold text-warning">{{ number_format($equipmentStats['assigned']) }}</h3>
                <div class="mt-4 flex items-center gap-1 text-xs font-semibold text-warning">
                    <span class="material-symbols-outlined text-sm">event_upcoming</span>
                    <span>Con agenda futura</span>
                </div>
            </div>

            <div class="group relative overflow-hidden rounded-xl bg-primary-800 p-8 shadow-2xl shadow-primary-900/20">
                <div
                    class="absolute right-0 top-0 h-24 w-24 rounded-bl-full bg-accent/5 transition-transform group-hover:scale-110">
                </div>
                <p class="mb-2 text-xs font-bold uppercase tracking-widest text-primary-200">Asignados a paquetes</p>
                <h3 class="font-manrope text-5xl font-extrabold text-accent">{{ number_format($equipmentStats['packageLinked']) }}</h3>
                <div class="mt-4 flex items-center gap-1 text-xs font-semibold text-accent">
                    <span class="material-symbols-outlined text-sm">auto_awesome</span>
                    <span>Uso comercial configurado</span>
                </div>
            </div>
        </div>

        <form action="{{ route('settings.equipments.index') }}"
            class="mb-6 rounded-xl bg-primary-800 p-6 shadow-2xl shadow-primary-900/20" method="GET">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
                <div class="space-y-1.5">
                    <label class="px-1 text-[10px] font-bold uppercase tracking-widest text-primary-200">Tipo de
                        equipo</label>
                    <select
                        class="h-10 w-full rounded-lg border border-primary-600/50 bg-primary-700/70 px-3 text-sm text-on-primary focus:border-secondary focus:ring-1 focus:ring-secondary/30"
                        name="type">
                        <option value="">Todos los tipos</option>
                        @foreach ($typeOptions as $typeOption)
                            <option @selected($selectedType === $typeOption) value="{{ $typeOption }}">{{ $typeOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="px-1 text-[10px] font-bold uppercase tracking-widest text-primary-200">Disponibilidad</label>
                    <select
                        class="h-10 w-full rounded-lg border border-primary-600/50 bg-primary-700/70 px-3 text-sm text-on-primary focus:border-secondary focus:ring-1 focus:ring-secondary/30"
                        name="availability">
                        <option value="">Cualquier estado</option>
                        @foreach ($availabilityOptions as $availabilityOption)
                            <option @selected($selectedAvailability === $availabilityOption) value="{{ $availabilityOption }}">
                                {{ $availabilityOption }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="px-1 text-[10px] font-bold uppercase tracking-widest text-primary-200">Estado
                        operativo</label>
                    <select
                        class="h-10 w-full rounded-lg border border-primary-600/50 bg-primary-700/70 px-3 text-sm text-on-primary focus:border-secondary focus:ring-1 focus:ring-secondary/30"
                        name="operational">
                        <option value="">Todos</option>
                        @foreach ($operationalOptions as $operationalOption)
                            <option @selected($selectedOperational === $operationalOption) value="{{ $operationalOption }}">
                                {{ $operationalOption }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="px-1 text-[10px] font-bold uppercase tracking-widest text-primary-200">Uso en
                        paquetes</label>
                    <select
                        class="h-10 w-full rounded-lg border border-primary-600/50 bg-primary-700/70 px-3 text-sm text-on-primary focus:border-secondary focus:ring-1 focus:ring-secondary/30"
                        name="package_usage">
                        <option value="">Cualquiera</option>
                        <option @selected($selectedPackageUsage === 'yes') value="yes">Sí</option>
                        <option @selected($selectedPackageUsage === 'no') value="no">No</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button
                        class="flex h-10 flex-1 items-center justify-center gap-2 rounded-lg border border-primary-600/50 bg-primary-700 text-on-primary transition-colors hover:bg-primary-600">
                        <span class="material-symbols-outlined text-sm">filter_list</span>
                        Aplicar
                    </button>
                    @if ($hasActiveFilters)
                        <a href="{{ route('settings.equipments.index') }}"
                            class="flex h-10 items-center justify-center rounded-lg bg-on-primary/10 px-4 text-sm font-semibold text-on-primary transition-colors hover:bg-on-primary/20">
                            Limpiar
                        </a>
                    @endif
                </div>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl bg-primary-800 shadow-2xl shadow-primary-900/20">
            @if ($equipmentRows->isEmpty())
                <div class="p-10">
                    <div class="rounded-2xl border border-primary-700/60 bg-primary-700/50 p-8 text-center">
                        <span class="material-symbols-outlined text-5xl text-primary-200">construction</span>
                        <p class="mt-4 text-lg font-bold text-on-primary">Sin equipamiento visible</p>
                        <p class="mt-2 text-sm text-primary-200">
                            Ajusta filtros o registra el primer equipo para comenzar a operar este módulo.
                        </p>
                    </div>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="responsive-stack-table responsive-stack-table-dark w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-primary-700/70">
                                <th class="px-6 py-4 text-[10px] font-extrabold uppercase tracking-widest text-primary-200">
                                    Nombre del equipo</th>
                                <th
                                    class="px-6 py-4 text-center text-[10px] font-extrabold uppercase tracking-widest text-primary-200">
                                    Clave</th>
                                <th class="px-6 py-4 text-[10px] font-extrabold uppercase tracking-widest text-primary-200">
                                    Tipo</th>
                                <th class="px-6 py-4 text-[10px] font-extrabold uppercase tracking-widest text-primary-200">
                                    Categoría</th>
                                <th
                                    class="px-6 py-4 text-center text-[10px] font-extrabold uppercase tracking-widest text-primary-200">
                                    Disponibilidad</th>
                                <th
                                    class="px-6 py-4 text-center text-[10px] font-extrabold uppercase tracking-widest text-primary-200">
                                    Estado operativo</th>
                                <th
                                    class="px-6 py-4 text-center text-[10px] font-extrabold uppercase tracking-widest text-primary-200">
                                    En Paquete</th>
                                <th class="px-6 py-4 text-[10px] font-extrabold uppercase tracking-widest text-primary-200">
                                    Referencia</th>
                                <th
                                    class="px-6 py-4 text-center text-[10px] font-extrabold uppercase tracking-widest text-primary-200">
                                    Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-primary-700/50">
                            @php
                                $listRows = \App\Support\PanelPagination::collection($equipmentRows);
                            @endphp
                            @foreach ($listRows as $equipment)
                                <tr class="transition-colors hover:bg-primary-700/35">
                                    <td class="px-6 py-4" data-label="Nombre del equipo">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex h-8 w-8 items-center justify-center rounded bg-primary/10 text-primary">
                                                <span class="material-symbols-outlined text-lg">construction</span>
                                            </div>
                                            <div class="min-w-0">
                                                <span class="block text-sm font-semibold text-on-primary">{{ $equipment['name'] }}</span>
                                                <span class="block text-xs text-primary-200">{{ $equipment['detail'] }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center font-mono text-xs text-primary-200" data-label="Clave">
                                        {{ $equipment['code'] }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-on-primary" data-label="Tipo">
                                        {{ $equipment['type_label'] }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-primary-200" data-label="Categoría">
                                        {{ $equipment['category_label'] }}
                                    </td>
                                    <td class="px-6 py-4 text-center" data-label="Disponibilidad">
                                        <span
                                            class="rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-tighter {{ $toneClasses[$equipment['availability_tone']] }}">
                                            {{ $equipment['availability_label'] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center" data-label="Estado operativo">
                                        <span
                                            class="rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-tighter {{ $toneClasses[$equipment['operational_tone']] }}">
                                            {{ $equipment['operational_label'] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center text-xs font-semibold {{ $equipment['in_package'] ? 'text-accent' : 'text-primary-200' }}"
                                        data-label="En Paquete">
                                        {{ $equipment['in_package_label'] }}
                                    </td>
                                    <td class="px-6 py-4" data-label="Referencia">
                                        <div class="text-xs">
                                            <p class="font-semibold text-on-primary">{{ $equipment['reference_label'] }}</p>
                                            <p class="mt-1 text-primary-200">{{ $equipment['reference_meta'] }}</p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4" data-label="Acciones">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="{{ route('equipments.edit', $equipment['id']) }}"
                                                class="inline-flex rounded-lg p-2 text-primary-200 transition-colors hover:bg-primary-700/70 hover:text-secondary"
                                                title="Editar equipo">
                                                <span class="material-symbols-outlined text-[20px]">edit</span>
                                            </a>
                                            <button
                                                class="inline-flex rounded-lg p-2 text-primary-200 transition-colors hover:bg-error/10 hover:text-error"
                                                onclick="confirmDelete({{ $equipment['id'] }})" title="Eliminar equipo"
                                                type="button">
                                                <span class="material-symbols-outlined text-[20px]">delete</span>
                                            </button>
                                            <form action="{{ route('equipments.destroy', $equipment['id']) }}"
                                                class="hidden" id="deleteForm-{{ $equipment['id'] }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="flex items-center justify-between bg-primary-700/60 px-6 py-4">
                <span class="text-xs text-primary-200">
                    Mostrando {{ ($listRows ?? \App\Support\PanelPagination::collection($equipmentRows))->count() }} de {{ number_format($equipments->count()) }}
                    equipos registrados
                </span>
                @if ($hasActiveFilters)
                    <a href="{{ route('settings.equipments.index') }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-800 px-3 py-2 text-xs font-semibold text-primary-200 transition-colors hover:bg-primary hover:text-on-primary">
                        <span class="material-symbols-outlined text-sm">restart_alt</span>
                        Ver todo
                    </a>
                @endif
            </div>
        </div>
    </div>
    <x-panel.pagination :paginator="$listRows ?? \App\Support\PanelPagination::collection($equipmentRows)" />
@endsection

@section('extra-script')
    <script>
        function confirmDelete(id) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: '¡No podrás revertir esto!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '¡Sí, bórralo!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteForm-' + id).submit();
                }
            })
        }
    </script>
@endsection
