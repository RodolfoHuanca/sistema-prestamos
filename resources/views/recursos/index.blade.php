<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Gestor de recursos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
    <h3>Inventario de recursos</h3>
    @if(session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('recursos.store') }}" class="row g-2 mb-4">
        @csrf
        <div class="col-md-2"><input name="codigo" class="form-control" placeholder="Código" value="{{ old('codigo') }}"></div>
        <div class="col-md-3"><input name="nombre" class="form-control" placeholder="Nombre" value="{{ old('nombre') }}"></div>
        <div class="col-md-2">
            <select name="tipo" class="form-select">
                <option value="equipo">Equipo</option>
                <option value="laboratorio">Laboratorio</option>
                <option value="material">Material</option>
            </select>
        </div>
        <div class="col-md-3"><input name="ubicacion" class="form-control" placeholder="Ubicación" value="{{ old('ubicacion') }}"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Registrar</button></div>
        <div class="col-12"><input name="caracteristicas" class="form-control" placeholder="Características" value="{{ old('caracteristicas') }}"></div>
    </form>

    <table class="table table-striped align-middle">
        <tr><th>Código</th><th>Nombre</th><th>Tipo</th><th>Ubicación</th><th>Estado</th><th></th></tr>
        @foreach($recursos as $r)
        <tr>
            <td>{{ $r->codigo }}</td>
            <td>{{ $r->nombre }}</td>
            <td>{{ ucfirst($r->tipo) }}</td>
            <td>{{ $r->ubicacion }}</td>
            @if($r->estado === 'baja')
                <td><span class="badge bg-secondary">Baja</span></td>
                <td></td>
            @else
                <td>
                    <form method="POST" action="{{ route('recursos.update', $r) }}" class="d-flex gap-1">
                        @csrf @method('PUT')
                        <select name="estado" class="form-select form-select-sm">
                            @foreach(['disponible', 'prestado', 'mantenimiento'] as $s)
                                <option value="{{ $s }}" @selected($r->estado === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-sm btn-outline-primary">Guardar</button>
                    </form>
                </td>
                <td>
                    <form method="POST" action="{{ route('recursos.baja', $r) }}">
                        @csrf @method('PATCH')
                        <button class="btn btn-sm btn-outline-danger">Dar de baja</button>
                    </form>
                </td>
            @endif
        </tr>
        @endforeach
    </table>
</body>
</html>