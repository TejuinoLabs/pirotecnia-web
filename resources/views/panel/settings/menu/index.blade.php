@extends('templates.adminlte')

@section('content-header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Menu</h1>
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb fa-pull-right">
                        <li class="breadcrumb-item"><a href="#">Inicio</a></li>
                        <li class="breadcrumb-item active">Menu</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
@endsection

@section('content')
    <section class="content">
        <div class="container-fluid">
            <!-- Small boxes (Stat box) -->
            <div class="row">
                <x-small-box color="bg-info" number="150" text="Nuevos pedidos" icon="ion ion-bag" url="#"
                    footerText="Más información" />
                <!-- ./col -->
                <x-small-box color="bg-success" number="53" text="Tasa de rebote" icon="ion ion-stats-bars"
                    url="#" footerText="Más información" />
                <!-- ./col -->
                <x-small-box color="bg-warning" number="44" text="Registros de usuario" icon="ion ion-person-add"
                    url="#" footerText="Más información" />
                <!-- ./col -->
                <x-small-box color="bg-danger" number="65" text="Visitantes únicos" icon="ion ion-pie-graph"
                    url="#" footerText="Más información" />
                <!-- ./col -->
            </div>
            <!-- /.row -->
            <!-- Main row -->
            <div class="row">
                <!-- Left col -->
                <section class="col-lg-12 connectedSortable">
                    <x-card title="Menu" icon="fas fa-box">
                        <x-slot:tools>
                            <a href="{{ route('menu.create') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i>
                            </a>
                        </x-slot>
                        <x-slot:body class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th class="col-2">Titulo</th>
                                        <th class="col-6">url</th>
                                        <th class="col-2">Activo</th>
                                        <th class="col-2">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $listRows = \App\Support\PanelPagination::collection($menu);
                                    @endphp
                                    @foreach ($listRows as $item)
                                        <tr>
                                            <td>{{ $item->title }}</td>
                                            <td>{{ $item->url != "" ? route($item->url) : ""}}</td>
                                            <td><input type="checkbox" {{ $item->active ? 'checked' : '' }} disabled></td>
                                            <td>      
                                                {{-- Button form activate or desactivate --}}
                                                <form action="{{ route('menu.active', $item->id) }}" method="POST">
                                                    @csrf
                                                    @method('PUT')
                                                    <button type="submit" class="btn {{ $item->active ? 'btn-danger' : 'btn-primary' }} btn-sm">
                                                        {{ $item->active ? 'Desactivar' : 'Activar' }}
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-slot>
                    </x-card>
                </section>
            </div>
        </div>
    </section>
    <x-panel.pagination :paginator="$listRows" />
@endsection