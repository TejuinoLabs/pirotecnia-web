<div>
    <div class="d-flex justify-content-between align-items-center px-3 pt-3">
        <p class="text-sm text-muted mb-0">
            Eventos del {{ \Illuminate\Support\Carbon::parse($selectedDate)->format('d/m/Y') }}
        </p>
        @if ($selectedDate !== $todayDate)
            <button wire:click="showToday" class="btn btn-default btn-sm" type="button">
                Hoy
            </button>
        @endif
    </div>

    <table class="table table-bordered table-hover text-nowrap">
        <thead>
            <tr>
                <th>Paquete</th>
                <th>Fecha del evento</th>
                <th>Dirección del evento</th>
                <th>Teléfono</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($events as $event)
                <tr>
                    <td>{{ $event->package->name }}</td>
                    <td>{{ $event->event_date }}</td>
                    <td>{{ $event->event_address }}</td>
                    <td>{{ $event->phone }}</td>                    
                    <td>
                        <a href="{{ route('events.show', $event) }}" class="btn btn-info btn-sm">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="{{ route('events.edit', $event) }}" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form id="deleteForm-{{ $event->id }}" action="{{ route('events.destroy', $event) }}"
                            method="POST" style="display: inline">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger btn-sm" type="button"
                                onclick="confirmDelete({{ $event->id }})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted">
                        No hay eventos para la fecha seleccionada.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-4">{{ $events->links() }}</div>
</div>
