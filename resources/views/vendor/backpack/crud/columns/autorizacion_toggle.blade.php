@php
    $user = backpack_user();
    $fase = $entry->fase_listado ?? 'inicial';
    $isAdicion = $fase === 'adicion';
    $field = $column['name'] ?? null;

    $editable = false;
    if ($user) {
        if ($field === 'aut_despacho') {
            $editable = $user->hasAnyRole(['diana', 'admin']);
        } elseif ($field === 'aut_planeacion') {
            $editable = $user->hasAnyRole(['administrativa', 'bancos', 'diana', 'admin']);
        } elseif ($field === 'aut_administrativa') {
            $editable = $user->hasAnyRole(['administrativa', 'diana', 'admin']);
        }
    }

    $dbField = $field;
    if ($isAdicion) {
        $dbField .= '_adicion';
    }

    $checked = (bool) ($entry->{$dbField} ?? false);
@endphp

@if ($editable)
    <form method="POST" action="{{ backpack_url('autorizacion/'.$entry->getKey().'/toggle-autorizacion') }}">
        @csrf
        <input type="hidden" name="fase" value="{{ $fase }}">
        <input type="hidden" name="field" value="{{ $field }}">
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

