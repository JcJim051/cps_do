@php
    $user = backpack_user();
    $canToggle = $user && $user->hasRole('bancos');
@endphp

@if ($canToggle)
    <form method="POST" action="{{ backpack_url('autorizacion/'.$entry->getKey().'/toggle-planeacion') }}">
        @csrf
        <input type="hidden" name="value" value="0">
        <label style="cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
            <input
                type="checkbox"
                name="value"
                value="1"
                {{ $entry->aut_planeacion ? 'checked' : '' }}
                onchange="this.form.submit()"
            >
            <span style="font-size:12px;">{{ $entry->aut_planeacion ? 'Autorizado' : 'No' }}</span>
        </label>
    </form>
@else
    {!! $entry->aut_planeacion ? '<span style="color:green;">✔</span>' : '<span style="color:red;">✖</span>' !!}
@endif
