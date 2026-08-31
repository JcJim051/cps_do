@php
    $link = $entry->vinculoSecop;
    $origins = $link?->origenes_campos ?? [];
    $excluded = $link?->campos_excluidos ?? [];
    $labels = [
        'numero_contrato'=>'Número de contrato','anio'=>'Vigencia','estado_contrato_id'=>'Estado operativo',
        'fecha_acta_inicio'=>'Fecha de inicio','fecha_finalizacion'=>'Finalización inicial','tiempo_ejecucion_dias'=>'Días iniciales',
        'valor_total'=>'Valor inicial','adicion'=>'Indicador de adición','fecha_finalizacion_adicion'=>'Finalización vigente',
        'tiempo_ejecucion_dias_adicion'=>'Días efectivos de adición','tiempo_extension_secop_dias'=>'Extensión calendario SECOP',
        'tiempo_suspension_dias'=>'Días de suspensión','tiempo_total_ejecucion_dias'=>'Total de ejecución',
        'tiempo_total_calendario_dias'=>'Total calendario','valor_adicion'=>'Valor de adición','valor_total_contrato'=>'Valor vigente',
    ];
    $colors = ['SECOP'=>'primary','Manual'=>'warning','Derivado'=>'info','Convertido'=>'secondary'];
@endphp
@if($entry->tipo === 'contrato' && $link)
<div class="card mt-4 border-0 shadow-sm" style="border-radius:12px;overflow:hidden">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2" style="background:#f3edf9">
        <button class="btn btn-link text-start p-0 text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#secop-field-control-{{ $entry->id }}" aria-expanded="false" aria-controls="secop-field-control-{{ $entry->id }}">
            <strong style="color:#5f259f"><i class="la la-sliders-h"></i> Control de campos SECOP <i class="la la-angle-down"></i></strong><div class="small text-muted">Administración técnica · pulsa para desplegar</div>
        </button>
        <div><span class="badge {{ $excluded ? 'bg-warning text-dark' : 'bg-success' }}">{{ count($excluded) }} excluidos</span></div>
    </div>
    <div class="collapse" id="secop-field-control-{{ $entry->id }}"><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Campo</th><th>Origen actual</th><th>Actualización futura</th><th></th></tr></thead><tbody>
    @foreach(\App\Services\SecopAplicacionService::MANAGED_FIELDS as $field)
        @php $origin = $origins[$field] ?? 'Manual'; $isExcluded = in_array($field,$excluded,true); @endphp
        <tr class="{{ $isExcluded ? 'table-warning' : '' }}">
            <td><strong>{{ $labels[$field] ?? str_replace('_',' ',$field) }}</strong></td>
            <td><span class="badge bg-{{ $colors[$origin] ?? 'secondary' }}">{{ $origin }}</span></td>
            <td>{{ $isExcluded ? 'Bloqueada por edición manual' : 'Activa' }}</td>
            <td class="text-end">@if($isExcluded)<form method="POST" action="{{ route('seguimiento.secop.restore-field',[$entry,$field]) }}">@csrf<button class="btn btn-sm btn-outline-primary">Volver a tomar de SECOP</button></form>@endif</td>
        </tr>
    @endforeach
    </tbody></table></div></div>
</div>
@endif
