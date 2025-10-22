<a href="#"
   class="btn btn-sm btn-warning"
   onclick="exportExcel()">
   <i class="la la-file-excel"></i> Exportar filtrado
</a>

<script>
function exportExcel() {
    // Tomar los parámetros actuales de la URL
    const params = window.location.search; // ?anio=2025&estado_contrato_id=8

    // Construir URL de export
    let url = '{{ url($crud->route."/export-excel") }}' + params;

    // Redirigir al export
    window.location.href = url;
}
</script>

