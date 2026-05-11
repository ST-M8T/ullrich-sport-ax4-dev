/**
 * DHL Product Catalog Modal
 * @module domains/fulfillment/dhl-product-catalog
 */

import { BaseModal } from '../../components/modal/base';

class DhlProductCatalogModal extends BaseModal {
    constructor() {
        const container = document.createElement('div');
        container.className = 'app-modal';
        container.setAttribute('aria-hidden', 'true');
        container.innerHTML = `
            <div class="app-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="dhl-catalog-modal-title">
                <div class="app-modal__header">
                    <div>
                        <h3 class="app-modal__title h4" id="dhl-catalog-modal-title">DHL-Produktkatalog</h3>
                        <p class="app-modal__subtitle text-muted small" data-modal-subtitle></p>
                    </div>
                    <button type="button" class="btn-close app-modal__close" aria-label="Schließen" data-modal-close></button>
                </div>
                <div class="app-modal__body" data-modal-body></div>
            </div>
            <div class="app-modal__backdrop" data-modal-close></div>
        `;

        document.body.appendChild(container);
        super(container);

        this.subtitleElement = container.querySelector('[data-modal-subtitle]');
        this.bodyElement = container.querySelector('[data-modal-body]');

        this.products = [];
        this.services = [];
        this.selectedProductId = '';
        this.selectedServices = [];
        this.validationErrors = {};
        this.validationResult = null;
        this.isLoadingServices = false;
        this.isValidating = false;
        this.isBooking = false;

        this.productsUrl = '';
        this.servicesUrl = '';
        this.validateUrl = '';
        this.bookingUrl = '';
        this.orderId = null;
    }

    /**
     * Initialize with configuration
     * @param {Object} config
     */
    init(config) {
        this.orderId = config.orderId;
        this.productsUrl = config.productsUrl;
        this.servicesUrl = config.servicesUrl;
        this.validateUrl = config.validateUrl;
        this.bookingUrl = config.bookingUrl;
    }

    /**
     * Open modal and load products
     */
    open() {
        this.resetState();
        this.renderLoading();
        super.open();
        this.loadProducts();
    }

    resetState() {
        this.selectedProductId = '';
        this.selectedServices = [];
        this.validationErrors = {};
        this.validationResult = null;
        this.isLoadingServices = false;
        this.isValidating = false;
        this.isBooking = false;
    }

    renderLoading() {
        this.bodyElement.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Lade...</span>
                </div>
                <p class="mt-2 text-muted">Lade DHL-Produkte...</p>
            </div>
        `;
    }

    renderError(message) {
        this.bodyElement.innerHTML = `
            <div class="alert alert-danger">${this.escapeHtml(message)}</div>
        `;
    }

    renderContent() {
        const selectedProduct = this.products.find(p => p.attributes.product_id === this.selectedProductId);
        const selectedProductDescription = selectedProduct?.attributes?.description || '';

        const productOptions = this.products.map(p => {
            const validUntil = p.attributes.valid_until ? ` (gültig bis ${p.attributes.valid_until})` : '';
            const selected = p.attributes.product_id === this.selectedProductId ? ' selected' : '';
            return `<option value="${this.escapeHtml(p.attributes.product_id)}"${selected}>${this.escapeHtml(p.attributes.name)}${validUntil}</option>`;
        }).join('');

        const serviceCheckboxes = this.services.map(s => `
            <div class="form-check">
                <input
                    type="checkbox"
                    class="form-check-input"
                    id="service-${this.escapeHtml(s.id)}"
                    value="${this.escapeHtml(s.attributes.service_code)}"
                    ${this.selectedServices.includes(s.attributes.service_code) ? ' checked' : ''}
                    ${this.isValidating || this.isBooking ? ' disabled' : ''}
                >
                <label class="form-check-label" for="service-${this.escapeHtml(s.id)}">
                    <strong>${this.escapeHtml(s.attributes.name)}</strong>
                    ${s.attributes.description ? `<div class="text-muted small">${this.escapeHtml(s.attributes.description)}</div>` : ''}
                </label>
            </div>
        `).join('');

        const validationHtml = this.renderValidation();

        this.bodyElement.innerHTML = `
            <div class="mb-4">
                <label for="dhl-product-select" class="form-label">
                    Produkt <span class="text-danger">*</span>
                </label>
                <select
                    id="dhl-product-select"
                    class="form-select ${this.validationErrors.product_id ? 'is-invalid' : ''}"
                    ${this.isValidating || this.isBooking ? ' disabled' : ''}
                >
                    <option value="">Bitte wählen...</option>
                    ${productOptions}
                </select>
                ${this.validationErrors.product_id ? `<div class="invalid-feedback">${this.escapeHtml(this.validationErrors.product_id)}</div>` : ''}
                ${selectedProductDescription ? `<div class="form-text">${this.escapeHtml(selectedProductDescription)}</div>` : ''}
            </div>

            <div class="mb-4">
                <label class="form-label">Zusatzservices</label>
                ${this.isLoadingServices ? `
                    <div class="text-muted small">
                        <span class="spinner-border spinner-border-sm"></span>
                        Lade Zusatzservices...
                    </div>
                ` : this.services.length === 0 ? `
                    <div class="text-muted small">
                        ${this.selectedProductId ? 'Keine Zusatzservices für dieses Produkt verfügbar.' : 'Bitte wählen Sie zuerst ein Produkt.'}
                    </div>
                ` : `
                    <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                        ${serviceCheckboxes}
                    </div>
                `}
            </div>

            ${this.validationErrors.services ? `<div class="text-danger small mb-3">${this.escapeHtml(this.validationErrors.services)}</div>` : ''}

            ${validationHtml}
        `;

        this.bindContentEvents();
    }

    renderValidation() {
        if (this.isValidating) {
            return `
                <div class="text-center py-2">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                    <span class="ms-2 text-muted">Validiere Services...</span>
                </div>
            `;
        }

        if (!this.validationResult) {
            return '';
        }

        if (this.validationResult.valid) {
            return `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> Auswahl ist gültig.
                </div>
            `;
        }

        const errorsHtml = (this.validationResult.errors || []).map(e => `<div>${this.escapeHtml(e)}</div>`).join('');
        return `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle"></i>
                <strong>Ungültige Kombination:</strong>
                ${errorsHtml}
            </div>
        `;
    }

    renderFooter() {
        const footer = this.element.querySelector('.app-modal__footer');
        if (footer) {
            footer.innerHTML = `
                <button type="button" class="btn btn-secondary" data-modal-close ${this.isValidating || this.isBooking ? ' disabled' : ''}>
                    Abbrechen
                </button>
                <button type="button" class="btn btn-primary" id="dhl-catalog-submit" ${this.isValidating || this.isBooking ? ' disabled' : ''}>
                    ${this.isValidating || this.isBooking ? '<span class="spinner-border spinner-border-sm me-2"></span>' : ''}
                    <span>${this.isValidating ? 'Validiere...' : (this.isBooking ? 'Buche...' : 'Validieren & Buchen')}</span>
                </button>
            `;

            footer.querySelector('#dhl-catalog-submit')?.addEventListener('click', () => this.validateAndBook());
        }
    }

    bindContentEvents() {
        const productSelect = this.bodyElement.querySelector('#dhl-product-select');
        productSelect?.addEventListener('change', (e) => {
            this.selectedProductId = e.target.value;
            this.loadAdditionalServices();
        });

        const checkboxes = this.bodyElement.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(cb => {
            cb.addEventListener('change', () => {
                this.selectedServices = Array.from(checkboxes)
                    .filter(c => c.checked)
                    .map(c => c.value);
            });
        });
    }

    async loadProducts() {
        try {
            const response = await fetch(this.productsUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const json = await response.json();
            this.products = json.data || [];
            this.renderContent();
            this.renderFooter();
        } catch (err) {
            console.error('[DHL Product Catalog]', err);
            this.renderError('DHL-Produkte konnten nicht geladen werden: ' + err.message);
        }
    }

    async loadAdditionalServices() {
        if (!this.selectedProductId) {
            this.services = [];
            this.renderContent();
            this.renderFooter();
            return;
        }

        this.isLoadingServices = true;
        this.services = [];
        this.selectedServices = [];
        this.renderContent();
        this.renderFooter();

        try {
            const url = this.servicesUrl + '?product_id=' + encodeURIComponent(this.selectedProductId);
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const json = await response.json();
            this.services = json.data || [];
        } catch (err) {
            console.error('[DHL Additional Services]', err);
        } finally {
            this.isLoadingServices = false;
            this.renderContent();
            this.renderFooter();
        }
    }

    async validateAndBook() {
        this.validationErrors = {};
        this.validationResult = null;

        if (!this.selectedProductId) {
            this.validationErrors.product_id = 'Bitte wählen Sie ein Produkt.';
            this.renderContent();
            this.renderFooter();
            return;
        }

        if (this.selectedServices.length === 0) {
            this.validationErrors.services = 'Bitte wählen Sie mindestens einen Service.';
            this.renderContent();
            this.renderFooter();
            return;
        }

        this.isValidating = true;
        this.renderContent();
        this.renderFooter();

        try {
            const response = await fetch(this.validateUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    product_id: this.selectedProductId,
                    services: this.selectedServices,
                }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const json = await response.json();
            this.validationResult = json.data?.attributes || { valid: false, errors: ['Unbekannter Fehler'] };

            if (!this.validationResult.valid) {
                this.isValidating = false;
                this.renderContent();
                this.renderFooter();
                return;
            }
        } catch (err) {
            this.validationResult = { valid: false, errors: [err.message] };
            this.isValidating = false;
            this.renderContent();
            this.renderFooter();
            return;
        }

        this.isValidating = false;
        await this.submitBooking();
    }

    async submitBooking() {
        this.isBooking = true;
        this.renderContent();
        this.renderFooter();

        try {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = this.bookingUrl;

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = this.getCsrfToken();
            form.appendChild(csrfInput);

            const productInput = document.createElement('input');
            productInput.type = 'hidden';
            productInput.name = 'product_id';
            productInput.value = this.selectedProductId;
            form.appendChild(productInput);

            const servicesInput = document.createElement('input');
            servicesInput.type = 'hidden';
            servicesInput.name = 'additional_services';
            servicesInput.value = JSON.stringify(this.selectedServices);
            form.appendChild(servicesInput);

            const redirectInput = document.createElement('input');
            redirectInput.type = 'hidden';
            redirectInput.name = 'redirect_to';
            redirectInput.value = window.location.href;
            form.appendChild(redirectInput);

            document.body.appendChild(form);
            form.submit();
        } catch (err) {
            this.error = 'Fehler beim Absenden: ' + err.message;
            this.isBooking = false;
            this.renderContent();
            this.renderFooter();
        }
    }

    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta?.content || document.querySelector('input[name="_token"]')?.value || '';
    }

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

/** @type {DhlProductCatalogModal|null} */
let catalogModalInstance = null;

/**
 * Initialize DHL Product Catalog trigger buttons
 */
function initialiseDhlProductCatalog() {
    const triggerButtons = document.querySelectorAll('[data-dhl-catalog-trigger]');
    if (!triggerButtons.length) {
        return;
    }

    if (!catalogModalInstance) {
        catalogModalInstance = new DhlProductCatalogModal();
    }

    triggerButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const config = {
                orderId: button.dataset.orderId || null,
                productsUrl: button.dataset.productsUrl,
                servicesUrl: button.dataset.servicesUrl,
                validateUrl: button.dataset.validateUrl,
                bookingUrl: button.dataset.bookingUrl,
            };

            catalogModalInstance.init(config);
            catalogModalInstance.open();
        });
    });
}

document.addEventListener('DOMContentLoaded', initialiseDhlProductCatalog);

export { DhlProductCatalogModal, initialiseDhlProductCatalog };
