@extends(backpack_view('blank'))
@section('content')
<div class="card mb-3 secop-toolbar">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div><h2 class="h4 mb-0">Sincronización Seguimientos ↔ SECOP</h2><small class="text-muted">Vista previa y aplicación controlada · vigencia 2026</small></div>
            <button type="button" class="btn btn-primary btn-sm text-nowrap" id="link-exact" @disabled($conciliationRun?->isActive())>
                <i class="la {{ $conciliationRun?->isActive() ? 'la-spinner la-spin' : 'la-link' }}"></i>
                <span>{{ $conciliationRun?->isActive() ? 'Conciliación en curso' : 'Vincular coincidencias exactas' }}</span>
            </button>
        </div>
        <form method="GET" class="row g-2 align-items-end" id="secop-filters">
            <input type="hidden" name="anio" value="2026">
            <div class="col-lg-5"><label class="form-label small mb-1">Entidad</label><select name="secretaria_id" class="form-select form-select-sm"><option value="">Todas las entidades</option>@foreach($secretarias as $s)<option value="{{ $s->id }}" @selected(request('secretaria_id')==$s->id)>{{ $s->nombre }}</option>@endforeach</select></div>
            <div class="col-lg-4"><label class="form-label small mb-1">Estado</label><select name="estado_contrato_id" class="form-select form-select-sm"><option value="">Todos los estados</option>@foreach($estados as $e)<option value="{{ $e->id }}" @selected(request('estado_contrato_id')==$e->id)>{{ $e->nombre }}</option>@endforeach</select></div>
            <input type="hidden" name="simular" value="1">
            <div class="col-lg-3"><button class="btn btn-outline-primary btn-sm w-100"><i class="la la-flask"></i> Simular diferencias</button></div>
        </form>
    </div>
</div>

<div class="alert alert-info {{ $conciliationRun?->isActive() ? '' : 'd-none' }} process-status" id="link-exact-progress" role="status"></div>

@if($summary)
<div class="alert alert-info d-flex flex-wrap gap-3">
    <span><strong>{{ $summary['total'] }}</strong> consultados</span><span><strong>{{ $summary['actualizados'] }}</strong> con diferencias</span>
    <span><strong>{{ $summary['sin_cambios'] }}</strong> sin cambios</span><span><strong>{{ $summary['excluidos'] }}</strong> excluidos</span>
    <span><strong>{{ $summary['sin_datos'] }}</strong> sin datos</span><span><strong>{{ $summary['estados_desconocidos'] }}</strong> estados desconocidos</span>
    <span><strong>{{ $summary['errores'] }}</strong> errores</span>
</div>
@endif

<div class="card border-primary mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <strong>Revisión bidireccional 2026</strong>
            <div class="small text-muted">{{ $auditRun?->criterio_vigencia_label ?: 'Ejecución activa durante 2026 · Solo entidades departamentales parametrizadas' }} · No crea Seguimientos.</div>
        </div>
        <div class="d-flex flex-wrap align-items-end gap-2">
            @if($auditRuns->isNotEmpty())
                <form method="GET"><input type="hidden" name="anio" value="2026"><select name="auditoria_lote" class="form-select form-select-sm" onchange="this.form.submit()">@foreach($auditRuns as $run)<option value="{{ $run->id }}" @selected($auditRun?->id === $run->id)>Lote #{{ $run->id }} · {{ $run->created_at?->format('d/m/Y H:i') }} · {{ $run->estado }}</option>@endforeach</select></form>
            @endif
            <div>
                <label for="audit-persona" class="form-label small mb-1">Nombre o cédula</label>
                <div class="input-group input-group-sm">
                    <input id="audit-persona" type="search" class="form-control" maxlength="120" placeholder="Ej. 1030590916" style="min-width:210px">
                    <button type="button" class="btn btn-primary" id="start-audit-persona" @disabled($latestAuditRun?->isActive() || $auditScope['faltantes'] !== [])>
                        <i class="la la-user-check"></i> Auditar persona
                    </button>
                </div>
            </div>
            <button type="button" class="btn btn-outline-primary" id="start-audit-all" @disabled($latestAuditRun?->isActive() || $auditScope['faltantes'] !== [])>
                <i class="la {{ $latestAuditRun?->isActive() ? 'la-spinner la-spin' : 'la-search' }}"></i>
                <span>{{ $latestAuditRun?->isActive() ? 'Auditoría en segundo plano' : 'Auditar todas' }}</span>
            </button>
        </div>
    </div>
    @if($auditScope['faltantes'] !== [])
        <div class="alert alert-warning rounded-0 border-start-0 border-end-0 mb-0 py-2 small">
            <i class="la la-exclamation-triangle"></i>
            <strong>La auditoría está bloqueada para evitar resultados incompletos.</strong>
            Falta el NIT SECOP de {{ count($auditScope['faltantes']) }} entidades incluidas:
            {{ collect($auditScope['faltantes'])->take(6)->implode(', ') }}{{ count($auditScope['faltantes']) > 6 ? '…' : '' }}
            <a href="{{ backpack_url('secretaria') }}" class="ms-1">Parametrizar entidades</a>
        </div>
    @else
        <div class="px-3 py-2 border-top small text-muted">
            <i class="la la-shield-alt text-success"></i>
            Alcance activo: {{ count($auditScope['secretaria_ids']) }} dependencias · {{ count($auditScope['nit_entidades']) }} NIT contratantes departamentales.
        </div>
    @endif
    <div class="card-body {{ $auditRun ? '' : 'd-none' }}" id="audit-progress"></div>
    @if($auditRun && !$auditRun->isActive())
        <div class="px-3 py-2 border-top small">
            @php
                $personasConsultadas = max(0, $auditRun->personas_procesadas - $auditRun->errores);
            @endphp
            <div class="mb-2">
                <strong>{{ $personasConsultadas }}</strong> personas consultadas
                · <strong>{{ $auditRun->total_personas - $personasConsultadas }}</strong> pendientes
                @if($auditRun->errores) · <strong class="text-danger">{{ $auditRun->errores }} sin respuesta de SECOP</strong> @endif
            </div>
            <div class="d-flex flex-wrap gap-3">
                <span class="text-danger"><strong>{{ $auditMetrics['solo_secop']['personas'] }}</strong> personas · <strong>{{ $auditMetrics['solo_secop']['registros'] }}</strong> contratos solo Datos Abiertos</span>
                <span class="text-warning"><strong>{{ $auditMetrics['solo_integra']['personas'] }}</strong> personas · <strong>{{ $auditMetrics['solo_integra']['registros'] }}</strong> Seguimientos solo Integra</span>
                <span><strong>{{ $auditMetrics['requiere_revision']['personas'] }}</strong> personas · <strong>{{ $auditMetrics['requiere_revision']['registros'] }}</strong> por revisar</span>
                <span class="text-success"><strong>{{ $auditMetrics['coincidencia_exacta']['personas'] }}</strong> personas · <strong>{{ $auditMetrics['coincidencia_exacta']['registros'] }}</strong> exactas</span>
                <span class="text-muted"><strong>{{ $auditMetrics['alerta_secop']['personas'] }}</strong> personas · <strong>{{ $auditMetrics['alerta_secop']['registros'] }}</strong> alertas SECOP</span>
            </div>
            @if($auditRun->ultimo_error)
                <details class="mt-2 text-danger">
                    <summary class="cursor-pointer">Ver detalle del último error</summary>
                    <div class="mt-1 text-break">{{ $auditRun->ultimo_error }}</div>
                </details>
            @endif
        </div>
    @endif
    @if($auditRun && !$auditRun->isActive())
        <div class="card-body border-top pb-2">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="anio" value="2026">
                <input type="hidden" name="auditoria_lote" value="{{ $auditRun->id }}">
                <div class="col-md-2"><label for="auditoria-tipo" class="form-label small mb-1">Resultado</label><select id="auditoria-tipo" name="auditoria_tipo" class="form-select form-select-sm">
                        <option value="">Todos</option><option value="solo_secop" @selected(request('auditoria_tipo') === 'solo_secop')>Solo Datos Abiertos</option><option value="solo_integra" @selected(request('auditoria_tipo') === 'solo_integra')>Solo Integra</option><option value="requiere_revision" @selected(request('auditoria_tipo') === 'requiere_revision')>Requiere revisión</option><option value="coincidencia_exacta" @selected(request('auditoria_tipo') === 'coincidencia_exacta')>Coincidencia exacta</option><option value="alerta_secop" @selected(request('auditoria_tipo') === 'alerta_secop')>Alerta SECOP</option>
                    </select></div>
                <div class="col-md-2"><label class="form-label small mb-1">Persona</label><input type="search" name="auditoria_persona" value="{{ request('auditoria_persona') }}" class="form-control form-control-sm" placeholder="Nombre o cédula"></div>
                <div class="col-md-3"><label class="form-label small mb-1">Entidad</label><select name="auditoria_entidad" class="form-select form-select-sm"><option value="">Todas</option>@foreach($auditEntities as $entity)<option value="{{ $entity }}" @selected(request('auditoria_entidad') === $entity)>{{ $entity }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label small mb-1">Estado SECOP</label><select name="auditoria_estado" class="form-select form-select-sm"><option value="">Todos</option>@foreach($auditStates as $state)<option value="{{ $state }}" @selected(request('auditoria_estado') === $state)>{{ $state }}</option>@endforeach</select></div>
                <div class="col-md-1"><label class="form-label small mb-1">Fuente</label><select name="auditoria_fuente" class="form-select form-select-sm"><option value="">Todas</option>@foreach($auditSources as $source)<option value="{{ $source }}" @selected(request('auditoria_fuente') === $source)>{{ strtoupper($source) }}</option>@endforeach</select></div>
                <div class="col-md-2 d-flex gap-1"><button class="btn btn-sm btn-primary flex-grow-1">Filtrar</button><a href="{{ route('secop.sync.index', ['anio' => 2026, 'auditoria_lote' => $auditRun->id]) }}" class="btn btn-sm btn-outline-secondary">Limpiar</a></div>
            </form>
        </div>
    @endif
    @if($auditRun && !$auditRun->isActive() && $auditFindings->isNotEmpty())
        <div class="table-responsive border-top">
            <table class="table table-sm table-striped align-middle mb-0">
                <thead><tr><th>Resultado</th><th>Persona</th><th>Integra</th><th>Datos Abiertos</th><th>Fechas / valor</th><th>Motivo</th></tr></thead>
                <tbody>
                @foreach($auditFindings as $finding)
                    @php
                        $appearance = match($finding->tipo) {
                            'solo_secop' => ['danger', 'SOLO DATOS ABIERTOS'],
                            'solo_integra' => ['warning', 'SOLO INTEGRA'],
                            'coincidencia_exacta' => ['success', 'COINCIDENCIA EXACTA'],
                            'alerta_secop' => ['secondary', 'ALERTA SECOP'],
                            default => ['secondary', 'REQUIERE REVISIÓN'],
                        };
                    @endphp
                    <tr>
                        <td><span class="badge bg-{{ $appearance[0] }}">{{ $appearance[1] }}</span></td>
                        <td><a href="{{ backpack_url('persona/'.$finding->persona_id.'/show') }}"><strong>{{ $finding->persona?->nombre_contratista ?: 'Persona #'.$finding->persona_id }}</strong></a><br><small>{{ $finding->persona?->cedula_o_nit }}</small></td>
                        <td>@if($finding->seguimiento_id)<a href="{{ backpack_url('seguimiento/'.$finding->seguimiento_id.'/show') }}">Seguimiento #{{ $finding->seguimiento_id }}</a><br><small>{{ $finding->seguimiento?->numero_contrato ?: 'Sin número' }}</small>@else<span class="text-danger">NO ESTÁ</span>@endif</td>
                        <td>{{ strtoupper($finding->fuente_secop ?: '—') }}<br><strong>{{ $finding->referencia_contrato ?: 'NO ENCONTRADO' }}</strong><br><small>{{ $finding->estado_secop }}</small></td>
                        <td>{{ $finding->fecha_inicio?->format('d/m/Y') ?: '-' }} → {{ $finding->fecha_fin?->format('d/m/Y') ?: '-' }}<br><small>{{ $finding->valor_total !== null ? '$ '.number_format((float)$finding->valor_total, 0, ',', '.') : '-' }}</small></td>
                        <td class="small">{{ collect($finding->detalle['razones'] ?? [])->implode(' · ') ?: ($finding->detalle['etiqueta'] ?? '-') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2 small text-muted">
            <span>Resultados del último barrido con los filtros seleccionados.</span>
            {{ $auditFindings->links() }}
        </div>
    @elseif($auditRun && !$auditRun->isActive())
        <div class="card-body border-top text-center text-muted">No hay hallazgos para los filtros seleccionados.</div>
    @endif
</div>

<form method="POST" action="{{ route('secop.sync.apply') }}">@csrf
<div class="card"><div class="card-header d-flex justify-content-between align-items-center">
    <div><strong>Contratos vinculados</strong> <span class="badge bg-light text-dark ms-1">{{ $links->count() }}</span></div>
    <button class="btn btn-primary" onclick="return confirm('¿Aplicar a los Seguimientos seleccionados las diferencias mostradas por SECOP?')"><i class="la la-check"></i> Confirmar seleccionados</button>
</div><div class="card-body pt-2"><div class="table-responsive"><table id="secop-links-table" class="table table-striped align-middle w-100"><thead><tr>
    <th><input type="checkbox" id="all"></th><th>Persona / Seguimiento</th><th>Vínculo</th><th>SECOP observado</th><th>Último resultado</th><th>Diferencias simuladas</th>
</tr></thead><tbody>
@forelse($links as $link)
@php
    $row = $summary ? collect($summary['filas'])->firstWhere('vinculo_id',$link->id) : null;
    $snap = $link->ultimaInstantanea;
@endphp
<tr>
    <td><input class="pick" type="checkbox" value="{{ $link->id }}" @checked(($row['resultado']??null)==='actualizable')></td>
    <td><strong>{{ $link->seguimiento?->persona?->nombre_contratista ?: '-' }}</strong><br><small>{{ $link->seguimiento?->persona?->cedula_o_nit }} · <a href="{{ backpack_url('seguimiento/'.$link->seguimiento_id.'/show') }}">Seguimiento #{{ $link->seguimiento_id }}</a></small></td>
    <td>{{ strtoupper($link->fuente_secop) }} · {{ $link->referencia_contrato ?: $link->identificador_externo }}<br><small>{{ $link->seguimiento?->secretaria?->nombre }}</small></td>
    <td>{{ $snap?->estado ?: 'Sin instantánea' }}<br><small>{{ $snap?->fecha_inicio?->format('d/m/Y') ?: '-' }} → {{ $snap?->fecha_fin?->format('d/m/Y') ?: '-' }} · {{ $snap?->valor_total !== null ? '$ '.number_format($snap->valor_total,0,',','.') : '-' }}</small></td>
    <td>@if($link->ultimo_error)<span class="badge bg-danger">Error</span><br><small class="text-danger">{{ Str::limit($link->ultimo_error,100) }}</small>@else<span class="badge bg-{{ $link->ultima_aplicacion_at?'success':'secondary' }}">{{ $link->ultima_aplicacion_at?'Sincronizado':'Pendiente' }}</span><br><small>{{ $link->ultima_aplicacion_at?->format('d/m/Y H:i') }}</small>@endif</td>
    <td class="small">@if($row)@forelse($row['cambios'] as $field=>$change)<div><strong>{{ $field }}</strong>: {{ $change['anterior'] ?? '∅' }} → {{ $change['nuevo'] ?? '∅' }}</div>@empty<span class="text-muted">{{ str_replace('_',' ',$row['resultado']) }}</span>@endforelse @foreach($row['alertas'] as $alert)<div class="text-warning"><i class="la la-exclamation-triangle"></i> {{ $alert }}</div>@endforeach @else<span class="text-muted">Pulsa Simular para consultar SECOP</span>@endif</td>
</tr>
@empty<tr><td colspan="6" class="text-center text-muted py-4">No hay Seguimientos 2026 vinculados.</td></tr>@endforelse
</tbody></table></div></div></div></form>
<script>
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
            <div class="d-flex justify-content-between align-items-center gap-3 mb-1">
                <strong>${statusLabel(run.estado)}</strong>
                <div><span class="me-2">${run.porcentaje}%</span>${active ? '' : '<button type="button" class="btn-close btn-close-sm" aria-label="Cerrar"></button>'}</div>
            </div>
            <div class="progress mb-2" style="height:8px"><div class="progress-bar" style="width:${run.porcentaje}%"></div></div>
            <div>${run.personas_procesadas} de ${run.total_personas} personas · ${run.coincidencias_exactas} coincidencias exactas · ${run.vinculos_creados} vínculos · ${run.errores} errores</div>
            <small>Última actualización: ${run.actualizado || '—'}.${active ? ' Puedes salir de esta página; el proceso continúa en el servidor.' : ''}</small>
            ${run.ultimo_error ? `<details class="mt-1 small text-danger"><summary>Ver detalle del último error</summary><div class="mt-1 text-break">${escapeHtml(run.ultimo_error)}</div></details>` : ''}`;
        progress.querySelector('.btn-close')?.addEventListener('click', () => progress.classList.add('d-none'));
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

    if (@json($conciliationRun?->isActive() ?? false)) poll();
})();

(() => {
    const personButton = document.getElementById('start-audit-persona');
    const allButton = document.getElementById('start-audit-all');
    const personInput = document.getElementById('audit-persona');
    const progress = document.getElementById('audit-progress');
    if (!personButton || !allButton || !progress) return;
    const viewingLatest = @json($auditRun?->id === $latestAuditRun?->id);
    const scopeReady = @json($auditScope['faltantes'] === []);
    let timer;

    personInput?.addEventListener('keydown', event => {
        if (event.key === 'Enter') { event.preventDefault(); personButton.click(); }
    });
    personButton.addEventListener('click', () => startAudit('persona'));
    allButton.addEventListener('click', () => startAudit('todas'));

    async function startAudit(scope) {
        const search = personInput?.value.trim() || '';
        if (scope === 'persona' && !search) {
            personInput?.focus();
            personInput?.classList.add('is-invalid');
            return;
        }
        personInput?.classList.remove('is-invalid');
        const question = scope === 'persona'
            ? `Se revisará únicamente la persona o las coincidencias de “${search}”. ¿Continuar?`
            : 'Se revisarán las más de 4.000 personas. Este barrido puede tomar varias horas. ¿Continuar?';
        if (!confirm(question)) return;
        setAuditButtons(true);
        progress.classList.remove('d-none');
        progress.textContent = 'Enviando auditoría al servidor…';
        try {
            const response = await fetch(@json(route('secop.audit.start')), {
                method:'POST',
                headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())},
                body: JSON.stringify({alcance: scope, persona: scope === 'persona' ? search : null}),
            });
            const data = await response.json();
            if (!response.ok) {
                const validation = data.errors ? Object.values(data.errors).flat()[0] : null;
                throw new Error(validation || data.message || 'No fue posible iniciar la auditoría.');
            }
            window.location.href = @json(route('secop.sync.index', ['anio' => 2026]));
        } catch (error) {
            progress.className = 'card-body border-top text-danger';
            progress.textContent = error.message;
            setAuditButtons(false);
        }
    }

    async function pollAudit() {
        try {
            const response = await fetch(@json(route('secop.audit.status')), {headers:{'Accept':'application/json'}});
            const data = await response.json();
            if (response.ok && data.lote) renderAudit(data.lote);
        } finally {
            if (allButton.disabled) timer = setTimeout(pollAudit, 5000);
        }
    }

    function renderAudit(run) {
        progress.className = 'card-body border-top';
        const queried = Math.max(0, run.personas_procesadas - run.errores);
        const pending = Math.max(0, run.total_personas - queried);
        const completedWithErrors = run.estado === 'finalizado' && run.errores > 0;
        const title = run.estado === 'fallido' || completedWithErrors
            ? 'Auditoría inconclusa'
            : run.estado === 'finalizado' ? 'Auditoría finalizada' : 'Auditoría en curso';
        const stateNote = run.activo
            ? 'Puedes salir de esta vista; el proceso continúa en el servidor.'
            : run.estado === 'fallido' || completedWithErrors
                ? 'SECOP no respondió para todas las personas. Los ceros no deben interpretarse como ausencia de contratos.'
                : 'Proceso completado.';
        progress.innerHTML = `<div class="d-flex justify-content-between"><strong>${title}</strong><span>${run.porcentaje}%</span></div><div class="progress my-2" style="height:8px"><div class="progress-bar" style="width:${run.porcentaje}%"></div></div><div><strong>${queried}</strong> personas consultadas · <strong>${pending}</strong> pendientes${run.errores ? ` · <strong class="text-danger">${run.errores} sin respuesta de SECOP</strong>` : ''}</div><div class="small">Solo Datos Abiertos: ${run.personas_solo_secop} personas · ${run.solo_secop} contratos | Solo Integra: ${run.personas_solo_integra} personas · ${run.solo_integra} Seguimientos | Por revisar: ${run.personas_requiere_revision} personas · ${run.requiere_revision} registros | Alertas: ${run.personas_alerta_secop} personas · ${run.alertas_secop} registros</div><small>${escapeAudit(run.criterio_vigencia)}. ${stateNote} Última actualización: ${run.actualizado || '—'}</small>${run.ultimo_error ? `<details class="small text-danger mt-1"><summary>Ver detalle del error</summary><div class="mt-1 text-break">${escapeAudit(run.ultimo_error)}</div></details>` : ''}`;
        setAuditButtons(run.activo);
        clearTimeout(timer);
        if (run.activo) timer = setTimeout(pollAudit, 5000);
    }

    function setAuditButtons(disabled) {
        personButton.disabled = disabled || !scopeReady;
        allButton.disabled = disabled || !scopeReady;
        allButton.querySelector('i').className = `la ${disabled ? 'la-spinner la-spin' : 'la-search'}`;
        allButton.querySelector('span').textContent = disabled ? 'Auditoría en segundo plano' : 'Auditar todas';
    }

    function escapeAudit(value) { const node = document.createElement('div'); node.textContent = value || ''; return node.innerHTML; }
    if (viewingLatest && @json($latestAuditRun?->isActive() ?? false)) pollAudit();
})();
</script>
@endsection

@section('after_styles')
    @basset('https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css')
    <style>
        .secop-toolbar, .process-status, .card { box-shadow: 0 1px 3px rgba(30, 41, 59, .06); }
        .process-status { font-size: .82rem; padding: .7rem .9rem; }
        .process-status details > summary, details > summary.cursor-pointer { cursor: pointer; }
        #secop-links-table { font-size: 9px; }
        #secop-links-table td, #secop-links-table th { padding: .42rem .5rem; vertical-align: middle; }
        #secop-links-table_wrapper .dataTables_filter input,
        #secop-links-table_wrapper .dataTables_length select { font-size: .78rem; }
        #secop-links-table_wrapper .dataTables_info,
        #secop-links-table_wrapper .pagination { font-size: .76rem; }
        .card-header { min-height: auto; }
    </style>
@endsection

@section('after_scripts')
    @basset('https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js')
    @basset('https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.jQuery?.fn?.DataTable) return;

        const tableElement = document.getElementById('secop-links-table');
        if (!tableElement || jQuery.fn.dataTable.isDataTable(tableElement)) return;

        const selected = new Set(
            Array.from(tableElement.querySelectorAll('.pick:checked')).map(input => input.value)
        );
        const table = jQuery(tableElement).DataTable({
            pageLength: 15,
            lengthMenu: [[15, 25, 50, 100], [15, 25, 50, 100]],
            order: [],
            columnDefs: [{targets: 0, orderable: false, searchable: false}],
            language: {
                search: 'Buscar:', searchPlaceholder: 'Persona, contrato, entidad…',
                lengthMenu: 'Mostrar _MENU_', info: '_START_–_END_ de _TOTAL_',
                infoEmpty: 'Sin registros', zeroRecords: 'No se encontraron coincidencias',
                paginate: {first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior'}
            },
        });
        const all = document.getElementById('all');

        function syncChecks() {
            table.rows().nodes().to$().find('.pick').each(function () { this.checked = selected.has(this.value); });
            const filtered = table.rows({search: 'applied'}).nodes().to$().find('.pick').toArray();
            const checked = filtered.filter(input => selected.has(input.value)).length;
            if (all) { all.checked = filtered.length > 0 && checked === filtered.length; all.indeterminate = checked > 0 && checked < filtered.length; }
        }

        jQuery(tableElement).on('change', '.pick', function () {
            this.checked ? selected.add(this.value) : selected.delete(this.value);
            syncChecks();
        });
        all?.addEventListener('change', function () {
            table.rows({search: 'applied'}).nodes().to$().find('.pick').each(function () {
                this.checked = all.checked;
                all.checked ? selected.add(this.value) : selected.delete(this.value);
            });
            syncChecks();
        });
        table.on('draw', syncChecks);
        tableElement.closest('form')?.addEventListener('submit', function () {
            this.querySelectorAll('input[data-selected-secop]').forEach(input => input.remove());
            selected.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = 'ids[]'; input.value = id;
                input.dataset.selectedSecop = '1'; this.appendChild(input);
            });
        });
        syncChecks();
    });
    </script>
@endsection
