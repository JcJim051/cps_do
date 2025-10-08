@if ($crud->hasAccess('update'))
    <a href="{{ url($crud->route.'/'.$entry->getKey().'/edit') }}" 
       class="btn btn-sm btn-link" 
       data-toggle="tooltip" 
       title="{{ trans('backpack::crud.edit') }}"
       target="_blank"> 
        <i class="la la-edit"></i> {{ trans('backpack::crud.edit') }}
    </a>
@endif