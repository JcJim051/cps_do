@if ($crud->hasAccess('create'))
    <a href="{{ url($crud->route . '/import') }}" class="btn btn-success" data-style="zoom-in">
        <span class="ladda-label">
            <i class="la la-file-excel"></i> {{ trans('Importar Masivamente') }}
        </span>
    </a>
@endif
