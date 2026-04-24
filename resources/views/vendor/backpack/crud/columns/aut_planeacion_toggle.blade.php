@php
    $user = backpack_user();
    $canToggle = $user && $user->hasAnyRole(['administrativa', 'bancos', 'diana', 'admin']);
    $fase = $entry->fase_listado ?? 'inicial';
    $isAdicion = $fase === 'adicion';
    $checked = $isAdicion ? $entry->aut_planeacion_adicion : $entry->aut_planeacion;
@endphp

@if ($canToggle)
    <form method="POST" action="{{ backpack_url('autorizacion/'.$entry->getKey().'/toggle-planeacion') }}">
        @csrf
        <input type="hidden" name="fase" value="{{ $fase }}">
        <input type="hidden" name="value" value="0">
        <label style="cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
            <input
                type="checkbox"
                name="value"
                value="1"
                {{ $checked ? 'checked' : '' }}
                onchange="this.form.submit()"
            >
            <span style="font-size:12px;">{{ $checked ? 'Autorizado' : 'No' }}</span>
        </label>
    </form>
@else
    {!! $checked ? '<span style="color:green;">✔</span>' : '<span style="color:red;">✖</span>' !!}
@endif
