@php
    $trackings = $entry->seguimientos;
    $contracts = $trackings->where('tipo', 'contrato');
    $currentContract = $contracts
        ->sortByDesc(fn ($item) => sprintf('%04d-%010d', (int) ($item->anio ?? 0), (int) $item->id))
        ->first(fn ($item) => !in_array(mb_strtoupper((string) ($item->estadoContrato?->nombre ?? '')), ['LIQUIDADO', 'CANCELADO'], true))
        ?? $contracts->sortByDesc('id')->first();
    $linkedContracts = $contracts->filter(fn ($item) => $item->vinculoSecop !== null)->count();
    $references = $entry->referencias->pluck('nombre')->filter();
    $money = fn ($value) => is_numeric($value) ? '$ '.number_format((float) $value, 0, ',', '.') : 'Sin dato';
    $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : 'Sin dato';
    $initials = collect(preg_split('/\s+/', trim((string) $entry->nombre_contratista)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $profileCollapse = 'perfil-complementario-'.$entry->id;
@endphp

<style>
    .person-overview{background:var(--tblr-bg-surface,#fff);border:1px solid var(--tblr-border-color,#e7e6e7);border-radius:14px;overflow:hidden;box-shadow:0 4px 14px rgba(42,47,54,.06)}
    .person-overview__hero{background:var(--tblr-primary,#7c69ef);color:#fff;padding:15px 18px;border-bottom:1px solid var(--tblr-primary,#7c69ef)}
    .person-avatar{width:68px;height:68px;border-radius:12px;object-fit:cover;border:2px solid rgba(255,255,255,.65);background:rgba(255,255,255,.16);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.35rem;font-weight:700;flex:0 0 68px}
    .person-overview__identity{min-width:220px}
    .person-overview__identity h3{font-size:1.35rem;line-height:1.2;margin:0 0 5px}
    .person-overview__meta{display:flex;flex-wrap:wrap;gap:5px 14px;font-size:.82rem;color:rgba(255,255,255,.9)}
    .person-overview__metrics{display:grid;grid-template-columns:repeat(3,minmax(82px,1fr));gap:7px;margin-left:auto}
    .person-stat{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.28);border-radius:9px;padding:7px 9px;text-align:center;min-width:82px;color:#fff}
    .person-stat strong{display:block;font-size:1.05rem;line-height:1.1}.person-stat small{font-size:.68rem;color:rgba(255,255,255,.82)}
    .person-overview__body{padding:12px 16px}
    .person-profile-tags{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:10px}
    .person-profile-tag{background:var(--tblr-primary-bg-subtle,#e5e1fc);border:1px solid var(--tblr-primary-border-subtle,#cbc3f9);color:var(--tblr-primary-text-emphasis,#322a60);border-radius:8px;padding:5px 9px;font-size:.78rem}
    .person-current-contract{display:grid;grid-template-columns:minmax(180px,1.4fr) repeat(3,minmax(110px,1fr)) auto;gap:10px;align-items:center;background:var(--tblr-light,#f8fafc);border:1px solid var(--tblr-border-color,#e7e6e7);border-radius:10px;padding:10px 12px}
    .person-current-contract__item small{display:block;color:#756e7d;font-size:.68rem;text-transform:uppercase;letter-spacing:.035em}.person-current-contract__item strong{color:#292331;font-size:.88rem}
    .person-profile-more{border-top:1px solid #eeeaf3;margin-top:10px;padding-top:8px}
    .person-profile-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;padding-top:8px}
    .person-profile-grid div{background:#faf9fc;border-radius:8px;padding:8px}.person-profile-grid small{display:block;color:#756e7d}.person-profile-grid strong{font-size:.85rem}
    .persona-tone-card{border:1px solid var(--tblr-border-color,#e7e6e7)!important;box-shadow:0 3px 10px rgba(42,47,54,.045)}
    .persona-tone-header{background:#f6f3fa!important;color:#512080!important;border-color:#e7ddf2!important}
    .persona-tone-header--purple{background:#efe4fa!important;color:#512080!important;border-color:#d4b8ed!important}
    .persona-tone-header--success{background:#eaf7f0!important;color:#17663a!important;border-color:#b9dfca!important}
    .persona-tone-header--warning{background:#fff7df!important;color:#6d5200!important;border-color:#f3d98a!important}
    .persona-tone-header .persona-tone-count{background:rgba(255,255,255,.68);border:1px solid currentColor;color:inherit}
    .persona-tone-header .btn-outline-light{color:inherit;border-color:currentColor;background:rgba(255,255,255,.45)}
    .persona-tone-header .btn-outline-light:hover{color:inherit;border-color:currentColor;background:rgba(255,255,255,.9)}
    @media(max-width:991px){.person-overview__metrics{margin-left:0;width:100%}.person-current-contract{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:575px){.person-avatar{width:52px;height:52px;flex-basis:52px}.person-overview__metrics{grid-template-columns:repeat(3,1fr)}.person-current-contract,.person-profile-grid{grid-template-columns:1fr}.person-current-contract__action{width:100%}}
</style>

<div class="persona-section" data-section="datos_persona">
    <div class="person-overview">
        <div class="person-overview__hero d-flex flex-wrap align-items-center gap-3">
            @if($entry->foto)
                <a href="{{ asset('storage/'.$entry->foto) }}" target="_blank"><img class="person-avatar" src="{{ asset('storage/'.$entry->foto) }}" alt="Foto de {{ $entry->nombre_contratista }}"></a>
            @else
                <div class="person-avatar">{{ $initials ?: '—' }}</div>
            @endif
            <div class="person-overview__identity">
                <div class="small opacity-75">Perfil de persona</div>
                <h3>{{ $entry->nombre_contratista ?: 'Sin nombre' }}</h3>
                <div class="person-overview__meta">
                    <span><i class="la la-id-card"></i> {{ $entry->cedula_o_nit ?: 'Sin documento' }}</span>
                    <span><i class="la la-phone"></i> {{ $entry->celular ?: 'Sin celular' }}</span>
                    @if($references->isNotEmpty())<span><i class="la la-user-tag"></i> {{ $references->implode(', ') }}</span>@endif
                </div>
            </div>
            <div class="person-overview__metrics">
                <div class="person-stat"><strong>{{ $trackings->count() }}</strong><small>Seguimientos</small></div>
                <div class="person-stat"><strong>{{ $contracts->count() }}</strong><small>Contratos</small></div>
                <div class="person-stat"><strong>{{ $linkedContracts }}</strong><small>Vinculados SECOP</small></div>
            </div>
        </div>

        <div class="person-overview__body">
            <div class="person-profile-tags">
                <span class="person-profile-tag"><strong>Nivel:</strong> {{ $entry->nivelAcademico?->nombre ?: 'Sin dato' }}</span>
                <span class="person-profile-tag"><strong>Perfil:</strong> {{ $entry->tecnico_tecnologo_profesion ?: 'Sin dato' }}</span>
                @if($entry->caso?->nombre)<span class="person-profile-tag"><strong>Caso:</strong> {{ $entry->caso->nombre }}</span>@endif
            </div>

            @if($currentContract)
                <div class="person-current-contract">
                    <div class="person-current-contract__item"><small>Contrato más reciente</small><strong>{{ $currentContract->numero_contrato ?: 'Sin número' }} · {{ $currentContract->anio ?: 'Sin vigencia' }}</strong></div>
                    <div class="person-current-contract__item"><small>Estado</small><strong>{{ $currentContract->estadoContrato?->nombre ?: 'Sin estado' }}</strong></div>
                    <div class="person-current-contract__item"><small>Entidad</small><strong>{{ $currentContract->secretaria?->nombre ?: 'Sin dato' }}</strong></div>
                    <div class="person-current-contract__item"><small>Fin / valor vigente</small><strong>{{ $date($currentContract->fecha_finalizacion_adicion ?: $currentContract->fecha_finalizacion) }} · {{ $money($currentContract->valor_total_contrato ?: $currentContract->valor_total) }}</strong></div>
                    <a class="btn btn-sm btn-primary person-current-contract__action" href="{{ backpack_url('seguimiento/'.$currentContract->id.'/show') }}">Abrir seguimiento</a>
                </div>
            @else
                <div class="alert alert-light border py-2 mb-0">Esta persona no tiene contratos registrados.</div>
            @endif

            <div class="person-profile-more d-flex flex-wrap justify-content-between align-items-center gap-2">
                <button class="btn btn-sm btn-link text-decoration-none p-0" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $profileCollapse }}" aria-expanded="false"><i class="la la-plus-circle"></i> Ver perfil complementario</button>
                @if($entry->documento_pdf)<a href="{{ asset('storage/'.$entry->documento_pdf) }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="la la-file-pdf"></i> Documento PDF</a>@endif
            </div>
            <div id="{{ $profileCollapse }}" class="collapse">
                <div class="person-profile-grid">
                    <div><small>Género</small><strong>{{ $entry->genero ?: 'Sin dato' }}</strong></div>
                    <div><small>Especialización</small><strong>{{ $entry->especializacion ?: 'Sin dato' }}</strong></div>
                    <div><small>Maestría</small><strong>{{ $entry->maestria ?: 'Sin dato' }}</strong></div>
                    @if(backpack_user()?->hasAnyRole(['admin', 'diana']))<div><small>Referencia 2</small><strong>{{ $entry->referencia_2 ?: 'Sin dato' }}</strong></div>@endif
                </div>
            </div>
        </div>
    </div>
</div>
