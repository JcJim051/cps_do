@php
    $user = backpack_user();
    $canEdit = $user && $user->hasRole('bancos');
    $canView = $user && $user->hasAnyRole(['bancos','diana','admin']);
    $fase = $entry->fase_listado ?? 'inicial';
    $isAdicion = $fase === 'adicion';
    $currentValue = $isAdicion
        ? ($entry->estado_aprobacion_adicion ?? '')
        : ($entry->estado_aprobacion ?? '');
    $options = [
        ''      => '',
        'mayor' => 'Mayor',
        'menor' => 'Menor',
        'sin'   => 'Sin',
    ];
@endphp

@if ($canEdit)
    <form method="POST" action="{{ backpack_url('autorizacion/'.$entry->getKey().'/estado-aprobacion') }}">
        @csrf
        <input type="hidden" name="fase" value="{{ $fase }}">
        <select name="value" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:120px;">
            @foreach ($options as $val => $label)
                <option value="{{ $val }}" {{ $currentValue === $val ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </form>
@elseif ($canView)
    @php
        $map = [
            'mayor' => 'Mayor',
            'menor' => 'Menor',
            'sin'   => 'Sin',
        ];
        $label = $map[$currentValue] ?? '';
    @endphp
    <span>{{ $label }}</span>
@else
    <span class="text-muted">-</span>
@endif
