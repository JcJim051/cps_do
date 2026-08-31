@php
    $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : 'Sin fecha';
    $initialSteps = [
        ['label' => 'Autorización 1', 'area' => 'Despacho', 'done' => (bool) $entry->aut_despacho, 'date' => $entry->fecha_aut_despacho],
        ['label' => 'Autorización 2', 'area' => 'Planeación', 'done' => (bool) $entry->aut_planeacion, 'date' => $entry->fecha_aut_planeacion],
        ['label' => 'Autorización 3', 'area' => 'Administrativa', 'done' => (bool) $entry->aut_administrativa, 'date' => $entry->fecha_aut_administrativa],
    ];
    $additionSteps = [
        ['label' => 'Autorización 1', 'area' => 'Despacho', 'done' => (bool) $entry->aut_despacho_adicion, 'date' => $entry->fecha_aut_despacho_adicion],
        ['label' => 'Autorización 2', 'area' => 'Planeación', 'done' => (bool) $entry->aut_planeacion_adicion, 'date' => $entry->fecha_aut_planeacion_adicion],
        ['label' => 'Autorización 3', 'area' => 'Administrativa', 'done' => (bool) $entry->aut_administrativa_adicion, 'date' => $entry->fecha_aut_administrativa_adicion],
    ];
    $initialDone = collect($initialSteps)->where('done', true)->count();
    $additionDone = collect($additionSteps)->where('done', true)->count();
@endphp

<style>
    .authorization-routes{margin-top:1rem}
    .authorization-routes__intro{display:flex;justify-content:space-between;align-items:end;gap:12px;flex-wrap:wrap;margin-bottom:10px}
    .authorization-routes__intro h5{color:#5f259f;margin:0;font-weight:700}
    .authorization-route{border:1px solid #e7e3ef;border-radius:13px;overflow:hidden;background:#fff;height:100%;box-shadow:0 3px 12px rgba(53,38,84,.05)}
    .authorization-route__header{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:12px 14px}
    .authorization-route--initial .authorization-route__header{background:#f1e9f9;color:#512080;border-bottom:1px solid #ddcaef}
    .authorization-route--addition{border-color:#efdca5}
    .authorization-route--addition .authorization-route__header{background:#fff4d6;color:#6d5200;border-bottom:1px solid #efdca5}
    .authorization-route__eyebrow{display:block;font-size:.67rem;text-transform:uppercase;letter-spacing:.055em;opacity:.72;margin-bottom:2px}
    .authorization-route__steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));padding:14px 10px 12px}
    .authorization-step{position:relative;text-align:center;padding:0 6px}
    .authorization-step:not(:last-child)::after{content:"";position:absolute;top:15px;left:calc(50% + 17px);right:calc(-50% + 17px);height:2px;background:#ddd7e5}
    .authorization-step.is-done:not(:last-child)::after{background:#8061a3}
    .authorization-route--addition .authorization-step.is-done:not(:last-child)::after{background:#d29b14}
    .authorization-step__number{position:relative;z-index:1;display:inline-flex;width:31px;height:31px;border-radius:50%;align-items:center;justify-content:center;background:#f0edf4;color:#786f82;border:2px solid #ddd7e5;font-weight:700}
    .authorization-step.is-done .authorization-step__number{background:#5f259f;border-color:#5f259f;color:#fff}
    .authorization-route--addition .authorization-step.is-done .authorization-step__number{background:#d49400;border-color:#d49400}
    .authorization-step strong{display:block;margin-top:7px;font-size:.88rem;color:#302a37}
    .authorization-step small{display:block;color:#756e7d;font-size:.73rem}
    .authorization-step__status{margin-top:5px}
    .authorization-route__empty{border:1px dashed #d9d3e2;border-radius:13px;background:#faf9fc;height:100%;padding:18px;display:flex;align-items:center;color:#6c6473}
    @media(max-width:575px){.authorization-route__steps{grid-template-columns:1fr;gap:12px}.authorization-step{display:grid;grid-template-columns:35px 1fr;column-gap:9px;text-align:left}.authorization-step:not(:last-child)::after{top:31px;bottom:-12px;left:21px;right:auto;width:2px;height:auto}.authorization-step strong,.authorization-step small,.authorization-step__status{grid-column:2;margin-top:0}}
</style>

<div class="col-12 authorization-routes">
    <div class="authorization-routes__intro">
        <div>
            <h5><i class="la la-route"></i> Rutas de autorización</h5>
            <small class="text-muted">Cada ruta corresponde a una decisión contractual diferente.</small>
        </div>
        @if($entry->adicion === 'SI')
            <span class="badge bg-warning text-dark">Existe una adición contractual</span>
        @endif
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <div class="authorization-route authorization-route--initial">
                <div class="authorization-route__header">
                    <div><span class="authorization-route__eyebrow">Ruta original</span><strong>Contrato inicial</strong></div>
                    <span class="badge bg-light text-dark">{{ $initialDone }}/3 aprobaciones</span>
                </div>
                <div class="authorization-route__steps">
                    @foreach($initialSteps as $index => $step)
                        <div class="authorization-step {{ $step['done'] ? 'is-done' : '' }}">
                            <span class="authorization-step__number">{{ $step['done'] ? '✓' : $index + 1 }}</span>
                            <strong>{{ $step['area'] }}</strong>
                            <small>{{ $step['label'] }}</small>
                            <div class="authorization-step__status"><span class="badge {{ $step['done'] ? 'bg-success' : 'bg-secondary' }}">{{ $step['done'] ? 'Aprobada' : 'Pendiente' }}</span></div>
                            <small>{{ $date($step['date']) }}</small>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            @if($entry->adicion === 'SI')
                <div class="authorization-route authorization-route--addition">
                    <div class="authorization-route__header">
                        <div><span class="authorization-route__eyebrow">Ruta posterior e independiente</span><strong>Adición contractual</strong></div>
                        <span class="badge bg-warning text-dark">{{ $additionDone }}/3 aprobaciones</span>
                    </div>
                    <div class="authorization-route__steps">
                        @foreach($additionSteps as $index => $step)
                            <div class="authorization-step {{ $step['done'] ? 'is-done' : '' }}">
                                <span class="authorization-step__number">{{ $step['done'] ? '✓' : $index + 1 }}</span>
                                <strong>{{ $step['area'] }}</strong>
                                <small>{{ $step['label'] }} de adición</small>
                                <div class="authorization-step__status"><span class="badge {{ $step['done'] ? 'bg-success' : 'bg-secondary' }}">{{ $step['done'] ? 'Aprobada' : 'Pendiente' }}</span></div>
                                <small>{{ $date($step['date']) }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="authorization-route__empty">
                    <div><strong><i class="la la-info-circle"></i> Sin ruta de adición</strong><div class="small mt-1">Este seguimiento no tiene una adición contractual registrada.</div></div>
                </div>
            @endif
        </div>
    </div>
</div>
