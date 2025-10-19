<!DOCTYPE html>
<html>
<head>
    <title>Imprimir Seguimiento</title>
    {{-- Sólo la hoja de estilo de Bootstrap de Backpack para mantener el diseño --}}
    <link rel="stylesheet" href="{{ asset('packages/backpack/crud/css/crud.css') }}">
    <style>
        body { background: white !important; padding: 20px; }
    </style>
</head>
<body>

    {{-- ⚠️ AQUÍ PEGAS EL MISMO HTML QUE GENERA TU CLOSURE DEL SHOW --}}
    {!! app(\App\Http\Controllers\Admin\SeguimientoCrudController::class)->renderResumenHtml($seguimiento) !!}

    <script>
        window.print();
    </script>
</body>
</html>
