@php
    $person = $entry->persona;
    $initials = collect(preg_split('/\s+/', trim((string) $person?->nombre_contratista)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $authorizations = collect([$entry->aut_despacho, $entry->aut_planeacion, $entry->aut_administrativa])->filter()->count();
@endphp

<style>
    .tracking-context{background:#fff;border:1px solid #e9e7f2;border-radius:12px;padding:10px 14px;box-shadow:0 3px 12px rgba(54,41,93,.05)}
    .tracking-context__avatar{width:44px;height:44px;border-radius:10px;object-fit:cover;background:#eee7f5;color:#5f259f;display:flex;align-items:center;justify-content:center;font-weight:700;flex:0 0 44px}
    .tracking-context__name{font-size:1rem;color:#292331}.tracking-context__meta{color:#756e7d;font-size:.78rem}
    .tracking-context__facts{display:flex;align-items:center;justify-content:flex-end;gap:7px;flex-wrap:wrap;margin-left:auto}
    .tracking-context__fact{background:#f7f4fa;border:1px solid #ebe4f1;border-radius:8px;padding:5px 9px;font-size:.76rem}.tracking-context__fact strong{display:block;color:#453652}
    @media(max-width:767px){.tracking-context__facts{width:100%;justify-content:flex-start;margin-left:0}}
</style>

<div class="col-12 mb-2">
    <div class="tracking-context d-flex flex-wrap align-items-center gap-2">
        @if($person?->foto)
            <img class="tracking-context__avatar" src="{{ asset('storage/'.$person->foto) }}" alt="Foto de {{ $person->nombre_contratista }}">
        @else
            <div class="tracking-context__avatar">{{ $initials ?: '—' }}</div>
        @endif
        <div>
            <a class="tracking-context__name fw-bold" href="{{ $person ? backpack_url('persona/'.$person->id.'/show') : '#' }}">{{ $person?->nombre_contratista ?: 'Persona sin asociar' }}</a>
            <div class="tracking-context__meta">{{ $person?->cedula_o_nit ?: 'Sin documento' }} · {{ $person?->celular ?: 'Sin celular' }}</div>
        </div>
        <div class="tracking-context__facts">
            <div class="tracking-context__fact"><span>Entidad</span><strong>{{ $entry->secretaria?->nombre ?: 'Sin dato' }}</strong></div>
            <div class="tracking-context__fact"><span>Gerencia</span><strong>{{ $entry->gerencia?->nombre ?: 'Sin dato' }}</strong></div>
            @if($entry->tipo === 'contrato')<div class="tracking-context__fact"><span>Ruta inicial</span><strong>{{ $authorizations }}/3 autorizaciones</strong></div>@endif
        </div>
    </div>
</div>
