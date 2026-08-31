@php
    $metrics = $resultado['metricas'];
    $canManage = backpack_user()?->hasAnyRole(['admin', 'diana']);
    $badge = [
        'exacto' => 'success',
        'vinculado' => 'primary',
        'ambiguo' => 'warning',
        'conflicto' => 'danger',
        'requiere_revision' => 'warning',
        'requiere_configuracion' => 'warning',
        'documento_diferente' => 'danger',
        'sin_resultado' => 'secondary',
        'no_habilitado' => 'light',
    ];
    $money = fn ($value) => is_numeric($value) ? '$ '.number_format((float) $value, 0, ',', '.') : '-';
    $collapseId = 'conciliacion-secop-'.$resultado['persona']->id;
@endphp

<div class="persona-section" data-section="conciliacion_secop">
    <div class="card mt-3 persona-tone-card">
        <div class="card-header persona-tone-header persona-tone-header--purple d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong>Conciliación Seguimientos ↔ SECOP</strong>
                <div class="small opacity-75">Simulación por cédula. Ninguna sugerencia se aplica sin confirmación.</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge persona-tone-count">{{ $metrics['vinculados'] }} vinculados · {{ $metrics['revision'] }} por revisar</span>
                <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                    <i class="la la-balance-scale"></i> Abrir conciliación
                </button>
            </div>
        </div>

        <div id="{{ $collapseId }}" class="collapse">
        <div class="card-body p-0">
            @if(!($resultado['consulta_secop_disponible'] ?? true))
                <div class="alert alert-warning rounded-0 border-start-0 border-end-0 mb-0">
                    SECOP no respondió en este momento. Se muestran los vínculos y las últimas instantáneas guardadas; los seguimientos sin vincular requieren reintentar la consulta.
                </div>
            @endif
            @if($canManage && ($metrics['vinculados'] > 0 || $metrics['exactos'] > 0))
                <div class="px-3 py-2 border-bottom d-flex flex-wrap justify-content-end gap-2">
                    @if($metrics['vinculados'] > 0)
                        <form method="POST" action="{{ route('persona.secop.sync', $resultado['persona']) }}">@csrf
                            <button class="btn btn-primary btn-sm"><i class="la la-sync"></i> Sincronizar vinculados</button>
                        </form>
                    @endif
                    @if($metrics['exactos'] > 0)
                        <form method="POST" action="{{ route('persona.secop.link-exact', $resultado['persona']) }}">
                            @csrf
                            <button class="btn btn-outline-primary btn-sm" onclick="return confirm('¿Vincular las {{ $metrics['exactos'] }} coincidencias exactas de esta persona?')">
                                <i class="la la-link"></i> Vincular {{ $metrics['exactos'] }} exactas
                            </button>
                        </form>
                    @endif
                </div>
            @endif
            <div class="px-3 py-2 bg-light border-bottom d-flex flex-wrap gap-3 small">
                <span><strong>{{ $metrics['total'] }}</strong> seguimientos</span>
                <span class="text-success"><strong>{{ $metrics['exactos'] }}</strong> exactos</span>
                <span class="text-primary"><strong>{{ $metrics['vinculados'] }}</strong> vinculados</span>
                <span class="text-warning"><strong>{{ $metrics['revision'] }}</strong> por revisar</span>
                <span class="text-muted"><strong>{{ $metrics['sin_resultado'] }}</strong> sin resultado</span>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Seguimiento Integra</th>
                            <th>Contrato sugerido</th>
                            <th>Comparación</th>
                            <th>Resultado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($resultado['filas'] as $row)
                        @php
                            $tracking = $row['seguimiento'];
                            $candidate = $row['candidato'];
                            $plannedValue = $tracking->valor_total_contrato ?: $tracking->valor_total;
                            $observedValue = $candidate['valor_total_con_adiciones'] ?? null;
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ backpack_url('seguimiento/'.$tracking->id.'/show') }}"><strong>#{{ $tracking->id }}</strong></a>
                                · {{ $tracking->numero_contrato ?: 'Sin número' }}<br>
                                <small>{{ $tracking->anio ?: '-' }} · {{ $tracking->secretaria?->nombre ?: '-' }}</small><br>
                                <small>Planeado: {{ $money($plannedValue) }}</small>
                            </td>
                            <td>
                                @if($candidate)
                                    <strong>{{ $candidate['referencia_contrato'] ?? $candidate['identificador_externo'] }}</strong><br>
                                    <small>{{ $candidate['nombre_entidad'] ?? '-' }}</small><br>
                                    <small>{{ $candidate['fecha_inicio'] ?? '-' }} → {{ $candidate['fecha_fin'] ?? '-' }}</small><br>
                                    <small>SECOP: {{ $money($observedValue) }}</small>
                                @else
                                    <span class="text-muted">Sin candidato único</span>
                                @endif
                            </td>
                            <td class="small">
                                @forelse($row['razones'] as $reason)
                                    @php $warningReason = str_contains($reason, 'diferente') || str_contains($reason, 'pendiente'); @endphp
                                    <div><i class="la {{ $warningReason ? 'la-exclamation-triangle text-danger' : 'la-check text-success' }}"></i> {{ $reason }}</div>
                                @empty
                                    <span class="text-muted">Sin criterios suficientes</span>
                                @endforelse
                                @if($row['diferencia_valor_pct'] !== null)
                                    <div class="{{ abs($row['diferencia_valor_pct']) > 3 ? 'text-warning' : 'text-muted' }}">
                                        Valor: {{ $row['diferencia_valor_pct'] > 0 ? '+' : '' }}{{ number_format($row['diferencia_valor_pct'], 2, ',', '.') }}%
                                    </div>
                                @endif
                                @if($row['diferencia_fin_dias'] !== null && $row['diferencia_fin_dias'] !== 0)
                                    <div class="text-warning">Fin SECOP: {{ $row['diferencia_fin_dias'] > 0 ? '+' : '' }}{{ $row['diferencia_fin_dias'] }} días</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $badge[$row['estado']] ?? 'secondary' }} {{ $row['estado'] === 'no_habilitado' ? 'text-dark' : '' }}">
                                    {{ $row['estado_label'] }}
                                </span>
                                @if($row['cantidad_candidatos'] > 1)
                                    <div class="small text-muted mt-1">{{ $row['cantidad_candidatos'] }} candidatos</div>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if($canManage && $row['estado'] === 'exacto' && $candidate)
                                    <form method="POST" action="{{ route('seguimiento.secop.link', $tracking) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="fuente" value="{{ $candidate['fuente_codigo'] }}">
                                        <input type="hidden" name="identificador" value="{{ $candidate['identificador_externo'] }}">
                                        <button class="btn btn-sm btn-primary" onclick="return confirm('¿Vincular este contrato SECOP?')">Vincular</button>
                                    </form>
                                @elseif($canManage && $row['estado'] === 'vinculado')
                                    <form method="POST" action="{{ route('seguimiento.secop.unlink', $tracking) }}" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Desvincular este contrato SECOP?')">Desvincular</button>
                                    </form>
                                @elseif($canManage && in_array($row['estado'], ['ambiguo', 'requiere_revision', 'sin_resultado'], true))
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('seguimiento.secop.candidates', $tracking) }}">Revisar</a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            @if($resultado['contratos_sin_seguimiento']->isNotEmpty())
                <div class="px-3 py-2 border-top bg-light small">
                    <strong>Contratos SECOP sin Seguimiento conciliado:</strong>
                    {{ $resultado['contratos_sin_seguimiento']->pluck('referencia_contrato')->filter()->implode(', ') }}
                </div>
            @endif
        </div>
        </div>
    </div>
</div>
