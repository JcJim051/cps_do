<a href="#"
   class="btn btn-sm btn-primary"
   onclick="exportExecutiveExcel(); return false;">
   <i class="la la-chart-bar"></i> Exportar ejecutivo
</a>

<script>
function exportExecutiveExcel() {
    const params = window.location.search;
    const url = '{{ url($crud->route."/export-executive-excel") }}' + params;

    window.location.href = url;
}
</script>
