@extends(backpack_view('blank'))

@section('content')
<div class="container-fluid">
    <div class="row mb-2">
        <div class="col-md-6">
            <h3>Reportar Equipo</h3>
        </div>
        <div class="col-md-6 text-end">
            <a href="{{ route('reportar-equipo.create') }}" class="btn btn-primary">
                <i class="la la-plus"></i> Reportar miembro
            </a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('reportar-equipo.index') }}" class="row g-2">
                <div class="col-md-6">
                    <label class="form-label">Filtrar por equipo</label>
                    <select name="equipo_id" class="form-control">
                        <option value="">Todos mis equipos</option>
                        @foreach($equipos as $equipo)
                            <option value="{{ $equipo->id }}" @if(optional($equipoSeleccionado)->id === $equipo->id) selected @endif>
                                {{ $equipo->nombre }} ({{ $equipo->ejercicioPolitico?->nombre }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-outline-secondary" type="submit">Aplicar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            @if($listado->isEmpty())
                <div class="p-4 text-muted">No hay miembros reportados aún.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Campaña</th>
                                <th>Equipo</th>
                                <th>Nombre</th>
                                <th>Cédula/NIT</th>
                                    <th>Foto</th>
                                    <th>Hoja de vida</th>
                                    <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($listado as $row)
                                <tr>
                                    <td>{{ $row['campania'] ?? '-' }}</td>
                                    <td>{{ $row['equipo'] ?? '-' }}</td>
                                    <td>{{ $row['persona']->nombre_contratista }}</td>
                                    <td>{{ $row['persona']->cedula_o_nit }}</td>
                                    <td>
                                        @if($row['persona']->foto)
                                            <a href="{{ asset('storage/'.$row['persona']->foto) }}" target="_blank">Ver</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($row['persona']->documento_pdf)
                                            <a href="{{ asset('storage/'.$row['persona']->documento_pdf) }}" target="_blank">Ver</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary mb-1" href="{{ route('reportar-equipo.edit', ['equipo_id' => $row['equipo_id'], 'persona_id' => $row['persona']->id]) }}">Editar</a>
                                        <form method="POST" action="{{ route('reportar-equipo.remove') }}" onsubmit="return confirm('¿Retirar este miembro del equipo?')">
                                            @csrf
                                            <input type="hidden" name="equipo_id" value="{{ $row['equipo_id'] }}">
                                            <input type="hidden" name="persona_id" value="{{ $row['persona']->id }}">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Retirar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
