@extends(backpack_view('blank'))
@section('content')
<div class="d-flex justify-content-between mb-3"><h2>Editar prevalidación</h2><a class="btn btn-outline-secondary" href="{{ route('prevalidacion.show',$prevalidacion) }}">Volver</a></div>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('prevalidacion.update',$prevalidacion) }}">@csrf @method('PUT')
<div class="card"><div class="card-body"><div class="row g-3">
    <div class="col-12"><label class="form-label">Nombre reportado en Drive</label><textarea class="form-control" rows="2" readonly>{{ $prevalidacion->nombre_reportado }}</textarea>@if($prevalidacion->nombre_requiere_revision)<small class="text-warning">Contiene texto adicional. La cédula corresponde a la persona que ingresa.</small>@endif</div>
    <div class="col-md-6"><label class="form-label">Nombre normalizado para Integra</label><input class="form-control" name="nombre_contratista" value="{{ old('nombre_contratista',$prevalidacion->nombre_contratista) }}" required><small class="form-text text-muted">Este es el nombre que se usará al crear o asociar la persona.</small></div>
    <div class="col-md-3"><label class="form-label">Estado</label><select class="form-select" name="estado">@foreach(\App\Models\PrevalidacionContractual::ESTADOS as $estado)<option @selected(old('estado',$prevalidacion->estado)===$estado)>{{ $estado }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Año</label><input type="number" class="form-control" name="anio" value="{{ old('anio',$prevalidacion->anio) }}"></div>
    <div class="col-md-6"><label class="form-label">Gerencia homologada</label><select class="form-select" name="gerencia_id"><option value="">Sin homologar</option>@foreach($gerencias as $g)<option value="{{ $g->id }}" @selected((string)old('gerencia_id',$prevalidacion->gerencia_id)===(string)$g->id)>{{ $g->nombre }}</option>@endforeach</select></div>
    <div class="col-md-6"><label class="form-label">Programa</label><input class="form-control" name="programa_origen" value="{{ old('programa_origen',$prevalidacion->programa_origen) }}"></div>
    <div class="col-md-4"><label class="form-label">Número planeado</label><input class="form-control" name="numero_contrato_planeado" value="{{ old('numero_contrato_planeado',$prevalidacion->numero_contrato_planeado) }}"></div>
    <div class="col-md-4"><label class="form-label">Inicio planeado</label><input type="date" class="form-control" name="fecha_inicio_planeada" value="{{ old('fecha_inicio_planeada',$prevalidacion->fecha_inicio_planeada?->format('Y-m-d')) }}"></div>
    <div class="col-md-4"><label class="form-label">Fin planeado</label><input type="date" class="form-control" name="fecha_fin_planeada" value="{{ old('fecha_fin_planeada',$prevalidacion->fecha_fin_planeada?->format('Y-m-d')) }}"></div>
    <div class="col-md-4"><label class="form-label">Tiempo planeado (días)</label><input type="number" class="form-control" name="tiempo_planeado_dias" value="{{ old('tiempo_planeado_dias',$prevalidacion->tiempo_planeado_dias) }}"></div>
    <div class="col-md-4"><label class="form-label">Valor mensual</label><input type="number" step="0.01" class="form-control" name="valor_mensual_planeado" value="{{ old('valor_mensual_planeado',$prevalidacion->valor_mensual_planeado) }}"></div>
    <div class="col-md-4"><label class="form-label">Valor total</label><input type="number" step="0.01" class="form-control" name="valor_total_planeado" value="{{ old('valor_total_planeado',$prevalidacion->valor_total_planeado) }}"></div>
    <div class="col-12"><label class="form-label">Observaciones</label><textarea class="form-control" name="observaciones" rows="3">{{ old('observaciones',$prevalidacion->observaciones) }}</textarea></div>
</div></div><div class="card-footer"><button class="btn btn-primary">Guardar y notificar al cuadro</button></div></div></form>
@endsection
