@extends(backpack_view('blank'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">Consulta Datos Abiertos</div>
            <div class="card-body">
                <form method="GET" action="{{ backpack_url('consulta-datos-abiertos') }}">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Cédula/NIT</label>
                            <input type="text" name="cedula" class="form-control" value="{{ $cedula }}" required>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Desde (fecha firma)</label>
                            <input type="date" name="desde" class="form-control" value="{{ $desde }}">
                        </div>
                        <div class="col-md-4 mb-2 d-flex align-items-end">
                            <button class="btn btn-primary" type="submit">Consultar</button>
                        </div>
                    </div>
                </form>

                @if($error)
                    <div class="mt-3 alert alert-warning">{{ $error }}</div>
                @endif

                @if($results !== null)
                    @if(empty($results))
                        <p class="mt-3 text-muted">Sin contratos desde 2024.</p>
                    @else
                        <div class="mt-3 d-flex justify-content-between align-items-center">
                            <small class="text-muted">Vista compacta. Usa el botón para ver más detalles por fila.</small>
                        </div>
                        <div class="mt-2 table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0" id="tabla-compacta">
                                <thead class="table-light">
                                    <tr>
                                        <th></th>
                                        <th>Entidad</th>
                                        <th>Estado</th>
                                        <th>Tipo</th>
                                        <th>Fecha firma</th>
                                        <th>Valor</th>
                                        <th>Proveedor</th>
                                        <th>URL proceso</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @php
                                    $safe = function ($value) {
                                        if (is_array($value)) {
                                            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                                        }
                                        if ($value === null || $value === '') {
                                            return '-';
                                        }
                                        return e((string) $value);
                                    };
                                @endphp
                                @foreach($results as $row)
                                    @php
                                        $valor = $row['valor_del_contrato'] ?? null;
                                        if (is_numeric($valor)) {
                                            $valor = '$ ' . number_format((float) $valor, 0, ',', '.');
                                        }
                                        $fechaFirma = $row['fecha_de_firma'] ?? null;
                                        $fechaInicio = $row['fecha_de_inicio_del_contrato'] ?? null;
                                        $fechaFin = $row['fecha_de_fin_del_contrato'] ?? null;
                                        try { if ($fechaFirma) $fechaFirma = \Carbon\Carbon::parse($fechaFirma)->format('Y-m-d'); } catch (\Throwable $e) {}
                                        try { if ($fechaInicio) $fechaInicio = \Carbon\Carbon::parse($fechaInicio)->format('Y-m-d'); } catch (\Throwable $e) {}
                                        try { if ($fechaFin) $fechaFin = \Carbon\Carbon::parse($fechaFin)->format('Y-m-d'); } catch (\Throwable $e) {}

                                        $url = $row['urlproceso'] ?? '';
                                        if (is_array($url)) {
                                            $url = $url['url'] ?? json_encode($url, JSON_UNESCAPED_UNICODE);
                                        } elseif (is_string($url) && str_starts_with($url, '{') && str_contains($url, '"url"')) {
                                            $decoded = json_decode($url, true);
                                            if (is_array($decoded) && !empty($decoded['url'])) {
                                                $url = $decoded['url'];
                                            }
                                        }
                                        $url = is_string($url) ? trim($url) : '';
                                        if ($url && !str_starts_with($url, 'http')) {
                                            $url = 'https://' . ltrim($url, '/');
                                        }
                                        $urlHtml = $url ? '<a href="'.e($url).'" target="_blank">Ver</a>' : '-';

                                        $nombreEntidad = $row['nombre_entidad'] ?? null;
                                        $esMeta = is_string($nombreEntidad) && mb_strtoupper(trim($nombreEntidad)) === 'DEPARTAMENTO DEL META';
                                        $rowClass = $esMeta ? '' : 'table-warning';
                                    @endphp
                                    @php
                                        $rowIdDoc = $row['documento_proveedor'] ?? '';
                                        if (is_array($rowIdDoc)) {
                                            $rowIdDoc = json_encode($rowIdDoc, JSON_UNESCAPED_UNICODE);
                                        }
                                        $rowIdFecha = $row['fecha_de_firma'] ?? '';
                                        if (is_array($rowIdFecha)) {
                                            $rowIdFecha = json_encode($rowIdFecha, JSON_UNESCAPED_UNICODE);
                                        }
                                        $rowIdUrl = $row['urlproceso'] ?? '';
                                        if (is_array($rowIdUrl)) {
                                            $rowIdUrl = json_encode($rowIdUrl, JSON_UNESCAPED_UNICODE);
                                        }
                                        $rowId = 'detalle-' . md5((string) $rowIdDoc . (string) $rowIdFecha . (string) $rowIdUrl . $loop->index);
                                    @endphp
                                    <tr class="{{ $rowClass }}">
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $rowId }}" aria-expanded="false" aria-controls="{{ $rowId }}">+</button>
                                        </td>
                                        <td>{!! $safe($row['nombre_entidad'] ?? null) !!}</td>
                                        <td>{!! $safe($row['estado_contrato'] ?? null) !!}</td>
                                        <td>{!! $safe($row['tipo_de_contrato'] ?? null) !!}</td>
                                        <td>{!! $safe($fechaFirma) !!}</td>
                                        <td>{!! $safe($valor) !!}</td>
                                        <td>{!! $safe($row['proveedor_adjudicado'] ?? null) !!}</td>
                                        <td>{!! $urlHtml !!}</td>
                                    </tr>
                                    <tr class="{{ $rowClass }}">
                                        <td colspan="8" class="p-0 border-top-0">
                                            <div class="collapse" id="{{ $rowId }}">
                                                <div class="p-3">
                                                    <div class="row">
                                                        <div class="col-md-3 mb-2"><small class="text-muted">Departamento</small><div>{!! $safe($row['departamento'] ?? null) !!}</div></div>
                                                        <div class="col-md-3 mb-2"><small class="text-muted">Ciudad</small><div>{!! $safe($row['ciudad'] ?? null) !!}</div></div>
                                                        <div class="col-md-6 mb-2"><small class="text-muted">Proceso de compra</small><div>{!! $safe($row['proceso_de_compra'] ?? null) !!}</div></div>
                                                        <div class="col-md-4 mb-2"><small class="text-muted">Modalidad</small><div>{!! $safe($row['modalidad_de_contratacion'] ?? null) !!}</div></div>
                                                        <div class="col-md-4 mb-2"><small class="text-muted">Inicio</small><div>{!! $safe($fechaInicio) !!}</div></div>
                                                        <div class="col-md-4 mb-2"><small class="text-muted">Fin</small><div>{!! $safe($fechaFin) !!}</div></div>
                                                        <div class="col-md-4 mb-2"><small class="text-muted">Documento</small><div>{!! $safe($row['documento_proveedor'] ?? null) !!}</div></div>
                                                        <div class="col-md-8 mb-2"><small class="text-muted">Objeto</small><div>{!! $safe($row['objeto_del_contrato'] ?? null) !!}</div></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <style>
                            #tabla-compacta td, #tabla-compacta th { white-space: nowrap; }
                            #tabla-compacta td { max-width: 180px; overflow: hidden; text-overflow: ellipsis; }
                        </style>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
