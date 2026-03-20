{{-- Topbar (left) items --}}
@if(backpack_user() && backpack_user()->hasAnyRole(['admin','diana']))
    <li class="nav-item px-2">
        <a class="nav-link" href="{{ backpack_url('indicadores') }}">
            <i class="la la-chart-bar me-1"></i> Indicadores
        </a>
    </li>
@endif
