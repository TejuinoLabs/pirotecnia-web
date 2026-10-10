@extends('templates.adminlte')

@section('main-content')
    @include('panel.settings.partials.simple-catalog-show', ['catalogTitle' => 'Niveles de experiencia', 'catalogDescription' => 'Configura los niveles del personal técnico y los requisitos de los paquetes.', 'catalogIcon' => 'badge', 'routePrefix' => 'experience-levels', 'indexRoute' => 'settings.experience-levels.index', 'singular' => 'nivel de experiencia', 'createLabel' => 'Nuevo nivel de experiencia'])
@endsection
