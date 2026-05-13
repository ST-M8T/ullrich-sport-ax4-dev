/**
 * Versandprofil-Form — Default-Zusatzleistungen + Parameter-Eingabe.
 *
 * Bindet auf [data-freight-profile-services] innerhalb des Freight-Profile
 * Forms (resources/views/fulfillment/masterdata/freight/_form.blade.php) und
 * rendert pro Service mit `parameterSchema` dynamische Eingabefelder
 * (text/number/date/boolean/enum) auf Basis des im DOM mitgelieferten
 * JSON-Schemas (kein XHR — Profile sind routing-frei).
 *
 * Engineering-Handbuch:
 *  - §35-§39 Frontend-Schichten: dieses Modul ist Application-Layer der
 *    Bounded-Context-UI „Fulfillment/Masterdata" und kennt nur DOM.
 *  - §51 A11y: jedes generierte Feld hat ein `<label for>`, Checkbox steuert
 *    Sichtbarkeit der Parameter-Sektion + `disabled`-Zustand der Inputs.
 *  - §62 KISS: Schema-Renderer wird ueber das gemeinsame Modul
 *    services/dhl-parameter-form-renderer geladen.
 *  - §75 DRY: KEINE eigene Schema-Render-Logik mehr; geteilt mit PROJ-5
 *    Akkordeon-Component (dhl-allowed-services-accordion).
 *
 * @module domains/fulfillment/freight-profile-services
 */

import { renderParameterForm } from './services/dhl-parameter-form-renderer.js';

const ROOT_SELECTOR = '[data-freight-profile-services]';
const ROW_SELECTOR = '[data-freight-service-row]';
const TOGGLE_SELECTOR = '[data-freight-service-toggle]';
const PARAMS_SELECTOR = '[data-freight-service-parameters]';
const CODE_INPUT_SELECTOR = '[data-freight-service-code-input]';

const PRODUCT_SELECT_SELECTOR = '[data-freight-profile-product-select]';
const PRODUCT_MIRROR_SELECTOR = '[data-freight-profile-product-mirror]';

const parseJsonAttribute = (value, fallback) => {
    if (!value) {
        return fallback;
    }
    try {
        return JSON.parse(value);
    } catch (_err) {
        return fallback;
    }
};

const renderParameters = (paramsContainer) => {
    const schema = parseJsonAttribute(paramsContainer.dataset.parameterSchema, null);
    const values = parseJsonAttribute(paramsContainer.dataset.parameterValues, {}) || {};
    const prefix = paramsContainer.dataset.fieldPrefix || '';

    renderParameterForm(paramsContainer, schema, values, prefix);
};

const setRowChecked = (row, checked) => {
    const paramsContainer = row.querySelector(PARAMS_SELECTOR);
    const codeInput = row.querySelector(CODE_INPUT_SELECTOR);

    if (codeInput) {
        codeInput.disabled = !checked;
    }

    if (!paramsContainer) {
        return;
    }

    if (checked) {
        // Only render once — keep user input on re-toggle.
        if (paramsContainer.children.length === 0) {
            renderParameters(paramsContainer);
        }
        paramsContainer.classList.remove('d-none');
        paramsContainer.querySelectorAll('input, select, textarea').forEach((el) => {
            el.disabled = false;
        });
    } else {
        paramsContainer.classList.add('d-none');
        paramsContainer.querySelectorAll('input, select, textarea').forEach((el) => {
            el.disabled = true;
        });
    }
};

const initRow = (row) => {
    const toggle = row.querySelector(TOGGLE_SELECTOR);
    if (!toggle) {
        return;
    }

    setRowChecked(row, toggle.checked);

    toggle.addEventListener('change', () => {
        setRowChecked(row, toggle.checked);
    });
};

const initProductMirror = () => {
    const select = document.querySelector(PRODUCT_SELECT_SELECTOR);
    const mirror = document.querySelector(PRODUCT_MIRROR_SELECTOR);
    if (!select || !mirror) {
        return;
    }
    const sync = () => {
        mirror.value = (select.value || '').toUpperCase();
    };
    sync();
    select.addEventListener('change', sync);
};

const init = () => {
    const root = document.querySelector(ROOT_SELECTOR);
    if (root) {
        root.querySelectorAll(ROW_SELECTOR).forEach(initRow);
    }
    initProductMirror();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

export { init };
