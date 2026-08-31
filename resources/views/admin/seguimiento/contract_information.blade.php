@php
    $money = fn ($value) => $value !== null ? '$ '.number_format((float) $value, 2, ',', '.') : 'Sin dato';
    $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : 'Sin dato';
    $days = fn ($value) => $value !== null ? number_format((int) $value, 0, ',', '.').' días' : 'Sin dato';
    $hasBreakdown = $entry->tiempo_extension_secop_dias !== null;
    $link = $entry->vinculoSecop;
    $snapshot = $link?->ultimaInstantanea;
    $secopEligible = filled($entry->numero_contrato) || ($entry->aut_despacho && $entry->aut_planeacion);
    $preview = $link && $snapshot ? app(\App\Services\SecopAplicacionService::class)->preview($link) : null;
@endphp

<style>
    .contract-overview{background:#fff;border:1px solid #e9e7f2;border-radius:14px;overflow:hidden;box-shadow:0 5px 20px rgba(54,41,93,.07)}
    .contract-overview__header{background:linear-gradient(120deg,#5f259f,#7b3fc0);color:#fff;padding:14px 18px}
    .contract-state-flow{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .contract-state-flow__state{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.28);border-radius:9px;padding:6px 10px;line-height:1.1}
    .contract-state-flow__state small{display:block;font-size:.65rem;text-transform:uppercase;letter-spacing:.04em;opacity:.76;margin-bottom:3px}
    .contract-state-flow__arrow{opacity:.8;font-size:1.1rem}
    .contract-overview__section{border:1px solid #eceaf3;border-radius:11px;height:100%;padding:12px 14px;background:#fff}
    .contract-overview__section h6{color:#5f259f;font-weight:700;margin-bottom:9px}
    .contract-metric{padding:5px 0;border-bottom:1px solid #f0eef5}
    .contract-metric:last-child{border-bottom:0}
    .contract-metric small{display:block;color:#6c757d;margin-bottom:2px}
    .contract-metric strong{font-size:1rem;color:#272332}
    .contract-timeline{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
    .contract-timeline__item{border-radius:10px;padding:12px;text-align:center;background:#f6f3fa;border:1px solid #e7ddf2}
    .contract-timeline__item.is-purple{background:#efe4fa;border-color:#d4b8ed;color:#512080}
    .contract-timeline__item.is-warning{background:#fff7df;border-color:#f3d98a;color:#6d5200}
    .contract-timeline__item.is-success{background:#eaf7f0;border-color:#b9dfca;color:#17663a}
    .contract-equation{background:#f7f4fb;border-left:4px solid #5f259f;border-radius:8px;padding:12px 15px}
    .contract-secoppanel{background:#f8fafc;border:1px solid #dfe7ef;border-radius:11px;padding:14px 16px}
    @media(max-width:767px){.contract-timeline{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>

<div class="col-12">
    <div class="contract-overview">
        <div class="contract-overview__header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="small opacity-75">Información del contrato</div>
                <h4 class="mb-0">{{ $entry->numero_contrato ?: 'Contrato sin número' }} · Vigencia {{ $entry->anio ?: 'sin definir' }}</h4>
            </div>
            <div class="contract-state-flow">
                <div class="contract-state-flow__state">
                    <small>Estado publicado en SECOP</small>
                    <strong>{{ $entry->estado_secop ?: 'Sin dato' }}</strong>
                </div>
                <span class="contract-state-flow__arrow" title="Integra actualiza el estado operativo al sincronizar"><i class="la la-arrow-right"></i></span>
                <div class="contract-state-flow__state">
                    <small>Estado operativo en Integra</small>
                    <strong>{{ $entry->estadoContrato?->nombre ?: 'Sin estado' }}</strong>
                </div>
                <span class="badge {{ $entry->adicion === 'SI' ? 'bg-warning text-dark' : 'bg-light text-dark' }}">{{ $entry->adicion === 'SI' ? 'Con adición' : 'Sin adición' }}</span>
            </div>
        </div>

        <div class="p-3">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="contract-overview__section">
                        <h6><i class="la la-building"></i> Identificación</h6>
                        <div class="contract-metric"><small>Entidad / Secretaría</small><strong>{{ $entry->secretaria?->nombre ?: 'Sin dato' }}</strong></div>
                        <div class="contract-metric"><small>Gerencia o programa</small><strong>{{ $entry->gerencia?->nombre ?: 'Sin dato' }}</strong></div>
                        <div class="contract-metric"><small>Fuente financiera</small><strong>{{ $entry->fuente?->nombre ?: 'Sin dato' }}</strong></div>
                        <div class="contract-metric"><small>Evaluación / continuidad</small><strong>{{ $entry->evaluacion?->nombre ?: 'Sin evaluación' }} · {{ $entry->continua ? 'Continúa' : 'No continúa' }}</strong></div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="contract-overview__section">
                        <h6><i class="la la-calendar"></i> Plazo inicial</h6>
                        <div class="contract-metric"><small>Acta de inicio</small><strong>{{ $date($entry->fecha_acta_inicio) }}</strong></div>
                        <div class="contract-metric"><small>Finalización inicial</small><strong>{{ $date($entry->fecha_finalizacion) }}</strong></div>
                        <div class="contract-metric"><small>Días iniciales de ejecución</small><strong>{{ $days($entry->tiempo_ejecucion_dias) }}</strong></div>
                        <div class="contract-metric"><small>Valor inicial</small><strong>{{ $money($entry->valor_total) }}</strong></div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="contract-overview__section">
                        <h6><i class="la la-coins"></i> Valores vigentes</h6>
                        <div class="contract-metric"><small>Valor mensual</small><strong>{{ $money($entry->valor_mensual) }}</strong></div>
                        <div class="contract-metric"><small>Valor de la adición</small><strong>{{ $money($entry->valor_adicion) }}</strong></div>
                        <div class="contract-metric"><small>Valor total vigente</small><strong>{{ $money($entry->valor_total_contrato) }}</strong></div>
                        <div class="contract-metric"><small>Finalización vigente</small><strong>{{ $date($entry->fecha_finalizacion_adicion ?: $entry->fecha_finalizacion) }}</strong></div>
                    </div>
                </div>
            </div>

            <div class="mt-3 contract-overview__section">
                <h6 class="mb-1"><i class="la la-stream"></i> Impacto temporal de la modificación</h6>
                <p class="small text-muted mb-3">Este bloque explica cómo cambió el plazo; no corresponde a una nueva ruta de autorización.</p>
                <div class="contract-timeline">
                    <div class="contract-timeline__item"><small>Plazo inicial</small><div class="fs-4 fw-bold">{{ $entry->tiempo_ejecucion_dias ?? '—' }}</div><small>días ejecutables</small></div>
                    <div class="contract-timeline__item is-success"><small>Ampliación ejecutable</small><div class="fs-4 fw-bold">{{ $entry->tiempo_ejecucion_dias_adicion ?? '—' }}</div><small>días adicionales de trabajo</small></div>
                    <div class="contract-timeline__item is-warning"><small>Suspensión</small><div class="fs-4 fw-bold">{{ $entry->tiempo_suspension_dias ?? '—' }}</div><small>días sin ejecución</small></div>
                    <div class="contract-timeline__item is-purple"><small>Extensión calendario</small><div class="fs-4 fw-bold">{{ $entry->tiempo_extension_secop_dias ?? '—' }}</div><small>total observado en SECOP</small></div>
                </div>
                @if($hasBreakdown)
                    <div class="contract-equation mt-3 d-flex flex-wrap justify-content-between gap-2">
                        <span><strong>{{ $entry->tiempo_extension_secop_dias }}</strong> días de extensión = <strong>{{ $entry->tiempo_ejecucion_dias_adicion ?? 0 }}</strong> de adición + <strong>{{ $entry->tiempo_suspension_dias ?? 0 }}</strong> de suspensión</span>
                        <span>Ejecución efectiva: <strong>{{ $entry->tiempo_total_ejecucion_dias ?? '—' }}</strong> · Calendario: <strong>{{ $entry->tiempo_total_calendario_dias ?? '—' }}</strong></span>
                    </div>
                @else
                    <div class="alert alert-light border mt-3 mb-0">La composición aparecerá después de sincronizar el contrato con SECOP.</div>
                @endif
            </div>

            <div class="contract-secoppanel mt-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h6 class="mb-1" style="color:#5f259f"><i class="la la-link"></i> Conexión y sincronización SECOP</h6>
                        @if($link)
                            <div><strong>{{ strtoupper($link->fuente_secop) }}</strong> · <span class="text-break">{{ $link->identificador_externo }}</span></div>
                            <small class="text-muted">Última consulta: {{ $snapshot?->consultado_at?->format('d/m/Y H:i') ?: 'pendiente' }} · Última aplicación: {{ $link->ultima_aplicacion_at?->format('d/m/Y H:i') ?: 'pendiente' }}</small>
                        @else
                            <div class="text-muted">Este Seguimiento todavía no tiene un contrato SECOP vinculado.</div>
                        @endif
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        @if($link)
                            <span class="badge {{ $link->sincronizacion_automatica ? 'bg-success' : 'bg-secondary' }}">Nocturna {{ $link->sincronizacion_automatica ? 'activa' : 'pausada' }}</span>
                            @if($snapshot?->url)<a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="{{ $snapshot->url }}">Abrir en SECOP</a>@endif
                            <form method="POST" action="{{ route('seguimiento.secop.refresh',$entry) }}">@csrf<button class="btn btn-sm btn-primary"><i class="la la-sync"></i> Sincronizar ahora</button></form>
                            <form method="POST" action="{{ route('seguimiento.secop.toggle-automatic',$entry) }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $link->sincronizacion_automatica ? 'Pausar nocturna' : 'Activar nocturna' }}</button></form>
                            <form method="POST" action="{{ route('seguimiento.secop.unlink',$entry) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Desvincular SECOP?')">Desvincular</button></form>
                        @elseif($secopEligible)
                            <a class="btn btn-sm btn-primary" href="{{ route('seguimiento.secop.candidates',$entry) }}">Buscar y vincular</a>
                        @else
                            <span class="badge bg-light text-dark border">Disponible después de autorizaciones 1 y 2</span>
                        @endif
                    </div>
                </div>
                @if($link?->ultimo_error)<div class="alert alert-danger mt-2 mb-0">{{ $link->ultimo_error }}</div>@endif
                @if($preview && (($preview['cambios'] ?? []) !== [] || ($preview['omitidos'] ?? []) !== []))
                    <div class="alert alert-info mt-2 mb-0"><strong>Diferencias pendientes:</strong>
                        @foreach($preview['cambios'] ?? [] as $field=>$change)<span class="d-block">{{ str_replace('_',' ',$field) }}: {{ $change['anterior'] ?? 'vacío' }} → {{ $change['nuevo'] ?? 'vacío' }}</span>@endforeach
                        @foreach($preview['omitidos'] ?? [] as $field=>$change)<span class="d-block text-warning">{{ str_replace('_',' ',$field) }}: omitido por edición manual</span>@endforeach
                    </div>
                @endif
            </div>

            @if($entry->observaciones_contrato)
                <div class="mt-3 contract-overview__section"><h6><i class="la la-comment"></i> Observaciones internas</h6><div>{{ $entry->observaciones_contrato }}</div></div>
            @endif
        </div>
    </div>
</div>
