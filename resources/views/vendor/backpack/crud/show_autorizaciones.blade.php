@extends(backpack_view('blank'))

@section('content')
<div class="container my-4">

    <h2>Detalle de {{ $entry->nombre ?? 'Registro' }}</h2>

    {{-- ========================= --}}
    {{-- BLOQUES FIJOS DE AUTORIZACIONES --}}
    {{-- ========================= --}}
    <div class="mb-3 card">
        <div class="card-header bg-light">
            <strong>Autorización 2 - Bancos</strong>
        </div>
        <div class="card-body">
            @if(backpack_user()->hasRole('bancos'))
                <form method="POST" action="{{ url('admin/autorizaciones/'.$entry->id.'/update') }}">
                    @csrf
                    <input type="hidden" name="field" value="autorizacion_2">
                    <label>
                        <input type="checkbox" name="value" value="1" {{ $entry->autorizacion_2 ? 'checked' : '' }} onchange="this.form.submit()">
                        Marcar como aprobado
                    </label>
                </form>
            @else
                {!! $entry->autorizacion_2 ? '✅ Aprobado' : '❌ Pendiente' !!}
            @endif
        </div>
    </div>

    <div class="mb-3 card">
        <div class="card-header bg-light">
            <strong>Autorización 3 - Administrativa</strong>
        </div>
        <div class="card-body">
            @if(backpack_user()->hasRole('administrativa'))
                <form method="POST" action="{{ url('admin/autorizaciones/'.$entry->id.'/update') }}">
                    @csrf
                    <input type="hidden" name="field" value="autorizacion_3">
                    <label>
                        <input type="checkbox" name="value" value="1" {{ $entry->autorizacion_3 ? 'checked' : '' }} onchange="this.form.submit()">
                        Marcar como aprobado
                    </label>
                </form>
            @else
                {!! $entry->autorizacion_3 ? '✅ Aprobado' : '❌ Pendiente' !!}
            @endif
        </div>
    </div>

    {{-- ========================= --}}
    {{-- CAMPOS NORMALES DEL SHOW --}}
    {{-- ========================= --}}
    <div class="card">
        <div class="card-header bg-light">
            <strong>Información General</strong>
        </div>
        <div class="card-body">

            @foreach($crud->columns() as $column)
                <div class="mb-2 row">
                    <div class="col-md-4 text-muted">{{ $column['label'] ?? $column['name'] }}</div>
                    <div class="col-md-8">{!! $crud->getModelAttributeValue($entry, $column['name']) !!}</div>
                </div>
            @endforeach

        </div>
    </div>

</div>
@endsection
