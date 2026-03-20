@php
    $user = backpack_user();
    $canEdit = $user && $user->hasAnyRole(['bancos','diana','admin']);
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
        <select name="value" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:120px;">
            @foreach ($options as $val => $label)
                <option value="{{ $val }}" {{ ($entry->estado_aprobacion ?? '') === $val ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </form>
@else
    @php
        $map = [
            'mayor' => 'Mayor',
            'menor' => 'Menor',
            'sin'   => 'Sin',
        ];
        $label = $map[$entry->estado_aprobacion] ?? '';
    @endphp
    <span>{{ $label }}</span>
@endif
