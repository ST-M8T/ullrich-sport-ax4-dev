/**
 * DHL "Allowed Services" Akkordeon-Controller.
 *
 * Auto-init fuer alle `[data-dhl-services-accordion]`-Container. Bei Init
 * liest der Controller das Routing/Produkt aus den Data-Attributen oder
 * (sofern im selben Form vorhanden) aus den umgebenden Form-Feldern,
 * laedt die erlaubten Services ueber den Service-Layer und rendert
 * Kategorie-Akkordeon + Service-Items + dynamische Parameter-Felder.
 *
 * Engineering-Handbuch:
 *  - §35-§39 Trennung UI / API / Renderer: nutzt
 *      services/dhl-allowed-services-service (API)
 *      services/dhl-parameter-form-renderer  (Schema → DOM)
 *  - §45 keine direkten fetch-Calls.
 *  - §51 A11y: aria-expanded, aria-controls, aria-busy, aria-live,
 *    Pflicht-Markierung via aria-required und `*`.
 *  - §57 Performance: AbortController bei Re-Fetch zur Race-Condition-Vermeidung.
 *  - §75 DRY: Parameter-Renderer ist extrahiert, kein dupliziertes Schema-Mapping.
 *
 * @module domains/fulfillment/dhl-allowed-services-accordion
 */

import {
    fetchAllowedServices,
    fetchIntersection,
    DhlAllowedServicesError,
} from './services/dhl-allowed-services-service.js';
import {
    renderParameterForm,
    hasRenderableProperties,
} from './services/dhl-parameter-form-renderer.js';

const CATEGORY_ORDER = ['pickup', 'delivery', 'notification', 'dangerous_goods', 'special'];

const CATEGORY_LABELS = {
    pickup: 'Pickup',
    delivery: 'Delivery',
    notification: 'Notification',
    dangerous_goods: 'Dangerous Goods',
    special: 'Special',
};

const STATE_SELECTORS = ['idle', 'loading', 'success', 'empty', 'error'];

const parseJsonAttr = (value, fallback) => {
    if (!value) {
        return fallback;
    }
    try {
        return JSON.parse(value);
    } catch (_err) {
        return fallback;
    }
};

const setState = (root, state) => {
    STATE_SELECTORS.forEach((s) => {
        const el = root.querySelector(`[data-dhl-services-state="${s}"]`);
        if (!el) {
            return;
        }
        if (s === state) {
            el.classList.remove('d-none');
            el.removeAttribute('aria-hidden');
        } else {
            el.classList.add('d-none');
            el.setAttribute('aria-hidden', 'true');
        }
    });
    root.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');
};

const groupByCategory = (services) => {
    const groups = {};
    CATEGORY_ORDER.forEach((c) => { groups[c] = []; });
    services.forEach((service) => {
        const cat = CATEGORY_ORDER.includes(service.category) ? service.category : 'special';
        groups[cat].push(service);
    });
    return groups;
};

const buildServiceItem = (service, preselected, inputName, readOnly, containerId, index) => {
    const code = service.code;
    const mandatory = service.requirement === 'mandatory' || service.requirement === 'required';
    const isPreselected = Object.prototype.hasOwnProperty.call(preselected, code) || mandatory;
    const params = preselected[code] || service.default_parameters || {};

    const item = document.createElement('div');
    item.className = 'list-group-item';
    item.dataset.serviceCode = code;

    // Checkbox + label row.
    const formCheck = document.createElement('div');
    formCheck.className = 'form-check';
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.className = 'form-check-input';
    checkbox.id = `${containerId}__svc_${index}_${code}`.replace(/[^a-zA-Z0-9_-]/g, '_');
    checkbox.value = code;
    checkbox.dataset.dhlServicesItemToggle = '';
    checkbox.checked = isPreselected;
    if (mandatory || readOnly) {
        checkbox.disabled = true;
    }

    const label = document.createElement('label');
    label.className = 'form-check-label';
    label.setAttribute('for', checkbox.id);
    const codeStrong = document.createElement('strong');
    codeStrong.textContent = code;
    label.appendChild(codeStrong);
    label.appendChild(document.createTextNode(` — ${service.name || code}`));
    if (mandatory) {
        const badge = document.createElement('span');
        badge.className = 'badge bg-warning-subtle text-warning-emphasis ms-2';
        badge.textContent = 'Pflicht';
        badge.title = 'Diese Zusatzleistung ist fuer das gewaehlte Routing Pflicht.';
        label.appendChild(badge);
    }
    if (service.deprecated) {
        const badge = document.createElement('span');
        badge.className = 'badge bg-secondary ms-2';
        badge.textContent = 'deprecated';
        badge.title = 'Diese Zusatzleistung laeuft aus.';
        label.appendChild(badge);
    }

    formCheck.appendChild(checkbox);
    formCheck.appendChild(label);
    item.appendChild(formCheck);

    if (service.description) {
        const desc = document.createElement('div');
        desc.className = 'small text-muted mt-1';
        desc.textContent = service.description;
        item.appendChild(desc);
    }

    // Parameter container.
    const paramContainer = document.createElement('div');
    paramContainer.className = 'mt-2 ps-4';
    paramContainer.dataset.dhlServicesParams = '';
    item.appendChild(paramContainer);

    // Hidden "code" input — disabled when checkbox unchecked.
    const codeInput = document.createElement('input');
    codeInput.type = 'hidden';
    codeInput.name = `${inputName}[${code}][code]`;
    codeInput.value = code;
    codeInput.dataset.dhlServicesCodeInput = '';
    if (!isPreselected) {
        codeInput.disabled = true;
    }
    item.appendChild(codeInput);

    const schema = service.parameter_schema || null;
    const fieldPrefix = `${inputName}[${code}]`;
    const renderable = hasRenderableProperties(schema);

    const applyCheckedState = (checked) => {
        codeInput.disabled = !checked;
        if (renderable) {
            paramContainer.classList.toggle('d-none', !checked);
            paramContainer.querySelectorAll('input, select, textarea').forEach((el) => {
                el.disabled = !checked || readOnly;
            });
        }
    };

    if (renderable) {
        renderParameterForm(paramContainer, schema, params, fieldPrefix);
    }
    applyCheckedState(isPreselected);

    checkbox.addEventListener('change', () => {
        if (checkbox.checked && renderable && paramContainer.children.length === 0) {
            renderParameterForm(paramContainer, schema, params, fieldPrefix);
        }
        applyCheckedState(checkbox.checked);
        updateCategoryCounter(item.closest('[data-dhl-services-category-panel]'));
    });

    return item;
};

const updateCategoryCounter = (panel) => {
    if (!panel) {
        return;
    }
    const total = panel.querySelectorAll('[data-dhl-services-item-toggle]').length;
    const active = panel.querySelectorAll('[data-dhl-services-item-toggle]:checked').length;
    const counter = panel.parentElement?.querySelector('[data-dhl-services-counter]');
    if (counter) {
        counter.textContent = `${active}/${total}`;
    }
};

const buildCategorySection = (categoryKey, services, preselected, inputName, readOnly, containerId) => {
    if (services.length === 0) {
        return null;
    }

    const sectionId = `${containerId}__cat_${categoryKey}`;
    const headerId = `${sectionId}__hdr`;
    const panelId = `${sectionId}__pnl`;

    const item = document.createElement('div');
    item.className = 'accordion-item';

    const header = document.createElement('h3');
    header.className = 'accordion-header';
    header.id = headerId;

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'accordion-button collapsed';
    button.dataset.bsToggle = 'collapse';
    button.dataset.bsTarget = `#${panelId}`;
    button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-controls', panelId);
    button.innerHTML = `
        <span>${CATEGORY_LABELS[categoryKey] || categoryKey}</span>
        <span class="ms-2 badge bg-secondary" data-dhl-services-counter>0/0</span>
    `;
    header.appendChild(button);

    const collapse = document.createElement('div');
    collapse.id = panelId;
    collapse.className = 'accordion-collapse collapse';
    collapse.setAttribute('aria-labelledby', headerId);
    collapse.setAttribute('role', 'region');

    const body = document.createElement('div');
    body.className = 'accordion-body p-2';
    const list = document.createElement('div');
    list.className = 'list-group list-group-flush';
    list.dataset.dhlServicesCategoryPanel = categoryKey;

    services.forEach((service, idx) => {
        list.appendChild(
            buildServiceItem(service, preselected, inputName, readOnly, containerId, `${categoryKey}_${idx}`),
        );
    });

    body.appendChild(list);
    collapse.appendChild(body);
    item.appendChild(header);
    item.appendChild(collapse);

    // Initial counter.
    requestAnimationFrame(() => updateCategoryCounter(list));

    return item;
};

const renderServicesSuccess = (root, payload) => {
    const inputName = root.dataset.inputName || 'additional_services';
    const readOnly = root.dataset.readOnly === 'true';
    const preselected = parseJsonAttr(root.dataset.preselected, {}) || {};
    const containerId = root.id || 'dhl-services';

    const container = root.querySelector('[data-dhl-services-categories]');
    if (!container) {
        return;
    }
    container.innerHTML = '';

    const services = Array.isArray(payload.services) ? payload.services : [];

    // Deprecated banner.
    const banner = root.querySelector('[data-dhl-services-deprecated-banner]');
    const bannerText = root.querySelector('[data-dhl-services-deprecated-text]');
    const ctx = payload.context || {};
    if (banner && bannerText) {
        if (ctx.deprecated) {
            bannerText.textContent = ctx.replaced_by_code
                ? `Dieses DHL-Produkt ist abgekuendigt. Empfohlener Nachfolger: ${ctx.replaced_by_code}.`
                : 'Dieses DHL-Produkt ist abgekuendigt.';
            banner.classList.remove('d-none');
        } else {
            banner.classList.add('d-none');
        }
    }

    if (services.length === 0) {
        setState(root, 'empty');
        return;
    }

    const groups = groupByCategory(services);
    CATEGORY_ORDER.forEach((cat) => {
        const section = buildCategorySection(cat, groups[cat], preselected, inputName, readOnly, containerId);
        if (section) {
            container.appendChild(section);
        }
    });

    setState(root, 'success');
};

const renderError = (root, message) => {
    const msgEl = root.querySelector('[data-dhl-services-error-message]');
    if (msgEl) {
        msgEl.textContent = message;
    }
    setState(root, 'error');
};

const collectContext = (root) => {
    const productCode = root.dataset.productCode || '';
    const fromCountry = root.dataset.fromCountry || '';
    const toCountry = root.dataset.toCountry || '';
    const payerCode = root.dataset.payerCode || '';
    return { productCode, fromCountry, toCountry, payerCode };
};

const isReadyForLoad = (ctx, routings) => {
    if (Array.isArray(routings) && routings.length > 0) {
        return true;
    }
    return ctx.productCode && ctx.fromCountry && ctx.toCountry && ctx.payerCode;
};

const loadFor = async (root) => {
    if (root.__dhlActiveController) {
        root.__dhlActiveController.abort();
    }
    const controller = new AbortController();
    root.__dhlActiveController = controller;

    const routings = parseJsonAttr(root.dataset.routings, null);
    const ctx = collectContext(root);

    if (!isReadyForLoad(ctx, routings)) {
        setState(root, 'idle');
        return;
    }

    setState(root, 'loading');

    try {
        let payload;
        if (Array.isArray(routings) && routings.length > 0) {
            payload = await fetchIntersection({
                routings,
                endpointUrl: root.dataset.intersectionUrl,
                signal: controller.signal,
            });
        } else {
            payload = await fetchAllowedServices({
                productCode: ctx.productCode,
                fromCountry: ctx.fromCountry,
                toCountry: ctx.toCountry,
                payerCode: ctx.payerCode,
                endpointUrl: root.dataset.endpointUrl,
                signal: controller.signal,
            });
        }
        renderServicesSuccess(root, payload);
    } catch (err) {
        if (err instanceof DhlAllowedServicesError && err.kind === 'aborted') {
            return;
        }
        const message = err instanceof DhlAllowedServicesError
            ? err.message
            : 'Zusatzleistungen konnten nicht geladen werden.';
        renderError(root, message);
    }
};

const wireRetryButton = (root) => {
    const retry = root.querySelector('[data-dhl-services-retry]');
    if (!retry) {
        return;
    }
    retry.addEventListener('click', () => {
        loadFor(root);
    });
};

/**
 * Public API for outer modules (booking form, profile form) to push routing
 * changes. The component listens for a custom DOM event on the root element.
 *
 * Event:  'dhl:context-changed'
 * Detail: { productCode?, fromCountry?, toCountry?, payerCode?, routings? }
 */
const wireContextEvents = (root) => {
    root.addEventListener('dhl:context-changed', (event) => {
        const detail = (event && event.detail) || {};
        if (Object.prototype.hasOwnProperty.call(detail, 'productCode')) {
            root.dataset.productCode = detail.productCode || '';
        }
        if (Object.prototype.hasOwnProperty.call(detail, 'fromCountry')) {
            root.dataset.fromCountry = detail.fromCountry || '';
        }
        if (Object.prototype.hasOwnProperty.call(detail, 'toCountry')) {
            root.dataset.toCountry = detail.toCountry || '';
        }
        if (Object.prototype.hasOwnProperty.call(detail, 'payerCode')) {
            root.dataset.payerCode = detail.payerCode || '';
        }
        if (Object.prototype.hasOwnProperty.call(detail, 'routings')) {
            if (detail.routings === null || detail.routings === undefined) {
                delete root.dataset.routings;
            } else {
                root.dataset.routings = JSON.stringify(detail.routings);
            }
        }
        loadFor(root);
    });
};

const initRoot = (root) => {
    if (root.__dhlInited) {
        return;
    }
    root.__dhlInited = true;
    wireRetryButton(root);
    wireContextEvents(root);
    loadFor(root);
};

const init = () => {
    document.querySelectorAll('[data-dhl-services-accordion]').forEach(initRoot);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

export { init, initRoot, loadFor };
