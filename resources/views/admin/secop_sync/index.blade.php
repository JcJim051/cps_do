@extends(backpack_view('blank'))
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div><h2 class="mb-0">Sincronización Seguimientos ↔ SECOP</h2><small class="text-muted">Vista previa y aplicación controlada · vigencia 2026</small></div>
    <form method="GET" class="d-flex flex-wrap gap-2" id="secop-filters">
        <input type="hidden" name="anio" value="2026">
        <select name="secretaria_id" class="form-select"><option value="">Todas las entidades</option>@foreach($secretarias as $s)<option value="{{ $s->id }}" @selected(request('secretaria_id')==$s->id)>{{ $s->nombre }}</option>@endforeach</select>
        <select name="estado_contrato_id" class="form-select"><option value="">Todos los estados</option>@foreach($estados as $e)<option value="{{ $e->id }}" @selected(request('estado_contrato_id')==$e->id)>{{ $e->nombre }}</option>@endforeach</select>
        <input type="hidden" name="simular" value="1">
        <button class="btn btn-outline-primary text-nowrap"><i class="la la-flask"></i> Simular</button>
    </form>
    <button type="button" class="btn btn-primary text-nowrap" id="link-exact" @disabled($conciliationRun?->isActive())>
        <i class="la {{ $conciliationRun?->isActive() ? 'la-spinner la-spin' : 'la-link' }}"></i>
        <span>{{ $conciliationRun?->isActive() ? 'Proceso en segundo plano' : 'Vincular coincidencias exactas' }}</span>
    </button>
</div>

<div class="alert alert-info d-none" id="link-exact-progress" role="status"></div>

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
<script>
document.getElementById('all')?.addEventListener('change',e=>document.querySelectorAll('.pick').forEach(x=>x.checked=e.target.checked));

(() => {
    const button = document.getElementById('link-exact');
    const progress = document.getElementById('link-exact-progress');
    if (!button || !progress) return;
    let timer = null;

    button.addEventListener('click', async () => {
        if (!confirm('El servidor revisará en segundo plano los Seguimientos 2026 y vinculará únicamente coincidencias exactas de cédula, contrato, vigencia y entidad. Podrás salir de esta página. ¿Deseas continuar?')) return;

        const filters = document.getElementById('secop-filters');
        const secretaria = filters?.querySelector('[name="secretaria_id"]')?.value || '';
        const estado = filters?.querySelector('[name="estado_contrato_id"]')?.value || '';

        button.disabled = true;
        progress.className = 'alert alert-info';
        progress.textContent = 'Enviando la conciliación al servidor…';

        try {
            const response = await fetch(@json(route('secop.sync.link-exact')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': @json(csrf_token()),
                },
                body: JSON.stringify({
                    secretaria_id: secretaria || null,
                    estado_contrato_id: estado || null,
                }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'No fue posible iniciar la conciliación.');
            render(data.lote);
            schedulePoll();
        } catch (error) {
            progress.className = 'alert alert-danger';
            progress.textContent = error.message;
            button.disabled = false;
        }
    });

    async function poll() {
        try {
            const response = await fetch(@json(route('secop.sync.link-exact-status')), {
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();
            if (response.ok && data.lote) render(data.lote);
        } finally {
            if (button.disabled) schedulePoll();
        }
    }

    function schedulePoll() {
        clearTimeout(timer);
        if (button.disabled) timer = setTimeout(poll, 4000);
    }

    function render(run) {
        if (!run) return;
        const active = run.activo;
        const finished = run.estado === 'finalizado';
        progress.className = `alert ${finished ? (run.errores ? 'alert-warning' : 'alert-success') : (run.estado === 'fallido' ? 'alert-danger' : 'alert-info')}`;
        progress.innerHTML = `
            <div class="d-flex justify-content-between gap-3 mb-1">
                <strong>${statusLabel(run.estado)}</strong>
                <span>${run.porcentaje}%</span>
            </div>
            <div class="progress mb-2" style="height:8px"><div class="progress-bar" style="width:${run.porcentaje}%"></div></div>
            <div>${run.personas_procesadas} de ${run.total_personas} personas · ${run.coincidencias_exactas} coincidencias exactas · ${run.vinculos_creados} vínculos · ${run.errores} errores</div>
            <small>Última actualización: ${run.actualizado || '—'}. Puedes salir de esta página; el servidor continuará trabajando.</small>
            ${run.ultimo_error ? `<div class="mt-1 small text-danger">Último error: ${escapeHtml(run.ultimo_error)}</div>` : ''}`;
        button.disabled = active;
        button.querySelector('i').className = `la ${active ? 'la-spinner la-spin' : 'la-link'}`;
        button.querySelector('span').textContent = active ? 'Proceso en segundo plano' : 'Vincular coincidencias exactas';
    }

    function statusLabel(status) {
        return ({pendiente:'En cola', procesando:'Conciliación en curso', finalizado:'Conciliación finalizada', fallido:'Conciliación detenida'})[status] || status;
    }

    function escapeHtml(value) {
        const node = document.createElement('div');
        node.textContent = value ?? '';
        return node.innerHTML;
    }

    poll();
})();
</script>
@endsection
