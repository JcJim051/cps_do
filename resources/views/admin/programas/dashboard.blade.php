@extends(backpack_view('blank'))

@php
    $maxPeople = max(1, (int) ($peopleByProject->max('personas') ?? 1));
    $maxProjects = max(1, (int) ($projectsByMunicipality->max('proyectos') ?? 1));
    $maxReference1 = max(1, (int) ($peopleByReference1->max('personas') ?? 1));
    $maxReference2 = max(1, (int) ($peopleByReference2->max('personas') ?? 1));
    $chartFilters = collect($filters)->only(['municipio','operador','perfil','estado','referencia_1','referencia_2'])->all();
@endphp

@section('content')
<style>
    .programas-dashboard { --ink:#24283a; --muted:#6f7486; --purple:#6554c0; --purple-soft:#f0edff; --green:#2d9d78; --line:#e7e8ef; color:var(--ink); }
    .programas-dashboard .hero { background:linear-gradient(120deg,#4d3eaa,#7465d4 64%,#8c80df); color:#fff; border-radius:16px; padding:20px 22px; box-shadow:0 12px 30px rgba(75,58,165,.18); }
    .programas-dashboard .hero h1 { font-size:24px; margin:0 0 4px; }
    .programas-dashboard .hero p { margin:0; opacity:.84; font-size:13px; }
    .programas-dashboard .tabs { display:flex; gap:8px; margin:14px 0; }
    .programas-dashboard .tabs a { border:1px solid var(--line); border-radius:9px; padding:8px 13px; background:#fff; color:var(--ink); font-weight:700; text-decoration:none; }
    .programas-dashboard .tabs a.active { color:#fff; background:var(--purple); border-color:var(--purple); }
    .programas-dashboard .panel { background:#fff; border:1px solid var(--line); border-radius:14px; box-shadow:0 4px 14px rgba(33,35,58,.04); }
    .programas-dashboard .filter-panel { padding:16px; margin-bottom:12px; }
    .programas-dashboard .metrics { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px; margin:12px 0; }
    .programas-dashboard .metric { padding:16px; min-height:102px; position:relative; overflow:hidden; }
    .programas-dashboard .metric-button { width:100%; text-align:left; cursor:pointer; border:1px solid var(--line); transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease; }
    .programas-dashboard .metric-button:hover,.programas-dashboard .metric-button:focus { transform:translateY(-2px); border-color:#b9b0eb; box-shadow:0 10px 24px rgba(75,58,165,.13); outline:0; }
    .programas-dashboard .metric-hint { position:absolute; right:13px; top:12px; color:var(--purple); font-size:16px; }
    .programas-dashboard .metric small { display:block; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
    .programas-dashboard .metric strong { display:block; font-size:29px; margin-top:8px; }
    .programas-dashboard .metric.source strong { font-size:15px; line-height:1.3; }
    .programas-dashboard .grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-bottom:12px; }
    .programas-dashboard .panel-title { padding:15px 17px; border-bottom:1px solid var(--line); font-size:15px; font-weight:800; }
    .programas-dashboard .panel-body { padding:15px 17px; }
    .programas-dashboard .bar-row { display:grid; grid-template-columns:minmax(120px,1.25fr) 3fr 48px; gap:10px; align-items:center; margin:10px 0; }
    .programas-dashboard .bar-label { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:12px; }
    .programas-dashboard .bar-track { height:12px; border-radius:999px; background:#eeedf5; overflow:hidden; }
    .programas-dashboard .bar-fill { height:100%; min-width:3px; border-radius:inherit; background:linear-gradient(90deg,var(--purple),#9b8dea); }
    .programas-dashboard .bar-fill.green { background:linear-gradient(90deg,var(--green),#79c7ad); }
    .programas-dashboard .bar-fill.orange { background:linear-gradient(90deg,#e07a35,#f2b276); }
    .programas-dashboard .bar-fill.blue { background:linear-gradient(90deg,#247ba0,#72b7d2); }
    .programas-dashboard .bar-value { display:flex; align-items:center; justify-content:flex-end; gap:4px; font-weight:800; text-align:right; font-size:12px; }
    .programas-dashboard .chart-detail-trigger { width:100%; padding:5px 7px; border:0; border-radius:8px; background:transparent; color:inherit; font:inherit; cursor:pointer; transition:background-color .15s ease,transform .15s ease,box-shadow .15s ease; }
    .programas-dashboard .chart-detail-trigger:hover { background:var(--purple-soft); transform:translateX(2px); }
    .programas-dashboard .chart-detail-trigger:focus-visible { outline:2px solid var(--purple); outline-offset:2px; background:var(--purple-soft); }
    .programas-dashboard .chart-detail-trigger .chart-detail-icon { color:var(--purple); font-size:13px; opacity:.7; }
    .programas-dashboard .table { margin-bottom:0; }
    .programas-dashboard .table thead th { color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:.04em; border-top:0; white-space:nowrap; }
    .programas-dashboard .status { display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--muted); }
    .programas-dashboard .status-dot { width:8px; height:8px; border-radius:50%; background:var(--green); }
    .programas-dashboard .source-error { white-space:pre-line; max-height:150px; overflow:auto; font-size:12px; }
    .programas-dashboard .detail-search { width:min(100%,430px); }
    .programas-dashboard .modal-table-list { white-space:normal; min-width:180px; }
    .programas-metric-modal { z-index:2000 !important; }
    .modal-backdrop { z-index:1990 !important; }
    @media (max-width:1100px) { .programas-dashboard .metrics { grid-template-columns:repeat(3,1fr); } }
    @media (max-width:768px) { .programas-dashboard .metrics,.programas-dashboard .grid { grid-template-columns:1fr; } .programas-dashboard .bar-row { grid-template-columns:110px 1fr 38px; } }
</style>

<div class="programas-dashboard container-fluid px-0">
    <div class="hero d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h1>Programas</h1>
            <p>Personas y proyectos sincronizados desde Google Sheets.</p>
        </div>
        <div class="d-flex gap-2">
            @if($source?->spreadsheet_url)
                <a href="{{ $source->spreadsheet_url }}" target="_blank" rel="noopener" class="btn btn-light btn-sm">
                    <i class="la la-google-drive"></i> Abrir fuente
                </a>
            @endif
            @if($canSync)
                <form method="POST" action="{{ route('programas.sync') }}">
                    @csrf
                    <button class="btn btn-light btn-sm" type="submit"><i class="la la-sync"></i> Sincronizar ahora</button>
                </form>
            @endif
        </div>
    </div>

    <div class="tabs">
        <a class="active" href="{{ route('programas.dashboard') }}"><i class="la la-chart-bar"></i> Tablero</a>
        <a href="{{ backpack_url('programas') }}"><i class="la la-users"></i> Personas</a>
    </div>

    <form method="GET" class="panel filter-panel" id="programasFilters">
        @if($filters['buscar'] !== '')
            <input type="hidden" name="buscar" value="{{ $filters['buscar'] }}">
        @endif
        <div class="row g-2 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Municipio</label>
                <select class="form-select" name="municipio" onchange="this.form.querySelector('[name=operador]').value=''; this.form.querySelector('[name=perfil]').value=''; this.form.querySelector('[name=referencia_1]').value=''; this.form.querySelector('[name=referencia_2]').value=''; this.form.submit();">
                    <option value="">Todos los municipios</option>
                    @foreach($options['municipios'] as $option)<option value="{{ $option }}" @selected($filters['municipio']===$option)>{{ $option }}</option>@endforeach
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Proyecto</label>
                <select class="form-select" name="operador">
                    <option value="">Todos</option>
                    @foreach($options['operadores'] as $option)<option value="{{ $option }}" @selected($filters['operador']===$option)>{{ $option }}</option>@endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Perfil</label>
                <select class="form-select" name="perfil">
                    <option value="">Todos</option>
                    @foreach($options['perfiles'] as $option)<option value="{{ $option }}" @selected($filters['perfil']===$option)>{{ $option }}</option>@endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Estado</label>
                <select class="form-select" name="estado">
                    <option value="TODOS" @selected($filters['estado']==='TODOS')>Todos</option>
                    @foreach($options['estados'] as $option)<option value="{{ $option }}" @selected($filters['estado']===$option)>{{ $option }}</option>@endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-4 d-grid gap-2">
                <button class="btn btn-primary" type="submit">Filtrar</button>
                <button class="btn btn-outline-success" type="submit" formaction="{{ route('programas.export') }}">
                    <i class="la la-file-excel"></i> Descargar informe
                </button>
            </div>
            <div class="col-lg-6 col-md-6">
                <label class="form-label">Referencia 1</label>
                <select class="form-select" name="referencia_1">
                    <option value="">Todas</option>
                    @foreach($options['referencias_1'] as $option)<option value="{{ $option }}" @selected($filters['referencia_1']===$option)>{{ $option }}</option>@endforeach
                </select>
            </div>
            <div class="col-lg-6 col-md-6">
                <label class="form-label">Referencia 2</label>
                <select class="form-select" name="referencia_2">
                    <option value="">Todas</option>
                    @foreach($options['referencias_2'] as $option)<option value="{{ $option }}" @selected($filters['referencia_2']===$option)>{{ $option }}</option>@endforeach
                </select>
            </div>
        </div>
    </form>

    <div class="metrics">
        <button type="button" class="panel metric metric-button bg-white" data-bs-toggle="modal" data-bs-target="#personasMetricModal">
            <i class="la la-external-link-alt metric-hint"></i><small>Personas únicas</small><strong>{{ number_format($metrics['personas'],0,',','.') }}</strong>
        </button>
        <button type="button" class="panel metric metric-button bg-white" data-bs-toggle="modal" data-bs-target="#proyectosMetricModal">
            <i class="la la-external-link-alt metric-hint"></i><small>Proyectos</small><strong>{{ number_format($metrics['proyectos'],0,',','.') }}</strong>
        </button>
        <button type="button" class="panel metric metric-button bg-white" data-bs-toggle="modal" data-bs-target="#municipiosMetricModal">
            <i class="la la-external-link-alt metric-hint"></i><small>Municipios</small><strong>{{ number_format($metrics['municipios'],0,',','.') }}</strong>
        </button>
        <button type="button" class="panel metric metric-button bg-white" data-bs-toggle="modal" data-bs-target="#asignacionesMetricModal">
            <i class="la la-external-link-alt metric-hint"></i><small>Asignaciones</small><strong>{{ number_format($metrics['asignaciones'],0,',','.') }}</strong>
        </button>
        <button type="button" class="panel metric metric-button bg-white" data-bs-toggle="modal" data-bs-target="#referencia1MetricModal">
            <i class="la la-external-link-alt metric-hint"></i><small>Referencias 1</small><strong>{{ number_format($metrics['referencias_1'],0,',','.') }}</strong>
        </button>
        <button type="button" class="panel metric metric-button bg-white" data-bs-toggle="modal" data-bs-target="#referencia2MetricModal">
            <i class="la la-external-link-alt metric-hint"></i><small>Referencias 2</small><strong>{{ number_format($metrics['referencias_2'],0,',','.') }}</strong>
        </button>
        <button type="button" class="panel metric metric-button source bg-white" data-bs-toggle="modal" data-bs-target="#sincronizacionMetricModal">
            <i class="la la-external-link-alt metric-hint"></i>
            <small>Última sincronización</small>
            <strong>{{ $source?->ultima_sincronizacion_at?->format('d/m/Y H:i') ?: 'Pendiente' }}</strong>
            <span class="status"><span class="status-dot" style="background:{{ $source?->ultimo_error ? '#d9485f' : '#2d9d78' }}"></span>{{ $source?->ultimo_error ? 'Con observaciones' : 'Fuente disponible' }}</span>
        </button>
    </div>

    <div class="grid">
        <section class="panel">
            <div class="panel-title">Personas por referencia 1</div>
            <div class="panel-body">
                @forelse($peopleByReference1 as $item)
                    <button type="button" class="bar-row chart-detail-trigger" data-dimension="referencia_1" data-value="{{ $item['nombre'] }}" title="Ver detalle de {{ $item['nombre'] }}" aria-label="Ver {{ $item['personas'] }} personas de la referencia 1 {{ $item['nombre'] }}">
                        <span class="bar-label">{{ $item['nombre'] }}</span>
                        <span class="bar-track"><span class="bar-fill orange" style="width:{{ max(2,round(($item['personas']/$maxReference1)*100,1)) }}%"></span></span>
                        <span class="bar-value">{{ number_format($item['personas'],0,',','.') }} <i class="la la-search-plus chart-detail-icon" aria-hidden="true"></i></span>
                    </button>
                @empty
                    <div class="text-muted py-4 text-center">No hay referencias 1 para los filtros seleccionados.</div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <div class="panel-title">Personas por referencia 2</div>
            <div class="panel-body">
                @forelse($peopleByReference2 as $item)
                    <button type="button" class="bar-row chart-detail-trigger" data-dimension="referencia_2" data-value="{{ $item['nombre'] }}" title="Ver detalle de {{ $item['nombre'] }}" aria-label="Ver {{ $item['personas'] }} personas de la referencia 2 {{ $item['nombre'] }}">
                        <span class="bar-label">{{ $item['nombre'] }}</span>
                        <span class="bar-track"><span class="bar-fill blue" style="width:{{ max(2,round(($item['personas']/$maxReference2)*100,1)) }}%"></span></span>
                        <span class="bar-value">{{ number_format($item['personas'],0,',','.') }} <i class="la la-search-plus chart-detail-icon" aria-hidden="true"></i></span>
                    </button>
                @empty
                    <div class="text-muted py-4 text-center">No hay referencias 2 para los filtros seleccionados.</div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="grid">
        <section class="panel">
            <div class="panel-title">Personas por proyecto</div>
            <div class="panel-body">
                @forelse($peopleByProject as $item)
                    <button type="button" class="bar-row chart-detail-trigger" data-dimension="operador" data-value="{{ $item['nombre'] }}" title="Ver detalle de {{ $item['nombre'] }}" aria-label="Ver {{ $item['personas'] }} personas del proyecto {{ $item['nombre'] }}">
                        <span class="bar-label">{{ $item['nombre'] }}</span>
                        <span class="bar-track"><span class="bar-fill" style="width:{{ max(2,round(($item['personas']/$maxPeople)*100,1)) }}%"></span></span>
                        <span class="bar-value">{{ number_format($item['personas'],0,',','.') }} <i class="la la-search-plus chart-detail-icon" aria-hidden="true"></i></span>
                    </button>
                @empty
                    <div class="text-muted py-4 text-center">No hay datos para los filtros seleccionados.</div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <div class="panel-title">Proyectos por municipio</div>
            <div class="panel-body">
                @forelse($projectsByMunicipality as $item)
                    <button type="button" class="bar-row chart-detail-trigger" data-dimension="municipio" data-value="{{ $item['nombre'] }}" title="Ver detalle de {{ $item['nombre'] }}" aria-label="Ver los {{ $item['proyectos'] }} proyectos del municipio {{ $item['nombre'] }}">
                        <span class="bar-label">{{ $item['nombre'] }}</span>
                        <span class="bar-track"><span class="bar-fill green" style="width:{{ max(2,round(($item['proyectos']/$maxProjects)*100,1)) }}%"></span></span>
                        <span class="bar-value">{{ number_format($item['proyectos'],0,',','.') }} <i class="la la-search-plus chart-detail-icon" aria-hidden="true"></i></span>
                    </button>
                @empty
                    <div class="text-muted py-4 text-center">No hay datos para los filtros seleccionados.</div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="panel mb-3">
        <div class="panel-title">Resumen por municipio</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Municipio</th><th>Personas únicas</th><th>Proyectos</th><th>Asignaciones</th></tr></thead>
                <tbody>
                @forelse($projectsByMunicipality as $item)
                    <tr><td><strong>{{ $item['nombre'] }}</strong></td><td>{{ number_format($item['personas'],0,',','.') }}</td><td>{{ number_format($item['proyectos'],0,',','.') }}</td><td>{{ number_format($item['asignaciones'],0,',','.') }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No hay resultados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel mb-3">
        <div class="panel-title d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>Personas y asignaciones</span>
            <form method="GET" class="detail-search d-flex gap-2">
                @foreach(['municipio','operador','perfil','estado','referencia_1','referencia_2'] as $filterName)
                    @if($filters[$filterName] !== '')<input type="hidden" name="{{ $filterName }}" value="{{ $filters[$filterName] }}">@endif
                @endforeach
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="la la-search"></i></span>
                    <input class="form-control" name="buscar" value="{{ $filters['buscar'] }}" placeholder="Buscar por nombre, cédula, proyecto, institución o referencia">
                    <button class="btn btn-primary" type="submit">Buscar</button>
                    @if($filters['buscar'] !== '')
                        <a class="btn btn-outline-secondary" href="{{ route('programas.dashboard', request()->except(['buscar','page'])) }}" title="Limpiar búsqueda"><i class="la la-times"></i></a>
                    @endif
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Persona</th><th>Cédula</th><th>Municipio</th><th>Proyecto</th><th>Perfil</th><th>Referencia 1</th><th>Referencia 2</th><th>Institución / sede</th><th>Estado</th></tr></thead>
                <tbody>
                @forelse($details as $row)
                    <tr>
                        <td><strong>{{ $row->nombre_apellidos }}</strong></td>
                        <td>{{ $row->cedula ?: 'Sin cédula' }}</td>
                        <td>{{ $row->municipio }}</td>
                        <td>{{ $row->operador }}</td>
                        <td>{{ $row->perfil ?: 'N/A' }}</td>
                        <td>{{ $row->ref_1 ?: 'N/A' }}</td>
                        <td>{{ $row->ref_2 ?: 'N/A' }}</td>
                        <td>{{ $row->institucion ?: 'N/A' }}</td>
                        <td><span class="badge bg-{{ strtoupper((string)$row->estado)==='VIGENTE' ? 'success' : 'secondary' }}">{{ $row->estado ?: 'Sin estado' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No hay resultados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($details->hasPages())<div class="p-3 border-top">{{ $details->links() }}</div>@endif
    </section>

    @if($source?->ultimo_error)
        <section class="panel mb-3">
            <div class="panel-title text-danger d-flex justify-content-between align-items-center">
                <span>Observaciones de sincronización</span>
                @if($canConfigureGoogle)
                    <a class="btn btn-sm btn-outline-danger" href="{{ route('prevalidacion.google.configuration') }}">
                        <i class="la la-google"></i> Revisar conexión Google
                    </a>
                @endif
            </div>
            <div class="panel-body source-error">{{ $source->ultimo_error }}</div>
        </section>
    @endif
</div>

<div class="modal fade programas-metric-modal" id="personasMetricModal" tabindex="-1" aria-labelledby="personasMetricModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title" id="personasMetricModalLabel">Personas únicas</h5><small class="text-muted">{{ number_format($metrics['personas'],0,',','.') }} personas según los filtros activos</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" onclick="closeProgramasMetricModal(this)" aria-label="Cerrar"></button></div>
        <div class="modal-body table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Persona</th><th>Cédula</th><th>Proyectos</th><th>Municipios</th><th>Asignaciones</th></tr></thead><tbody>
            @forelse($peopleDetails as $item)<tr><td><strong>{{ $item['nombre'] }}</strong></td><td>{{ $item['cedula'] ?: 'Sin cédula' }}</td><td class="modal-table-list">{{ $item['proyectos']->implode(', ') }}</td><td class="modal-table-list">{{ $item['municipios']->implode(', ') }}</td><td>{{ $item['asignaciones'] }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted">No hay datos.</td></tr>@endforelse
        </tbody></table></div>
    </div></div>
</div>

<div class="modal fade programas-metric-modal" id="proyectosMetricModal" tabindex="-1" aria-labelledby="proyectosMetricModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title" id="proyectosMetricModalLabel">Proyectos</h5><small class="text-muted">{{ number_format($metrics['proyectos'],0,',','.') }} proyectos según los filtros activos</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" onclick="closeProgramasMetricModal(this)" aria-label="Cerrar"></button></div>
        <div class="modal-body table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Proyecto</th><th>Personas</th><th>Asignaciones</th><th>Municipios</th></tr></thead><tbody>
            @forelse($peopleByProject as $item)<tr><td><strong>{{ $item['nombre'] }}</strong></td><td>{{ $item['personas'] }}</td><td>{{ $item['asignaciones'] }}</td><td class="modal-table-list">{{ $item['municipios']->implode(', ') }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted">No hay datos.</td></tr>@endforelse
        </tbody></table></div>
    </div></div>
</div>

<div class="modal fade programas-metric-modal" id="municipiosMetricModal" tabindex="-1" aria-labelledby="municipiosMetricModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title" id="municipiosMetricModalLabel">Municipios</h5><small class="text-muted">{{ number_format($metrics['municipios'],0,',','.') }} municipios según los filtros activos</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" onclick="closeProgramasMetricModal(this)" aria-label="Cerrar"></button></div>
        <div class="modal-body table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Municipio</th><th>Personas</th><th>Proyectos</th><th>Asignaciones</th><th>Detalle de proyectos</th></tr></thead><tbody>
            @forelse($projectsByMunicipality as $item)<tr><td><strong>{{ $item['nombre'] }}</strong></td><td>{{ $item['personas'] }}</td><td>{{ $item['proyectos'] }}</td><td>{{ $item['asignaciones'] }}</td><td class="modal-table-list">{{ $item['operadores']->implode(', ') }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted">No hay datos.</td></tr>@endforelse
        </tbody></table></div>
    </div></div>
</div>

<div class="modal fade programas-metric-modal" id="asignacionesMetricModal" tabindex="-1" aria-labelledby="asignacionesMetricModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title" id="asignacionesMetricModalLabel">Asignaciones</h5><small class="text-muted">{{ number_format($metrics['asignaciones'],0,',','.') }} asignaciones según los filtros activos</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" onclick="closeProgramasMetricModal(this)" aria-label="Cerrar"></button></div>
        <div class="modal-body table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Proyecto</th><th>Municipio</th><th>Personas</th><th>Asignaciones</th></tr></thead><tbody>
            @forelse($assignmentDetails as $item)<tr><td><strong>{{ $item['proyecto'] }}</strong></td><td>{{ $item['municipio'] }}</td><td>{{ $item['personas'] }}</td><td>{{ $item['asignaciones'] }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted">No hay datos.</td></tr>@endforelse
        </tbody></table></div>
    </div></div>
</div>

<div class="modal fade programas-metric-modal" id="referencia1MetricModal" tabindex="-1" aria-labelledby="referencia1MetricModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title" id="referencia1MetricModalLabel">Referencias 1</h5><small class="text-muted">{{ number_format($metrics['referencias_1'],0,',','.') }} referencias distintas según los filtros activos</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" onclick="closeProgramasMetricModal(this)" aria-label="Cerrar"></button></div>
        <div class="modal-body table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Referencia 1</th><th>Personas</th><th>Proyectos</th><th>Asignaciones</th><th>Detalle de proyectos</th></tr></thead><tbody>
            @forelse($peopleByReference1 as $item)<tr><td><strong>{{ $item['nombre'] }}</strong></td><td>{{ $item['personas'] }}</td><td>{{ $item['proyectos'] }}</td><td>{{ $item['asignaciones'] }}</td><td class="modal-table-list">{{ $item['operadores']->implode(', ') }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted">No hay datos.</td></tr>@endforelse
        </tbody></table></div>
    </div></div>
</div>

<div class="modal fade programas-metric-modal" id="referencia2MetricModal" tabindex="-1" aria-labelledby="referencia2MetricModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title" id="referencia2MetricModalLabel">Referencias 2</h5><small class="text-muted">{{ number_format($metrics['referencias_2'],0,',','.') }} referencias distintas según los filtros activos</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" onclick="closeProgramasMetricModal(this)" aria-label="Cerrar"></button></div>
        <div class="modal-body table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Referencia 2</th><th>Personas</th><th>Proyectos</th><th>Asignaciones</th><th>Detalle de proyectos</th></tr></thead><tbody>
            @forelse($peopleByReference2 as $item)<tr><td><strong>{{ $item['nombre'] }}</strong></td><td>{{ $item['personas'] }}</td><td>{{ $item['proyectos'] }}</td><td>{{ $item['asignaciones'] }}</td><td class="modal-table-list">{{ $item['operadores']->implode(', ') }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted">No hay datos.</td></tr>@endforelse
        </tbody></table></div>
    </div></div>
</div>

<div class="modal fade programas-metric-modal" id="chartDetailModal" tabindex="-1" aria-labelledby="chartDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header">
            <div>
                <h5 class="modal-title" id="chartDetailModalLabel">Detalle de la gráfica</h5>
                <small class="text-muted" id="chartDetailSubtitle">Selecciona un segmento para consultar sus asignaciones.</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" onclick="closeProgramasMetricModal(this)" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
            <form id="chartDetailSearchForm" class="d-flex gap-2 mb-3" role="search">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="la la-search"></i></span>
                    <input type="search" class="form-control" id="chartDetailSearch" maxlength="255" placeholder="Buscar en este detalle">
                    <button class="btn btn-primary" type="submit">Buscar</button>
                    <button class="btn btn-outline-secondary" type="button" id="chartDetailClear" title="Limpiar búsqueda"><i class="la la-times"></i></button>
                </div>
            </form>
            <div id="chartDetailLoading" class="text-center text-muted py-5" hidden><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Cargando detalle…</div>
            <div id="chartDetailError" class="alert alert-danger" role="alert" hidden>
                <span id="chartDetailErrorText">No fue posible cargar el detalle.</span>
                <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="chartDetailRetry">Reintentar</button>
            </div>
            <div id="chartDetailContent" hidden>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead><tr><th>Persona</th><th>Cédula</th><th>Municipio</th><th>Proyecto</th><th>Perfil</th><th>Institución / sede</th><th>Referencia 1</th><th>Referencia 2</th><th>Estado</th></tr></thead>
                        <tbody id="chartDetailRows"></tbody>
                    </table>
                </div>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-top pt-3">
                    <small class="text-muted" id="chartDetailPageInfo"></small>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Paginación del detalle">
                        <button type="button" class="btn btn-outline-secondary" id="chartDetailPrevious"><i class="la la-angle-left"></i> Anterior</button>
                        <button type="button" class="btn btn-outline-secondary" id="chartDetailNext">Siguiente <i class="la la-angle-right"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div></div>
</div>

<div class="modal fade programas-metric-modal" id="sincronizacionMetricModal" tabindex="-1" aria-labelledby="sincronizacionMetricModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="sincronizacionMetricModalLabel">Detalle de sincronización</h5><button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" onclick="closeProgramasMetricModal(this)" aria-label="Cerrar"></button></div>
        <div class="modal-body">
            <dl class="row mb-0"><dt class="col-sm-4">Fuente</dt><dd class="col-sm-8">{{ $source?->nombre ?: 'Sin configurar' }}</dd><dt class="col-sm-4">Pestaña</dt><dd class="col-sm-8">{{ $source?->hoja ?: 'N/A' }}</dd><dt class="col-sm-4">Última sincronización</dt><dd class="col-sm-8">{{ $source?->ultima_sincronizacion_at?->format('d/m/Y H:i:s') ?: 'Pendiente' }}</dd></dl>
            @if($source?->ultimo_resumen)<hr><h6>Último resultado</h6><div class="row g-2">@foreach($source->ultimo_resumen as $label => $value)<div class="col-md-4"><div class="border rounded p-2"><small class="text-muted text-uppercase">{{ str_replace('_',' ',$label) }}</small><div class="fw-bold">{{ is_scalar($value) ? $value : json_encode($value) }}</div></div></div>@endforeach</div>@endif
            @if($source?->ultimo_error)<hr><h6 class="text-danger">Observaciones</h6><div class="source-error">{{ $source->ultimo_error }}</div>@endif
        </div>
    </div></div>
</div>
@endsection

@push('after_scripts')
<script>
(function () {
    var detailEndpoint = @json(route('programas.detail'));
    var activeFilters = @json($chartFilters);
    var detailState = { dimension: '', value: '', search: '', page: 1, lastPage: 1 };
    var detailRequest = null;

    function mountProgramasModals() {
        document.querySelectorAll('.programas-metric-modal').forEach(function (modal) {
            if (modal.parentElement !== document.body) document.body.appendChild(modal);
        });
    }

    function escapeHtml(value) {
        var element = document.createElement('div');
        element.textContent = value === null || value === undefined || value === '' ? 'N/A' : String(value);
        return element.innerHTML;
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString('es-CO');
    }

    function showChartDetailModal() {
        var modal = document.getElementById('chartDetailModal');
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
            return;
        }
        if (window.jQuery && window.jQuery.fn.modal) {
            window.jQuery(modal).modal('show');
            return;
        }
        modal.style.display = 'block';
        modal.classList.add('show');
        modal.removeAttribute('aria-hidden');
        document.body.classList.add('modal-open');
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show programas-fallback-backdrop';
        document.body.appendChild(backdrop);
    }

    function setDetailState(state) {
        document.getElementById('chartDetailLoading').hidden = state !== 'loading';
        document.getElementById('chartDetailError').hidden = state !== 'error';
        document.getElementById('chartDetailContent').hidden = state !== 'content';
    }

    function renderDetail(data) {
        document.getElementById('chartDetailModalLabel').textContent = data.title;
        document.getElementById('chartDetailSubtitle').textContent =
            formatNumber(data.personas) + ' personas únicas · ' + formatNumber(data.asignaciones) + ' asignaciones' +
            (detailState.search ? ' · Búsqueda: ' + detailState.search : '');

        var body = document.getElementById('chartDetailRows');
        if (!data.rows.length) {
            body.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No hay resultados para esta consulta.</td></tr>';
        } else {
            body.innerHTML = data.rows.map(function (row) {
                var status = escapeHtml(row.estado);
                var statusClass = String(row.estado || '').toUpperCase() === 'VIGENTE' ? 'success' : 'secondary';
                return '<tr>' +
                    '<td><strong>' + escapeHtml(row.persona) + '</strong></td>' +
                    '<td>' + (row.cedula ? escapeHtml(row.cedula) : 'Sin cédula') + '</td>' +
                    '<td>' + escapeHtml(row.municipio) + '</td>' +
                    '<td>' + escapeHtml(row.proyecto) + '</td>' +
                    '<td>' + escapeHtml(row.perfil) + '</td>' +
                    '<td>' + escapeHtml(row.institucion) + '</td>' +
                    '<td>' + escapeHtml(row.referencia_1) + '</td>' +
                    '<td>' + escapeHtml(row.referencia_2) + '</td>' +
                    '<td><span class="badge bg-' + statusClass + '">' + status + '</span></td>' +
                '</tr>';
            }).join('');
        }

        detailState.page = data.pagination.page;
        detailState.lastPage = data.pagination.last_page;
        var first = data.pagination.total ? ((data.pagination.page - 1) * data.pagination.per_page) + 1 : 0;
        var last = Math.min(data.pagination.page * data.pagination.per_page, data.pagination.total);
        document.getElementById('chartDetailPageInfo').textContent =
            'Mostrando ' + formatNumber(first) + '–' + formatNumber(last) + ' de ' + formatNumber(data.pagination.total);
        document.getElementById('chartDetailPrevious').disabled = data.pagination.page <= 1;
        document.getElementById('chartDetailNext').disabled = data.pagination.page >= data.pagination.last_page;
        setDetailState('content');
    }

    async function loadChartDetail(page) {
        if (detailRequest) detailRequest.abort();
        detailRequest = new AbortController();
        detailState.page = page || 1;
        setDetailState('loading');

        var params = new URLSearchParams();
        Object.keys(activeFilters).forEach(function (key) {
            if (activeFilters[key] !== null && activeFilters[key] !== '') params.set(key, activeFilters[key]);
        });
        params.set('dimension', detailState.dimension);
        params.set('valor', detailState.value);
        params.set('page', String(detailState.page));
        if (detailState.search) params.set('buscar', detailState.search);

        try {
            var response = await fetch(detailEndpoint + '?' + params.toString(), {
                headers: { 'Accept': 'application/json' },
                signal: detailRequest.signal
            });
            if (!response.ok) throw new Error('El servidor respondió con estado ' + response.status + '.');
            renderDetail(await response.json());
        } catch (error) {
            if (error.name === 'AbortError') return;
            document.getElementById('chartDetailErrorText').textContent = error.message || 'No fue posible cargar el detalle.';
            setDetailState('error');
        }
    }

    function initializeChartDetails() {
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('.chart-detail-trigger');
            if (!trigger) return;
            detailState.dimension = trigger.dataset.dimension;
            detailState.value = trigger.dataset.value;
            detailState.search = '';
            detailState.page = 1;
            document.getElementById('chartDetailSearch').value = '';
            document.getElementById('chartDetailModalLabel').textContent = 'Cargando detalle…';
            document.getElementById('chartDetailSubtitle').textContent = trigger.dataset.value;
            showChartDetailModal();
            loadChartDetail(1);
        });

        document.getElementById('chartDetailSearchForm').addEventListener('submit', function (event) {
            event.preventDefault();
            detailState.search = document.getElementById('chartDetailSearch').value.trim();
            loadChartDetail(1);
        });
        document.getElementById('chartDetailClear').addEventListener('click', function () {
            document.getElementById('chartDetailSearch').value = '';
            detailState.search = '';
            loadChartDetail(1);
        });
        document.getElementById('chartDetailRetry').addEventListener('click', function () { loadChartDetail(detailState.page); });
        document.getElementById('chartDetailPrevious').addEventListener('click', function () {
            if (detailState.page > 1) loadChartDetail(detailState.page - 1);
        });
        document.getElementById('chartDetailNext').addEventListener('click', function () {
            if (detailState.page < detailState.lastPage) loadChartDetail(detailState.page + 1);
        });
    }

    window.closeProgramasMetricModal = function (button) {
        var modal = button.closest('.modal');
        if (!modal) return;
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modal).hide();
            return;
        }
        if (window.jQuery && window.jQuery.fn.modal) {
            window.jQuery(modal).modal('hide');
            return;
        }
        modal.classList.remove('show');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) { backdrop.remove(); });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            mountProgramasModals();
            initializeChartDetails();
        });
    } else {
        mountProgramasModals();
        initializeChartDetails();
    }
})();
</script>
@endpush
