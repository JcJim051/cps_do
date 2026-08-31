{{-- Filtro múltiple remoto para catálogos grandes. --}}
@php($selectedOptions = $filter->options['selected_options'] ?? [])

<li filter-name="{{ $filter->name }}"
    filter-type="{{ $filter->type }}"
    filter-key="{{ $filter->key }}"
    class="nav-item dropdown {{ Request::get($filter->name) ? 'active' : '' }}">
    <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" data-bs-toggle="dropdown"
       role="button" aria-haspopup="true" aria-expanded="false">
        {{ $filter->label }} <span class="integra-filter-count"></span> <span class="caret"></span>
    </a>
    <div class="dropdown-menu p-0 ajax-select">
        <div class="form-group mb-0">
            <select id="filter_{{ $filter->key }}"
                    name="filter_{{ $filter->name }}"
                    class="form-control input-sm select2"
                    multiple
                    placeholder="{{ $filter->placeholder }}"
                    data-filter-key="{{ $filter->key }}"
                    data-filter-type="select2_ajax_multiple"
                    data-filter-name="{{ $filter->name }}"
                    data-select-key="{{ $filter->options['select_key'] ?? 'id' }}"
                    data-select-attribute="{{ $filter->options['select_attribute'] ?? 'name' }}"
                    data-language="{{ str_replace('_', '-', app()->getLocale()) }}"
                    data-minimum-input-length="{{ $filter->options['minimum_input_length'] ?? 2 }}"
                    data-method="{{ $filter->options['method'] ?? 'GET' }}"
                    data-quiet-time="{{ $filter->options['quiet_time'] ?? 350 }}">
                @foreach($selectedOptions as $key => $label)
                    <option value="{{ $key }}" selected>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
</li>

@push('crud_list_styles')
    @basset('https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css')
    @basset('https://cdn.jsdelivr.net/npm/select2-bootstrap-theme@0.1.0-beta.10/dist/select2-bootstrap.min.css')
    <style>
        .navbar-filters .select2-container { min-width: 285px; }
        .navbar-filters .integra-filter-count { font-weight: 700; }
        .navbar-filters .select2-selection--multiple { max-height: 180px; overflow-y: auto; }
    </style>
@endpush

@push('crud_list_scripts')
    @basset('https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.full.min.js')
    @if (app()->getLocale() !== 'en')
        @basset('https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/i18n/' . str_replace('_', '-', app()->getLocale()) . '.js')
    @endif

    <script>
        jQuery(document).ready(function ($) {
            const element = $('#filter_{{ $filter->key }}');
            if (!element.attr('data-initialised')) {
                element.attr('data-initialised', 'true').select2({
                    theme: 'bootstrap',
                    multiple: true,
                    minimumInputLength: Number(element.attr('data-minimum-input-length')),
                    allowClear: true,
                    closeOnSelect: false,
                    placeholder: element.attr('placeholder'),
                    dropdownParent: element.parent('.form-group'),
                    ajax: {
                        url: @json($filter->values),
                        dataType: 'json',
                        type: element.attr('data-method'),
                        delay: Number(element.attr('data-quiet-time')),
                        data: params => ({ q: params.term || '', page: params.page || 1 }),
                        processResults: function (data) {
                            const selectKey = element.attr('data-select-key');
                            const selectAttribute = element.attr('data-select-attribute');
                            const items = data.data || data;

                            return {
                                results: $.map(items, item => ({
                                    id: item[selectKey],
                                    text: item[selectAttribute],
                                })),
                                pagination: { more: Boolean(data.next_page_url) },
                            };
                        },
                    },
                }).on('change', function () {
                    const values = ($(this).val() || []).filter(value => value !== null && value !== '');
                    const encoded = values.length ? JSON.stringify(values) : null;
                    const filterName = $(this).attr('data-filter-name');
                    const filterKey = $(this).attr('data-filter-key');
                    const newUrl = updateDatatablesOnFilterChange(filterName, encoded, true, 0);
                    const item = $('li[filter-key="' + filterKey + '"]');

                    item.toggleClass('active', Boolean(encoded) && URI(newUrl).hasQuery(filterName, true));
                    window.updateIntegraMultipleFilterCount(this);
                });
            }

            $('li[filter-key="{{ $filter->key }}"]').on('shown.bs.dropdown', function () {
                setTimeout(() => element.select2('open'), 50);
            }).on('filter:clear', function () {
                element.val(null).trigger('change');
                $(this).removeClass('active');
            });

            window.updateIntegraMultipleFilterCount(element.get(0));
        });
    </script>

    @once('integra-multiple-filter-counts')
        <script>
            window.updateIntegraMultipleFilterCount = function (select) {
                if (!select) return;
                const item = select.closest('li[filter-name]');
                const anchor = item?.querySelector(':scope > a.nav-link');
                if (!anchor) return;

                let counter = anchor.querySelector('.integra-filter-count');
                if (!counter) {
                    counter = document.createElement('span');
                    counter.className = 'integra-filter-count';
                    anchor.querySelector('.caret')?.before(counter);
                }

                const count = Array.from(select.selectedOptions || []).length;
                counter.textContent = count ? `(${count}) ` : '';
            };

            jQuery(document).ready(function ($) {
                $('.navbar-filters select[multiple]').each(function () {
                    window.updateIntegraMultipleFilterCount(this);
                });
                $(document).on('change', '.navbar-filters select[multiple]', function () {
                    window.updateIntegraMultipleFilterCount(this);
                });
            });
        </script>
    @endonce
@endpush
