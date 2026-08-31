@extends(backpack_view('blank'))
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div><h2 class="mb-0">Sincronización Seguimientos ↔ SECOP</h2><small class="text-muted">Vista previa y aplicación controlada · vigencia 2026</small></div>
    <form method="GET" class="d-flex gap-2">
        <input type="hidden" name="anio" value="2026">
        <select name="secretaria_id" class="form-select"><option value="">Todas las entidades</option>@foreach($secretarias as $s)<option value="{{ $s->id }}" @selected(request('secretaria_id')==$s->id)>{{ $s->nombre }}</option>@endforeach</select>
        <select name="estado_contrato_id" class="form-select"><option value="">Todos los estados</option>@foreach($estados as $e)<option value="{{ $e->id }}" @selected(request('estado_contrato_id')==$e->id)>{{ $e->nombre }}</option>@endforeach</select>
        <input type="hidden" name="simular" value="1">
        <button class="btn btn-outline-primary text-nowrap"><i class="la la-flask"></i> Simular</button>
    </form>
</div>

@if($summary)
<div class="alert alert-info d-flex flex-wrap gap-3">
    <span><strong>{{ $summary['total'] }}</strong> consultados</span><span><strong>{{ $summary['actualizados'] }}</strong> con diferencias</span>
    <span><strong>{{ $summary['sin_cambios'] }}</strong> sin cambios</span><span><strong>{{ $summary['excluidos'] }}</strong> excluidos</span>
    <span><strong>{{ $summary['sin_datos'] }}</strong> sin datos</span><span><strong>{{ $summary['estados_desconocidos'] }}</strong> estados desconocidos</span>
    <span><strong>{{ $summary['errores'] }}</strong> errores</span>
</div>
@endif

<form method="POST" action="{{ route('secop.sync.apply') }}">@csrf
<div class="card"><div class="card-header d-flex justify-content-between align-items-center">
    <strong>Contratos vinculados</strong>
    <button class="btn btn-primary" onclick="return confirm('¿Aplicar a los Seguimientos seleccionados las diferencias mostradas por SECOP?')"><i class="la la-check"></i> Confirmar seleccionados</button>
</div><div class="table-responsive"><table class="table table-striped align-middle mb-0"><thead><tr>
    <th><input type="checkbox" id="all"></th><th>Persona / Seguimiento</th><th>Vínculo</th><th>SECOP observado</th><th>Último resultado</th><th>Diferencias simuladas</th>
</tr></thead><tbody>
@forelse($links as $link)
@php
    $row = $summary ? collect($summary['filas'])->firstWhere('vinculo_id',$link->id) : null;
    $snap = $link->ultimaInstantanea;
@endphp
<tr>
    <td><input class="pick" type="checkbox" name="ids[]" value="{{ $link->id }}" @checked(($row['resultado']??null)==='actualizable')></td>
    <td><strong>{{ $link->seguimiento?->persona?->nombre_contratista ?: '-' }}</strong><br><small>{{ $link->seguimiento?->persona?->cedula_o_nit }} · <a href="{{ backpack_url('seguimiento/'.$link->seguimiento_id.'/show') }}">Seguimiento #{{ $link->seguimiento_id }}</a></small></td>
    <td>{{ strtoupper($link->fuente_secop) }} · {{ $link->referencia_contrato ?: $link->identificador_externo }}<br><small>{{ $link->seguimiento?->secretaria?->nombre }}</small></td>
    <td>{{ $snap?->estado ?: 'Sin instantánea' }}<br><small>{{ $snap?->fecha_inicio?->format('d/m/Y') ?: '-' }} → {{ $snap?->fecha_fin?->format('d/m/Y') ?: '-' }} · {{ $snap?->valor_total !== null ? '$ '.number_format($snap->valor_total,0,',','.') : '-' }}</small></td>
    <td>@if($link->ultimo_error)<span class="badge bg-danger">Error</span><br><small class="text-danger">{{ Str::limit($link->ultimo_error,100) }}</small>@else<span class="badge bg-{{ $link->ultima_aplicacion_at?'success':'secondary' }}">{{ $link->ultima_aplicacion_at?'Sincronizado':'Pendiente' }}</span><br><small>{{ $link->ultima_aplicacion_at?->format('d/m/Y H:i') }}</small>@endif</td>
    <td class="small">@if($row)@forelse($row['cambios'] as $field=>$change)<div><strong>{{ $field }}</strong>: {{ $change['anterior'] ?? '∅' }} → {{ $change['nuevo'] ?? '∅' }}</div>@empty<span class="text-muted">{{ str_replace('_',' ',$row['resultado']) }}</span>@endforelse @foreach($row['alertas'] as $alert)<div class="text-warning"><i class="la la-exclamation-triangle"></i> {{ $alert }}</div>@endforeach @else<span class="text-muted">Pulsa Simular para consultar SECOP</span>@endif</td>
</tr>
@empty<tr><td colspan="6" class="text-center text-muted py-4">No hay Seguimientos 2026 vinculados.</td></tr>@endforelse
</tbody></table></div></div></form>
<script>document.getElementById('all')?.addEventListener('change',e=>document.querySelectorAll('.pick').forEach(x=>x.checked=e.target.checked));</script>
@endsection
