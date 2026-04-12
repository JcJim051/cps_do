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
    <x-backpack::menu-dropdown-item title="Cargos" icon="la la-briefcase" :link="backpack_url('cargo')" />
    <x-backpack::menu-dropdown-item title="Tipos de vinculación" icon="la la-link" :link="backpack_url('tipo-vinculacion')" />
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
<x-backpack::menu-item title="Seguimientos Cto" icon="la la-user-edit" :link="backpack_url('seguimiento')" />
<x-backpack::menu-item title="Seguimientos Nom" icon="la la-id-card" :link="backpack_url('seguimiento-nom')" />
<x-backpack::menu-item title="Consulta Datos Abiertos" icon="la la-search" :link="backpack_url('consulta-datos-abiertos')" />
@endif


{{-- Ejercicios políticos (solo admin/diana) --}}
@if(backpack_user()->hasAnyRole(['admin','diana']))
<x-backpack::menu-dropdown title="Ejercicios Políticos" icon="la la-flag-checkered">
    <x-backpack::menu-dropdown-item title="Campañas" icon="la la-flag" :link="backpack_url('ejercicio-politico')" />
    <x-backpack::menu-dropdown-item title="Equipos" icon="la la-users" :link="backpack_url('equipo-campania')" />
</x-backpack::menu-dropdown>
@endif

{{-- Reportar equipo (coordinadores) --}}
@if(backpack_user()->hasAnyRole(['coordinador','coordinador_comite']))
<x-backpack::menu-item title="Reportar Equipo" icon="la la-clipboard-list" :link="backpack_url('reportar-equipo')" />
@endif

{{-- Autorizaciones (NO programas ni coordinador) --}}
@if(!backpack_user()->hasAnyRole(['programas','coordinador','coordinador_comite']))
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
