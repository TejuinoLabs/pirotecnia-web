@extends('templates.adminlte')

@section('main-content')
    @include('panel.settings.partials.simple-catalog-show', ['catalogTitle' => 'Tipos de evento', 'catalogDescription' => 'Administra la clasificación de los espectáculos y eventos.', 'catalogIcon' => 'festival', 'routePrefix' => 'event_types', 'indexRoute' => 'settings.event_types.index', 'singular' => 'tipo de evento', 'createLabel' => 'Nuevo tipo de evento'])
@endsection
