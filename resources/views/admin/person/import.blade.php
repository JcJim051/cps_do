@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>
            <span class="text-capitalize">Importar Personas</span>
        </h2>
    </section>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            
            {{-- ENLACE DE DESCARGA DE PLANTILLA --}}
            <div class="alert alert-info" role="alert">
                Para evitar errores, descarga y utiliza la plantilla oficial.
                <a href="{{ route('person.downloadTemplate') }}" class="btn btn-xs btn-primary float-end">
                    <i class="la la-download"></i> Descargar Plantilla Modelo (.xlsx)
                </a>
            </div>
            
            {{-- FORMULARIO DE SUBIDA --}}
            <form method="POST" action="{{ route('person.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="p-4 card">
                    <label for="file">Seleccionar archivo CSV o Excel</label>
                    <input type="file" name="file" required class="mb-3 form-control">
                    <button type="submit" class="btn btn-success">Cargar y Crear Registros</button>
                </div>
            </form>

            {{-- LISTADO DE ERRORES DE IMPORTACIÓN (opcional) --}}
            @if (!empty($importFailures))
                <div class="mt-4 card">
                    <div class="text-white card-header bg-danger">
                        <i class="la la-warning"></i> Fallos de Importación ({{ count($importFailures) }} filas omitidas)
                    </div>
                    <div class="p-0 card-body">
                        <div class="table-responsive">
                            <table class="table mb-0 table-striped table-condensed">
                                <thead>
                                    <tr>
                                        <th>Fila</th>
                                        <th>Cédula/NIT</th>
                                        <th>Errores</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($importFailures as $failure)
                                        <tr class="{{ $loop->iteration % 2 === 0 ? 'bg-light' : '' }}"> 
                                            <td>{{ $failure['row'] }}</td>
                                            <td>{{ $failure['cedula'] ?? 'N/A' }}</td>
                                            <td>
                                                <ul class="mb-0 ps-3">
                                                    @foreach ((array)$failure['errors'] as $error)
                                                        <li>{{ is_array($error) ? implode('; ', $error) : $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
@endsection
