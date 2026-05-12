/**
 * DHL Price-Quote Trigger.
 *
 * Bindet jeden Button `[data-dhl-price-quote-trigger]` und rendert das Ergebnis
 * in `[data-dhl-price-quote-result]`. Keine Inline-Scripts, kein onclick — CSP-konform.
 *
 * Engineering-Handbuch:
 *  - §53 Loading/Empty/Error-States.
 *  - §56 Frontend-Security: keine Inline-Scripts.
 *  - §75.4: Single Source via zentralem fetchJson.
 */

import { fetchJson } from '../../core/http.js';

const formatPrice = (value, currency) => {
    const formatter = new Intl.NumberFormat('de-DE', {
        style: 'currency',
        currency: currency || 'EUR',
    });
    return formatter.format(value);
};

const renderLoading = (resultEl) => {
    resultEl.classList.remove('d-none');
    resultEl.innerHTML = '<div class="text-muted small">Lade Preisabfrage …</div>';
};

const renderSuccess = (resultEl, data) => {
    const price = formatPrice(data.price ?? 0, data.currency);
    const detail = data.breakdown && Object.keys(data.breakdown).length > 0
        ? '<small class="d-block">Details verfügbar.</small>'
        : '';
    resultEl.innerHTML = `
        <div class="alert alert-success mb-0">
            <strong>Preis:</strong> ${price}
            ${detail}
        </div>
    `;
};

const renderError = (resultEl, message) => {
    resultEl.innerHTML = `
        <div class="alert alert-danger mb-0">
            ${message}
        </div>
    `;
};

const bindTrigger = (button) => {
    const url = button.dataset.dhlPriceQuoteUrl;
    const resultSelector = button.dataset.dhlPriceQuoteResult;
    const resultEl = resultSelector ? document.querySelector(resultSelector) : null;

    if (!url || !resultEl) {
        return;
    }

    button.addEventListener('click', async () => {
        button.disabled = true;
        renderLoading(resultEl);

        try {
            const data = await fetchJson(url);
            if (data?.success) {
                renderSuccess(resultEl, data);
            } else {
                renderError(resultEl, data?.error ?? 'Fehler bei Preisabfrage.');
            }
        } catch (error) {
            renderError(resultEl, error.message ?? 'Verbindung zum Server fehlgeschlagen.');
        } finally {
            button.disabled = false;
        }
    });
};

export const initDhlPriceQuote = () => {
    document.querySelectorAll('[data-dhl-price-quote-trigger]').forEach(bindTrigger);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDhlPriceQuote);
} else {
    initDhlPriceQuote();
}
