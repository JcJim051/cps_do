@extends(backpack_view('blank'))

@php
    $s = $dashboard['summary'];
    $linked = $dashboard['linked'];
    $ratios = $dashboard['ratios'];
    $auth = $dashboard['authorizations'];
    $previous = $dashboard['previous'];
    $money = fn ($value) => '$ '.number_format((float) $value, 0, ',', '.');
    $moneyShort = function ($value) {
        $value = (float) $value;
        if (abs($value) >= 1_000_000_000) return '$ '.number_format($value / 1_000_000_000, 1, ',', '.').' mil M';
        if (abs($value) >= 1_000_000) return '$ '.number_format($value / 1_000_000, 1, ',', '.').' M';
        return '$ '.number_format($value, 0, ',', '.');
    };
    $pct = fn ($part, $total) => $total > 0 ? round(($part / $total) * 100, 1) : 0;
    $delta = function ($current, $old) {
        if (!$old) return null;
        return round((($current - $old) / $old) * 100, 1);
    };
    $contractsDelta = $delta((int) $s->total, (int) ($previous->total ?? 0));
    $valueDelta = $delta((float) $s->valor_total, (float) ($previous->valor_total ?? 0));
    $maxState = max(1, (int) ($dashboard['states']->max('total') ?? 1));
    $maxMonth = max(1, (int) ($dashboard['monthly']->max('contratos') ?? 1));
    $stateColors = [
        'CONTRATADO' => '#6f5bd3', 'LIQUIDADO' => '#2f9e78', 'SUSPENDIDO' => '#e6a23c',
        'APROBADO' => '#5b8def', 'PENDIENTE APROBACIÓN' => '#e8794f', 'CANCELADO' => '#d64f6c',
        'VALIDACION HV' => '#48a9a6', 'CAMBIO' => '#8d99ae', 'CEDE CONTRATO' => '#9b6ec8',
    ];
@endphp

@section('content')
<style>
    .executive-dashboard { --ink:#29263a; --muted:#716d82; --purple:#6f5bd3; --purple-soft:#f0edff; --line:#e8e5f0; color:var(--ink); }
    .executive-dashboard .hero { background:linear-gradient(120deg,#5541b8 0%,#7967db 62%,#9385e8 100%); color:#fff; border-radius:16px; padding:18px 22px; box-shadow:0 10px 30px rgba(71,54,158,.16); }
    .executive-dashboard .eyebrow { font-size:10px; font-weight:800; letter-spacing:.12em; text-transform:uppercase; opacity:.78; }
    .executive-dashboard .hero h1 { font-size:24px; line-height:1.1; margin:3px 0 5px; }
    .executive-dashboard .hero p { margin:0; font-size:12px; opacity:.84; }
    .executive-dashboard .year-form .form-select { min-width:104px; border:0; font-weight:700; }
    .executive-dashboard .year-form .btn { border-color:rgba(255,255,255,.45); color:#fff; }
    .executive-dashboard .year-form .btn:hover { background:#fff; color:var(--purple); }
    .executive-dashboard .metric-grid { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:10px; margin:12px 0; }
    .executive-dashboard .metric { background:#fff; border:1px solid var(--line); border-radius:13px; padding:13px 14px; min-height:112px; position:relative; overflow:hidden; box-shadow:0 3px 12px rgba(38,31,73,.04); }
    .executive-dashboard .metric::after { content:''; position:absolute; right:-18px; top:-22px; width:62px; height:62px; border-radius:50%; background:var(--tone,#f0edff); opacity:.75; }
    .executive-dashboard .metric-icon { width:28px; height:28px; display:grid; place-items:center; border-radius:8px; background:var(--tone,#f0edff); color:var(--accent,#6f5bd3); font-size:17px; margin-bottom:8px; }
    .executive-dashboard .metric-label { color:var(--muted); font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.055em; }
    .executive-dashboard .metric-value { font-size:21px; line-height:1.15; font-weight:800; margin:3px 0; white-space:nowrap; }
    .executive-dashboard .metric-note { font-size:10px; color:var(--muted); }
    .executive-dashboard .trend-up { color:#248565; } .executive-dashboard .trend-down { color:#c44f68; }
    .executive-dashboard .pulse { display:grid; grid-template-columns:repeat(4,1fr); background:#fff; border:1px solid var(--line); border-radius:12px; margin-bottom:12px; overflow:hidden; }
    .executive-dashboard .pulse-item { padding:10px 14px; border-right:1px solid var(--line); display:flex; align-items:center; gap:9px; }
    .executive-dashboard .pulse-item:last-child { border:0; }
    .executive-dashboard .pulse-dot { width:8px; height:8px; border-radius:50%; flex:0 0 auto; }
    .executive-dashboard .pulse strong { display:block; font-size:15px; line-height:1; }
    .executive-dashboard .pulse small { color:var(--muted); font-size:10px; }
    .executive-dashboard .panel { background:#fff; border:1px solid var(--line); border-radius:14px; box-shadow:0 3px 12px rgba(38,31,73,.035); height:100%; }
    .executive-dashboard .panel-head { display:flex; justify-content:space-between; align-items:flex-start; padding:14px 16px 10px; gap:12px; }
    .executive-dashboard .panel-title { font-size:14px; font-weight:800; margin:0; }
    .executive-dashboard .panel-subtitle { color:var(--muted); font-size:10px; margin-top:2px; }
    .executive-dashboard .panel-body { padding:4px 16px 15px; }
    .executive-dashboard .state-row { display:grid; grid-template-columns:minmax(120px,1.4fr) 3fr 42px; gap:10px; align-items:center; margin:9px 0; font-size:11px; }
    .executive-dashboard .bar-track { height:7px; background:#f0eef5; border-radius:20px; overflow:hidden; }
    .executive-dashboard .bar-fill { height:100%; border-radius:20px; min-width:3px; }
    .executive-dashboard .alert-list { display:grid; gap:8px; }
    .executive-dashboard .exec-alert { display:flex; gap:10px; padding:10px; border-radius:10px; background:#faf9fc; border:1px solid #efedf4; }
    .executive-dashboard .exec-alert i { font-size:19px; color:var(--alert,#d27b35); }
    .executive-dashboard .exec-alert strong { display:block; font-size:15px; }
    .executive-dashboard .exec-alert span { display:block; color:var(--muted); font-size:10px; line-height:1.25; }
    .executive-dashboard .month-chart { display:grid; grid-template-columns:repeat(12,1fr); gap:7px; height:145px; align-items:end; padding-top:8px; }
    .executive-dashboard .month-col { height:100%; display:flex; flex-direction:column; justify-content:flex-end; align-items:center; gap:4px; }
    .executive-dashboard .month-value { font-size:9px; color:var(--muted); }
    .executive-dashboard .month-bar { width:72%; min-height:3px; border-radius:6px 6px 2px 2px; background:linear-gradient(180deg,#8f80e6,#6552c7); }
    .executive-dashboard .month-label { font-size:9px; color:var(--muted); }
    .executive-dashboard .funnel { display:grid; gap:9px; }
    .executive-dashboard .funnel-row { display:grid; grid-template-columns:92px 1fr 48px; gap:8px; align-items:center; font-size:10px; }
    .executive-dashboard .funnel-track { height:24px; background:#f3f1f8; border-radius:6px; overflow:hidden; }
    .executive-dashboard .funnel-fill { height:100%; display:flex; align-items:center; padding-left:8px; color:#fff; font-weight:700; background:linear-gradient(90deg,#7764d7,#9a8ce8); min-width:2px; }
    .executive-dashboard .quality-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
    .executive-dashboard .quality-item { border:1px solid var(--line); border-radius:10px; padding:10px; }
    .executive-dashboard .quality-item strong { font-size:18px; }
    .executive-dashboard .quality-item span { display:block; font-size:10px; color:var(--muted); }
    .executive-dashboard .mini-progress { height:5px; background:#eeecf4; border-radius:8px; margin-top:7px; overflow:hidden; }
    .executive-dashboard .mini-progress div { height:100%; background:var(--purple); border-radius:8px; }
    .executive-dashboard .executive-table { font-size:10px; margin:0; }
    .executive-dashboard .executive-table thead th { color:#716d82; text-transform:uppercase; letter-spacing:.04em; font-size:9px; background:#faf9fc; border-bottom:1px solid var(--line); white-space:nowrap; }
    .executive-dashboard .executive-table td { vertical-align:middle; border-color:#efedf4; }
    .executive-dashboard .entity-name { max-width:220px; font-weight:700; line-height:1.2; }
    .executive-dashboard .coverage-chip { display:inline-block; padding:3px 7px; border-radius:10px; background:var(--purple-soft); color:#5946bd; font-weight:700; }
    .executive-dashboard .run-strip { border-radius:10px; padding:9px 12px; margin-bottom:12px; background:#f0edff; border:1px solid #ddd6ff; font-size:11px; }
    @media (max-width:1400px) { .executive-dashboard .metric-grid { grid-template-columns:repeat(3,1fr); } }
    @media (max-width:768px) { .executive-dashboard .metric-grid { grid-template-columns:repeat(2,1fr); } .executive-dashboard .pulse { grid-template-columns:repeat(2,1fr); } .executive-dashboard .pulse-item:nth-child(2){border-right:0}.executive-dashboard .pulse-item:nth-child(-n+2){border-bottom:1px solid var(--line)} .executive-dashboard .quality-grid{grid-template-columns:repeat(2,1fr)} }
</style>

<div class="executive-dashboard container-fluid px-0">
    <section class="hero d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="eyebrow">Tablero ejecutivo · Integra</div>
            <h1>Panorama contractual {{ $year }}</h1>
            <p>Contratación, ejecución, alertas y calidad de información en una sola lectura.</p>
        </div>
        <form class="year-form d-flex gap-2" method="GET">
            <select class="form-select form-select-sm" name="anio" onchange="this.form.submit()">
                @foreach($years as $availableYear)<option value="{{ $availableYear }}" @selected($year === $availableYear)>{{ $availableYear }}</option>@endforeach
            </select>
            <button class="btn btn-sm" name="actualizar" value="1" title="Actualizar cifras"><i class="la la-sync"></i> Actualizar</button>
        </form>
    </section>

    <section class="metric-grid">
        <article class="metric" style="--accent:#6f5bd3;--tone:#f0edff">
            <div class="metric-icon"><i class="la la-file-contract"></i></div><div class="metric-label">Intenciones / contratos</div>
            <div class="metric-value">{{ number_format($s->total, 0, ',', '.') }}</div>
            <div class="metric-note">{{ number_format($s->personas, 0, ',', '.') }} personas @if($contractsDelta !== null)· <span class="{{ $contractsDelta >= 0 ? 'trend-up' : 'trend-down' }}">{{ $contractsDelta >= 0 ? '+' : '' }}{{ $contractsDelta }}% vs. {{ $year-1 }}</span>@endif</div>
        </article>
        <article class="metric" style="--accent:#277a68;--tone:#e8f6f2" title="{{ $money($s->valor_total) }}">
            <div class="metric-icon"><i class="la la-wallet"></i></div><div class="metric-label">Valor contractual</div>
            <div class="metric-value">{{ $moneyShort($s->valor_total) }}</div>
            <div class="metric-note">@if($valueDelta !== null)<span class="{{ $valueDelta >= 0 ? 'trend-up' : 'trend-down' }}">{{ $valueDelta >= 0 ? '+' : '' }}{{ $valueDelta }}% vs. {{ $year-1 }}</span>@elseVigencia {{ $year }}@endif</div>
        </article>
        <article class="metric" style="--accent:#5278c7;--tone:#eaf0fc">
            <div class="metric-icon"><i class="la la-play-circle"></i></div><div class="metric-label">Contratados</div>
            <div class="metric-value">{{ number_format($s->contratados, 0, ',', '.') }}</div>
            <div class="metric-note">{{ $ratios['contratados'] }}% del total · {{ number_format($s->liquidados,0,',','.') }} liquidados</div>
        </article>
        <article class="metric" style="--accent:#8b62bd;--tone:#f3ecfa">
            <div class="metric-icon"><i class="la la-link"></i></div><div class="metric-label">Cobertura SECOP</div>
            <div class="metric-value">{{ $ratios['secop'] }}%</div>
            <div class="metric-note">{{ number_format($linked->total ?? 0,0,',','.') }} vinculados · {{ number_format($s->total-($linked->total ?? 0),0,',','.') }} pendientes</div>
        </article>
        <article class="metric" style="--accent:#cc7c2e;--tone:#fff3e5">
            <div class="metric-icon"><i class="la la-clock"></i></div><div class="metric-label">Duración promedio</div>
            <div class="metric-value">{{ number_format((float)$s->duracion_promedio, 0, ',', '.') }} días</div>
            <div class="metric-note">{{ number_format($s->vencen_30,0,',','.') }} finalizan en los próximos 30 días</div>
        </article>
        <article class="metric" style="--accent:#c34c68;--tone:#fdecef">
            <div class="metric-icon"><i class="la la-code-branch"></i></div><div class="metric-label">Adiciones</div>
            <div class="metric-value">{{ number_format($s->con_adicion,0,',','.') }}</div>
            <div class="metric-note">{{ $ratios['adiciones'] }}% · {{ $moneyShort($s->valor_adiciones) }} adicionados</div>
        </article>
    </section>

    <section class="pulse">
        <div class="pulse-item"><span class="pulse-dot" style="background:#e8794f"></span><div><strong>{{ number_format($s->por_gestionar,0,',','.') }}</strong><small>pendientes en ruta contractual</small></div></div>
        <div class="pulse-item"><span class="pulse-dot" style="background:#e6a23c"></span><div><strong>{{ number_format($s->suspendidos,0,',','.') }}</strong><small>contratos suspendidos</small></div></div>
        <div class="pulse-item"><span class="pulse-dot" style="background:#d64f6c"></span><div><strong>{{ number_format($s->vencidos_sin_cierre,0,',','.') }}</strong><small>vencidos sin cierre operativo</small></div></div>
        <div class="pulse-item"><span class="pulse-dot" style="background:#6f5bd3"></span><div><strong>{{ number_format($dashboard['pending_prevalidation'],0,',','.') }}</strong><small>aprobados aún en prevalidación</small></div></div>
    </section>

    @if($conciliationRun?->isActive())
        @php $runPct = $conciliationRun->total_personas ? round(($conciliationRun->personas_procesadas/$conciliationRun->total_personas)*100,1) : 0; @endphp
        <div class="run-strip d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span><i class="la la-spinner la-spin me-1"></i><strong>Conciliación SECOP en curso:</strong> {{ number_format($conciliationRun->personas_procesadas,0,',','.') }} de {{ number_format($conciliationRun->total_personas,0,',','.') }} personas · {{ number_format($conciliationRun->vinculos_creados,0,',','.') }} vínculos</span>
            <a href="{{ route('secop.sync.index') }}" class="btn btn-sm btn-outline-primary">Ver proceso · {{ $runPct }}%</a>
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-8"><section class="panel">
            <div class="panel-head"><div><h2 class="panel-title">Estado de la cartera contractual</h2><div class="panel-subtitle">Distribución operativa de los {{ number_format($s->total,0,',','.') }} registros de la vigencia</div></div></div>
            <div class="panel-body">
                @foreach($dashboard['states'] as $state)
                    @php $color = $stateColors[mb_strtoupper($state->nombre)] ?? '#9a93aa'; @endphp
                    <div class="state-row"><span>{{ $state->nombre }}</span><div class="bar-track"><div class="bar-fill" style="width:{{ ($state->total/$maxState)*100 }}%;background:{{ $color }}"></div></div><strong class="text-end">{{ number_format($state->total,0,',','.') }}</strong></div>
                @endforeach
            </div>
        </section></div>
        <div class="col-12 col-xl-4"><section class="panel">
            <div class="panel-head"><div><h2 class="panel-title">Alertas ejecutivas</h2><div class="panel-subtitle">Asuntos que requieren gestión o depuración</div></div></div>
            <div class="panel-body alert-list">
                <div class="exec-alert" style="--alert:#d64f6c"><i class="la la-calendar-times"></i><div><strong>{{ number_format($s->vencidos_sin_cierre,0,',','.') }}</strong><span>Fechas de terminación vencidas sin estado Liquidado o Cancelado.</span></div></div>
                <div class="exec-alert" style="--alert:#7b67d5"><i class="la la-unlink"></i><div><strong>{{ number_format($s->total-($linked->total ?? 0),0,',','.') }}</strong><span>Seguimientos todavía sin vínculo SECOP para actualización automática.</span></div></div>
                <div class="exec-alert" style="--alert:#d68a37"><i class="la la-file-alt"></i><div><strong>{{ number_format($s->sin_numero,0,',','.') }}</strong><span>Registros sin número de contrato; no pueden conciliarse de forma exacta.</span></div></div>
                <div class="exec-alert" style="--alert:#478f8c"><i class="la la-calendar-minus"></i><div><strong>{{ number_format($s->sin_fechas,0,',','.') }}</strong><span>Contratos sin fechas extremas completas.</span></div></div>
            </div>
        </section></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-7"><section class="panel">
            <div class="panel-head"><div><h2 class="panel-title">Inicio de contratos por mes</h2><div class="panel-subtitle">Ritmo de entrada a ejecución durante {{ $year }}</div></div><span class="coverage-chip">{{ number_format($auth->iniciados ?? 0,0,',','.') }} iniciados</span></div>
            <div class="panel-body"><div class="month-chart">
                @foreach($dashboard['monthly'] as $month)
                    <div class="month-col" title="{{ $month['mes'] }}: {{ $month['contratos'] }} contratos · {{ $money($month['valor']) }}"><span class="month-value">{{ $month['contratos'] ?: '' }}</span><div class="month-bar" style="height:{{ max(3,($month['contratos']/$maxMonth)*100) }}%"></div><span class="month-label">{{ $month['mes'] }}</span></div>
                @endforeach
            </div></div>
        </section></div>
        <div class="col-12 col-xl-5"><section class="panel">
            <div class="panel-head"><div><h2 class="panel-title">Embudo de autorizaciones</h2><div class="panel-subtitle">Avance acumulado frente al universo contractual</div></div></div>
            <div class="panel-body funnel">
                @foreach([['Autorización 1',$auth->aut1 ?? 0],['Autorización 2',$auth->aut2 ?? 0],['Autorización 3',$auth->aut3 ?? 0],['Acta de inicio',$auth->iniciados ?? 0]] as [$label,$value])
                    @php $rate=$pct($value,$s->total); @endphp
                    <div class="funnel-row"><span>{{ $label }}</span><div class="funnel-track"><div class="funnel-fill" style="width:{{ max(1,$rate) }}%">{{ $rate >= 18 ? $rate.'%' : '' }}</div></div><strong class="text-end">{{ number_format($value,0,',','.') }}</strong></div>
                @endforeach
            </div>
        </section></div>
    </div>

    <section class="panel mb-3">
        <div class="panel-head"><div><h2 class="panel-title">Desempeño por entidad</h2><div class="panel-subtitle">Volumen, valor, cobertura SECOP y comportamiento contractual</div></div><span class="coverage-chip">Top 12 por valor</span></div>
        <div class="table-responsive"><table class="table executive-table"><thead><tr><th>Entidad</th><th class="text-end">Contratos</th><th class="text-end">Personas</th><th class="text-end">Valor</th><th class="text-center">SECOP</th><th class="text-end">Adiciones</th><th class="text-end">Días prom.</th></tr></thead><tbody>
        @forelse($dashboard['secretarias'] as $entity)
            <tr><td><div class="entity-name">{{ $entity->nombre }}</div></td><td class="text-end fw-bold">{{ number_format($entity->contratos,0,',','.') }}</td><td class="text-end">{{ number_format($entity->personas,0,',','.') }}</td><td class="text-end" title="{{ $money($entity->valor) }}">{{ $moneyShort($entity->valor) }}</td><td class="text-center"><span class="coverage-chip">{{ $pct($entity->vinculados,$entity->contratos) }}%</span></td><td class="text-end">{{ number_format($entity->adiciones,0,',','.') }}</td><td class="text-end">{{ number_format((float)$entity->dias_promedio,0,',','.') }}</td></tr>
        @empty<tr><td colspan="7" class="text-center text-muted py-4">No hay información para esta vigencia.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-7"><section class="panel">
            <div class="panel-head"><div><h2 class="panel-title">Calidad y capacidad de actualización</h2><div class="panel-subtitle">Completitud mínima para operar y conciliar los seguimientos</div></div></div>
            <div class="panel-body quality-grid">
                @foreach([['Número de contrato',$ratios['numeros']],['Fechas extremas',$ratios['fechas']],['Valor contractual',$ratios['valores']],['Vinculación SECOP',$ratios['secop']]] as [$label,$rate])
                    <div class="quality-item"><strong>{{ $rate }}%</strong><span>{{ $label }}</span><div class="mini-progress"><div style="width:{{ $rate }}%"></div></div></div>
                @endforeach
            </div>
        </section></div>
        <div class="col-12 col-xl-5"><section class="panel">
            <div class="panel-head"><div><h2 class="panel-title">Cobertura organizacional</h2><div class="panel-subtitle">Personas y estructura de relacionamiento registradas</div></div></div>
            <div class="panel-body quality-grid">
                <div class="quality-item"><strong>{{ number_format($dashboard['people']['total'],0,',','.') }}</strong><span>Personas</span></div>
                <div class="quality-item"><strong>{{ number_format($dashboard['people']['en_equipos'],0,',','.') }}</strong><span>En equipos</span></div>
                <div class="quality-item"><strong>{{ number_format($dashboard['people']['equipos'],0,',','.') }}</strong><span>Equipos</span></div>
                <div class="quality-item"><strong>{{ number_format($dashboard['people']['campanias'],0,',','.') }}</strong><span>Ejercicios</span></div>
            </div>
        </section></div>
    </div>
</div>
@endsection
