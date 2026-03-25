@extends(backpack_view('blank'))

@section('content')
<div class="container-fluid">
    <div class="row mb-2">
        <div class="col-md-6">
            <h3>Reportar miembro</h3>
        </div>
        <div class="col-md-6 text-end">
            <a href="{{ route('reportar-equipo.index') }}" class="btn btn-secondary">Volver</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('reportar-equipo.buscar') }}" class="row g-2">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">Equipo</label>
                    <select name="equipo_id" class="form-control" required>
                        <option value="">Seleccione</option>
                        @foreach($equipos as $equipo)
                            <option value="{{ $equipo->id }}" @if($equipoId == $equipo->id) selected @endif>
                                {{ $equipo->nombre }} ({{ $equipo->ejercicioPolitico?->nombre }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cédula/NIT</label>
                    <input type="text" name="cedula_o_nit" class="form-control" value="{{ old('cedula_o_nit', $cedula) }}" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button class="btn btn-outline-primary" type="submit">Buscar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ $isEdit ? route('reportar-equipo.update') : route('reportar-equipo.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="equipo_id" value="{{ $equipoId }}">
                @if($isEdit)
                    <input type="hidden" name="persona_id" value="{{ $personaId }}">
                @endif

                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre_contratista" class="form-control" required value="{{ old('nombre_contratista', $personaData['nombre_contratista'] ?? '') }}" @if(!empty($personaData)) readonly @endif>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Cédula/NIT</label>
                        <input type="text" name="cedula_o_nit" class="form-control" value="{{ old('cedula_o_nit', $personaData['cedula_o_nit'] ?? $cedula) }}" required @if($isEdit) readonly @endif>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Celular</label>
                        <input type="text" name="celular" class="form-control" required value="{{ old('celular', $personaData['celular'] ?? '') }}">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Género</label>
                        <select name="genero" class="form-control" required>
                            <option value="">Seleccione</option>
                            <option value="Masculino" @if((old('genero', $personaData['genero'] ?? '') === 'Masculino')) selected @endif>Masculino</option>
                            <option value="Femenino" @if((old('genero', $personaData['genero'] ?? '') === 'Femenino')) selected @endif>Femenino</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Nivel académico</label>
                        <select name="nivel_academico_id" class="form-control" required>
                            <option value="">Seleccione</option>
                            @foreach($niveles as $nivel)
                                <option value="{{ $nivel->id }}" @if((old('nivel_academico_id', $personaData['nivel_academico_id'] ?? null) == $nivel->id)) selected @endif>
                                    {{ $nivel->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Profesión / Técnico / Tecnólogo</label>
                        <input type="text" name="tecnico_tecnologo_profesion" class="form-control" required value="{{ old('tecnico_tecnologo_profesion', $personaData['tecnico_tecnologo_profesion'] ?? '') }}">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Especialización</label>
                        <input type="text" name="especializacion" class="form-control" required value="{{ old('especializacion', $personaData['especializacion'] ?? '') }}">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Maestría</label>
                        <input type="text" name="maestria" class="form-control" required value="{{ old('maestria', $personaData['maestria'] ?? '') }}">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Priorización (1 a 3)</label>
                        <select name="priorizacion" class="form-control" required>
                            @php
                                $priorizacionValue = old('priorizacion', $personaData['priorizacion'] ?? null);
                            @endphp
                            <option value="" disabled @if($priorizacionValue === null || $priorizacionValue === '') selected @endif>Seleccione</option>
                            @for($i=1; $i<=3; $i++)
                                <option value="{{ $i }}" @if($priorizacionValue == $i) selected @endif>{{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Foto</label>
                        <input type="file" name="foto" class="form-control" @if(!$isEdit) required @endif>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Hoja de vida (PDF)</label>
                        <input type="file" name="documento_pdf" class="form-control">
                    </div>
                </div>

                <button class="btn btn-primary mt-2" type="submit">{{ $isEdit ? 'Actualizar' : 'Guardar' }}</button>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        // Sin lógica adicional por ahora.
    });
</script>
@endsection
