@extends('templates.adminlte')
@section('main-content')
<div class="mx-auto mt-16 max-w-4xl space-y-6 px-4 py-8">
    <a class="text-secondary" href="{{ route('inventory.preparations') }}">← Preparación de eventos</a>
    <section class="space-y-5 rounded-2xl border border-primary-700 bg-primary-800 p-6">
        <h1 class="text-3xl font-bold">Actualizar seguimiento del evento #{{ $plan['event_id'] }}</h1>
        @if(session('status'))<p role="status" class="text-secondary">{{ session('status') }}</p>@endif
        @if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <p>Versión actual: {{ $plan['current_version'] }}. @if($plan['needs_upgrade']) Nueva versión: {{ $plan['target_version'] }}. @endif</p>
        <p class="text-primary-200">El seguimiento utiliza llegada, instalación terminada, detonación registrada y evento finalizado. Conserva los IDs, registros y comentarios anteriores. La actualización agrega los hitos faltantes y cambia la versión del protocolo.</p>
        @if(!$plan['needs_upgrade'])<p>Este protocolo ya contiene los cuatro códigos de hito.</p>
        @elseif($plan['blocker'])<p role="alert">{{ $plan['blocker'] }}</p>
        @else
        <p>Códigos pendientes: {{ implode(', ', $plan['missing_codes']) }}.</p>
        <p class="text-primary-200">Las operaciones móviles pendientes con una versión anterior recibirán un conflicto para revisión. Actualiza la copia del evento después de aplicar este cambio.</p>
        <form method="post" action="{{ route('settings.protocol-upgrade.store', $plan['event_id']) }}" class="space-y-4">@csrf
            <input type="hidden" name="expected_version" value="{{ $plan['current_version'] }}">
            <label class="block">Motivo de la actualización<textarea name="reason" required maxlength="2000" class="mt-2 w-full rounded-xl border border-primary-600 bg-primary-900 p-3" placeholder="Habilitar el seguimiento de instalación y detonación para este evento">{{ old('reason') }}</textarea></label>
            <button class="rounded-xl bg-secondary px-5 py-3 font-semibold text-primary-950">Aplicar actualización auditada</button>
        </form>
        @endif
    </section>
</div>
@endsection
