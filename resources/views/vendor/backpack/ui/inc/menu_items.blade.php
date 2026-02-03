{{-- Dashboard --}}
<li class="nav-item">
    <a class="nav-link" href="{{ backpack_url('dashboard') }}">
        <i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}
    </a>
</li>

{{-- Gestión de Usuarios --}}
@if(backpack_user()->hasRole('admin'))
<x-backpack::menu-dropdown title="Gestión de Usuarios" icon="la la-users">
    <x-backpack::menu-dropdown-item title="Usuarios" icon="la la-user" :link="backpack_url('user')" />
    <x-backpack::menu-dropdown-item title="Roles" icon="la la-id-badge" :link="backpack_url('role')" />
    <x-backpack::menu-dropdown-item title="Permisos" icon="la la-key" :link="backpack_url('permission')" />
</x-backpack::menu-dropdown>
@endif

{{-- Parámetros del sistema --}}
@if(backpack_user()->hasRole('admin') || backpack_user()->hasRole('diana'))
<x-backpack::menu-dropdown title="Parámetros del Sistema" icon="la la-cogs">
    <x-backpack::menu-dropdown-item title="Fuentes" icon="la la-database" :link="backpack_url('fuente')" />
    <x-backpack::menu-dropdown-item title="Evaluaciones" icon="la la-check-circle" :link="backpack_url('evaluacion')" />
    <x-backpack::menu-dropdown-item title="Niveles Académicos" icon="la la-graduation-cap" :link="backpack_url('nivel-academico')" />
    <x-backpack::menu-dropdown-item title="Estados" icon="la la-flag" :link="backpack_url('estados')" />
    <x-backpack::menu-dropdown-item title="Secretarías" icon="la la-building" :link="backpack_url('secretaria')" />
    <x-backpack::menu-dropdown-item title="Gerencias" icon="la la-sitemap" :link="backpack_url('gerencia')" />
    <x-backpack::menu-dropdown-item title="Casos" icon="la la-folder" :link="backpack_url('caso')" />
    <x-backpack::menu-dropdown-item title="Estado personas" icon="la la-users" :link="backpack_url('estado-persona')" />
    <x-backpack::menu-dropdown-item title="Tipos" icon="la la-question" :link="backpack_url('tipo')" />
</x-backpack::menu-dropdown>
@endif

{{-- Referencias --}}
@if(backpack_user()->hasRole('admin') || backpack_user()->hasRole('diana'))
<x-backpack::menu-item
    title="Referencias"
    icon="la la-users-cog"
    :link="backpack_url('referencia')"
/>
@endif

{{-- Personas y Seguimientos --}}
@if(backpack_user()->hasRole('admin') || backpack_user()->hasRole('diana'))
<x-backpack::menu-item title="Personas" icon="la la-user-tag" :link="backpack_url('persona')" />
<x-backpack::menu-item title="Seguimientos" icon="la la-user-edit" :link="backpack_url('seguimiento')" />
@endif

{{-- Autorizaciones (NO role 8) --}}
@if(!backpack_user()->hasRole('programas'))
<x-backpack::menu-item
    title="Autorizaciones"
    icon="la la-check-circle"
    :link="backpack_url('autorizacion')"
/>
@endif

{{-- Programas (admin, diana y programas) --}}
@if(
    backpack_user()->hasRole('admin') ||
    backpack_user()->hasRole('diana') ||
    backpack_user()->hasRole('programas')
)
<x-backpack::menu-item
    title="Programas"
    icon="la la-question"
    :link="backpack_url('programas')"
/>
@endif
