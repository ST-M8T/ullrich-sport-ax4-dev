/**
 * DHL Booking Form — Produkt-Selector fuer Einzel-Buchung.
 *
 * Bindet auf [data-dhl-product-selector] und befuellt das Select
 * #dhl-product-select mit DHL-Produkten ueber GET /api/admin/dhl/products.
 *
 * Engineering-Handbuch:
 *  - §53 Klare Zustaende: idle / loading / success / empty / error.
 *  - §75.4 Keine doppelten API-Calls — zentrale fetchJson-Helper-Funktion.
 *  - §51 A11y: aria-busy waehrend Loading, aria-live="polite" fuer Status.
 *
 * @module domains/fulfillment/dhl-booking-form
 */

import { fetchJson } from '../../core/http';

const SELECTOR_ROOT = '[data-dhl-product-selector]';
const SELECTOR_SELECT = '[data-dhl-product-select]';
const SELECTOR_STATUS = '[data-dhl-product-status]';

const escapeText = (value) => {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
};

const setSelectState = (selectEl, { disabled, busy }) => {
    selectEl.disabled = Boolean(disabled);
    if (busy) {
        selectEl.setAttribute('aria-busy', 'true');
    } else {
        selectEl.removeAttribute('aria-busy');
    }
};

const renderStatus = (statusEl, state, message) => {
    if (!statusEl) {
        return;
    }
    statusEl.classList.remove('text-success', 'text-danger', 'text-muted');
    if (state === 'loading' || state === 'idle') {
        statusEl.classList.add('text-muted');
    } else if (state === 'error') {
        statusEl.classList.add('text-danger');
    } else if (state === 'empty') {
        statusEl.classList.add('text-muted');
    } else if (state === 'success') {
        statusEl.classList.add('text-muted');
    }
    statusEl.textContent = message;
};

const renderOptions = (selectEl, products, defaultCode) => {
    selectEl.innerHTML = '';

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = '-- Bitte wählen --';
    selectEl.appendChild(placeholder);

    products.forEach((product) => {
        const code = product?.attributes?.product_id ?? '';
        const name = product?.attributes?.name ?? '';
        if (!code) {
            return;
        }
        const option = document.createElement('option');
        option.value = code;
        option.innerHTML = `${escapeText(code)} – ${escapeText(name)}`;
        if (defaultCode && code === defaultCode) {
            option.selected = true;
        }
        selectEl.appendChild(option);
    });
};

const loadProducts = async (root) => {
    const selectEl = root.querySelector(SELECTOR_SELECT);
    const statusEl = root.querySelector(SELECTOR_STATUS);
    const url = root.getAttribute('data-products-url');
    const defaultCode = root.getAttribute('data-default-product-code') ?? '';

    if (!selectEl || !url) {
        return;
    }

    setSelectState(selectEl, { disabled: true, busy: true });
    renderStatus(statusEl, 'loading', 'Produkte werden geladen …');

    try {
        const payload = await fetchJson(url);
        const products = Array.isArray(payload?.data) ? payload.data : [];

        if (products.length === 0) {
            selectEl.innerHTML = '<option value="">Keine Produkte verfügbar</option>';
            setSelectState(selectEl, { disabled: true, busy: false });
            renderStatus(statusEl, 'empty', 'Keine DHL-Produkte konfiguriert.');
            return;
        }

        renderOptions(selectEl, products, defaultCode);
        setSelectState(selectEl, { disabled: false, busy: false });
        renderStatus(statusEl, 'success', '');
    } catch (error) {
        const message = error instanceof Error && error.message
            ? error.message
            : 'Produkte konnten nicht geladen werden.';
        selectEl.innerHTML = '<option value="">Bitte Produkt-Code manuell prüfen</option>';
        setSelectState(selectEl, { disabled: false, busy: false });
        renderStatus(
            statusEl,
            'error',
            `Produkte konnten nicht geladen werden — bitte erneut versuchen. (${message})`,
        );
    }
};

/**
 * Zusatzleistungen-Loader (§53 idle/loading/success/empty/error).
 *
 * Bindet auf [data-dhl-services-container] innerhalb eines Booking-Forms.
 * Reagiert auf Aenderungen des Produkt-Codes (entweder aus dem zentralen
 * Produkt-Selector #dhl-product-select oder aus dem Text-Input
 * [data-dhl-product-code-input] im Package-Editor) und laedt
 * GET /api/admin/dhl/services?product_id=XYZ.
 */
const SELECTOR_SERVICES_CONTAINER = '[data-dhl-services-container]';
const SELECTOR_SERVICES_STATUS = '[data-dhl-services-status]';
const SELECTOR_SERVICES_LIST = '[data-dhl-services-list]';
const SELECTOR_PRODUCT_CODE_INPUT = '[data-dhl-product-code-input]';

const renderServicesStatus = (statusEl, state, message) => {
    if (!statusEl) {
        return;
    }
    statusEl.classList.remove('text-success', 'text-danger', 'text-muted');
    if (state === 'error') {
        statusEl.classList.add('text-danger');
    } else {
        statusEl.classList.add('text-muted');
    }
    statusEl.textContent = message;
};

const renderServiceCheckboxes = (listEl, services, preselected) => {
    listEl.innerHTML = '';
    const preset = new Set((preselected ?? []).map(String));

    services.forEach((service, index) => {
        const code = service?.attributes?.service_code ?? service?.id ?? '';
        if (!code) {
            return;
        }
        const name = service?.attributes?.name ?? code;
        const inputId = `dhl-svc-${index}-${code}`.replace(/[^a-z0-9_-]/gi, '_');

        const wrapper = document.createElement('div');
        wrapper.className = 'form-check';

        const input = document.createElement('input');
        input.type = 'checkbox';
        input.className = 'form-check-input';
        input.id = inputId;
        input.name = 'additional_services[]';
        input.value = code;
        if (preset.has(String(code))) {
            input.checked = true;
        }

        const label = document.createElement('label');
        label.className = 'form-check-label';
        label.setAttribute('for', inputId);
        label.textContent = `${code} — ${name}`;

        wrapper.appendChild(input);
        wrapper.appendChild(label);
        listEl.appendChild(wrapper);
    });
};

const loadServicesForContainer = async (container, productCode) => {
    const statusEl = container.querySelector(SELECTOR_SERVICES_STATUS);
    const listEl = container.querySelector(SELECTOR_SERVICES_LIST);
    const url = container.closest('[data-dhl-services-url]')?.dataset?.dhlServicesUrl;

    if (!listEl || !url) {
        return;
    }

    if (!productCode || productCode.length < 3) {
        listEl.innerHTML = '';
        renderServicesStatus(
            statusEl,
            'idle',
            'Bitte zuerst einen Produkt-Code eingeben, um Zusatzleistungen zu laden.',
        );
        return;
    }

    listEl.setAttribute('aria-busy', 'true');
    renderServicesStatus(statusEl, 'loading', `Zusatzleistungen fuer ${productCode} werden geladen …`);

    try {
        const fullUrl = `${url}?product_id=${encodeURIComponent(productCode)}`;
        const payload = await fetchJson(fullUrl);
        const services = Array.isArray(payload?.data) ? payload.data : [];

        listEl.removeAttribute('aria-busy');

        if (services.length === 0) {
            listEl.innerHTML = '';
            renderServicesStatus(
                statusEl,
                'empty',
                `Keine Zusatzleistungen fuer Produkt ${productCode} verfuegbar.`,
            );
            return;
        }

        let preselected = [];
        try {
            preselected = JSON.parse(container.dataset.defaultServices || '[]');
        } catch (_err) {
            preselected = [];
        }

        renderServiceCheckboxes(listEl, services, preselected);
        renderServicesStatus(statusEl, 'success', `${services.length} Zusatzleistungen verfuegbar.`);
    } catch (error) {
        listEl.removeAttribute('aria-busy');
        listEl.innerHTML = '';
        const message = error instanceof Error && error.message
            ? error.message
            : 'Zusatzleistungen konnten nicht geladen werden.';
        renderServicesStatus(
            statusEl,
            'error',
            `Zusatzleistungen konnten nicht geladen werden — bitte erneut versuchen. (${message})`,
        );
    }
};

const debounce = (fn, wait = 300) => {
    let timer = null;
    return (...args) => {
        if (timer) {
            clearTimeout(timer);
        }
        timer = setTimeout(() => fn(...args), wait);
    };
};

const initServiceContainers = () => {
    const containers = document.querySelectorAll(SELECTOR_SERVICES_CONTAINER);
    containers.forEach((container) => {
        if (container.dataset.dhlServicesInitialised === '1') {
            return;
        }
        container.dataset.dhlServicesInitialised = '1';

        // Quelle 1: Produkt-Selector (Card-UI)
        const selectEl = document.querySelector(SELECTOR_SELECT);
        // Quelle 2: Produkt-Code-Text-Input (Package-Editor)
        const root = container.closest('[data-dhl-services-url]');
        const codeInput = root ? root.querySelector(SELECTOR_PRODUCT_CODE_INPUT) : null;

        const debouncedLoad = debounce((code) => {
            void loadServicesForContainer(container, (code || '').trim().toUpperCase());
        }, 250);

        if (selectEl) {
            selectEl.addEventListener('change', (event) => {
                debouncedLoad(event.target.value || '');
            });
        }

        if (codeInput) {
            codeInput.addEventListener('input', (event) => {
                debouncedLoad(event.target.value || '');
            });
            // Initial-Load, falls bereits vorbelegt (Validation-Redirect/old())
            const initialCode = (codeInput.value || '').trim().toUpperCase();
            if (initialCode.length === 3) {
                void loadServicesForContainer(container, initialCode);
            }
        }
    });
};

const init = () => {
    const roots = document.querySelectorAll(SELECTOR_ROOT);
    roots.forEach((root) => { void loadProducts(root); });
    initServiceContainers();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

export { init };
