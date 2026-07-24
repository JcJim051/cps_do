@php
    use Carbon\Carbon;

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

    $dateFieldMap = [
        'aut_despacho' => 'fecha_aut_despacho',
        'aut_planeacion' => 'fecha_aut_planeacion',
        'aut_administrativa' => 'fecha_aut_administrativa',
    ];
    $dateField = $dateFieldMap[$field] ?? null;
    if ($isAdicion && $dateField) {
        $dateField .= '_adicion';
    }

    $authorizedAt = $dateField ? ($entry->{$dateField} ?? null) : null;
    $formattedDate = null;
    if ($authorizedAt) {
        $formattedDate = $authorizedAt instanceof Carbon
            ? $authorizedAt->format('d/m/Y')
            : Carbon::parse($authorizedAt)->format('d/m/Y');
    }
    $tooltip = $checked && $formattedDate
        ? 'Autorizado el '.$formattedDate
        : 'Sin autorizar';
@endphp

@if ($editable)
    <form method="POST" action="{{ backpack_url('autorizacion/'.$entry->getKey().'/toggle-autorizacion') }}">
        @csrf
        <input type="hidden" name="fase" value="{{ $fase }}">
        <input type="hidden" name="field" value="{{ $field }}">
        <input type="hidden" name="value" value="0">
        <label
            style="cursor:pointer; display:inline-flex; align-items:center; gap:6px;"
            title="{{ $tooltip }}"
            data-bs-toggle="tooltip"
            data-bs-placement="top"
        >
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
    <span
        style="color:{{ $checked ? 'green' : 'red' }};"
        title="{{ $tooltip }}"
        data-bs-toggle="tooltip"
        data-bs-placement="top"
    >{!! $checked ? '✔' : '✖' !!}</span>
@endif
