<script>
document.addEventListener('DOMContentLoaded', function () {
    const decentralizedIds = new Set(@json(array_map('strval', $decentralizedIds)));
    const state = document.querySelector('[name="estado_contrato_id"]');
    const secretaria = document.querySelector('[name="secretaria_id"]');
    const authorization1 = document.querySelector('[name="aut_despacho"]');
    const authorization2 = document.querySelector('[name="aut_planeacion"]');
    const authorization3 = document.querySelector('[name="aut_administrativa"]');
    const authorization1Date = document.querySelector('[name="fecha_aut_despacho"]');
    const authorization2Date = document.querySelector('[name="fecha_aut_planeacion"]');
    const authorization3Date = document.querySelector('[name="fecha_aut_administrativa"]');
    let syncing = false;
    let previousWasDecentralized = decentralizedIds.has(String(secretaria?.value || ''));

    if (!state || !authorization1) return;

    const normalize = value => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toUpperCase();

    const today = () => {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const checkboxFor = hiddenInput => {
        const checkbox = hiddenInput?.nextElementSibling;
        return checkbox?.matches('input[type="checkbox"]') ? checkbox : null;
    };

    const isAuthorized = hiddenInput => String(hiddenInput?.value || '0') === '1';

    const setAuthorization = (hiddenInput, dateInput, enabled) => {
        if (!hiddenInput) return;
        hiddenInput.value = enabled ? '1' : '0';
        const checkbox = checkboxFor(hiddenInput);
        if (checkbox) checkbox.checked = enabled;
        if (dateInput) {
            if (enabled && !dateInput.value) dateInput.value = today();
            if (!enabled) dateInput.value = '';
        }
    };

    const selectedStateName = () => normalize(state.options[state.selectedIndex]?.text);
    const isDecentralized = () => decentralizedIds.has(String(secretaria?.value || ''));

    const stateIdByName = name => {
        const expected = normalize(name);
        const option = Array.from(state.options).find(item => normalize(item.text) === expected);
        return option?.value || null;
    };

    const selectState = name => {
        const value = stateIdByName(name);
        if (!value) return;
        state.value = value;
        if (window.jQuery) {
            window.jQuery(state).trigger('change.select2');
        }
    };

    const syncFromState = () => {
        const currentState = selectedStateName();
        if (currentState === 'APROBADO') {
            setAuthorization(authorization1, authorization1Date, true);
            if (isDecentralized()) {
                setAuthorization(authorization3, authorization3Date, true);
            }
        } else if (['PENDIENTE', 'PENDIENTE APROBACION', 'CAMBIO'].includes(currentState)) {
            setAuthorization(authorization1, authorization1Date, false);
            if (isDecentralized()) {
                setAuthorization(authorization3, authorization3Date, false);
            }
        }
    };

    state.addEventListener('change', function () {
        if (syncing) return;
        syncing = true;
        syncFromState();
        syncing = false;
    });

    authorization1.addEventListener('change', function () {
        if (syncing) return;
        syncing = true;
        if (isAuthorized(authorization1)) {
            selectState('APROBADO');
            setAuthorization(authorization1, authorization1Date, true);
            if (isDecentralized()) {
                setAuthorization(authorization3, authorization3Date, true);
            }
        } else {
            selectState('PENDIENTE APROBACION');
            setAuthorization(authorization1, authorization1Date, false);
            if (isDecentralized()) {
                setAuthorization(authorization3, authorization3Date, false);
            }
        }
        syncing = false;
    });

    [[authorization2, authorization2Date], [authorization3, authorization3Date]].forEach(([checkbox, dateInput]) => {
        checkbox?.addEventListener('change', function () {
            if (isAuthorized(checkbox) && dateInput && !dateInput.value) dateInput.value = today();
        });
    });

    secretaria?.addEventListener('change', function () {
        const currentIsDecentralized = isDecentralized();
        if (currentIsDecentralized && isAuthorized(authorization1) && selectedStateName() === 'APROBADO') {
            setAuthorization(authorization3, authorization3Date, true);
        } else if (previousWasDecentralized && !currentIsDecentralized) {
            setAuthorization(authorization3, authorization3Date, false);
        }
        previousWasDecentralized = currentIsDecentralized;
    });

    syncFromState();
});
</script>
