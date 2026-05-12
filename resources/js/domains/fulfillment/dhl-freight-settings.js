/**
 * DHL Freight Settings — Test-Connection Button.
 *
 * Bindet auf [data-dhl-freight-test-connection] und ruft die Test-Connection-
 * Route via fetchJson auf. Zeigt Loading-/Erfolg-/Fehler-Badge inline.
 *
 * Engineering-Handbuch:
 *  - §53 Klare Zustaende: idle / loading / success / error.
 *  - §75.4 Keine doppelten API-Calls — zentrale fetchJson-Helper-Funktion.
 *  - §56 Keine Secrets im DOM, kein Logging sensibler Daten.
 *
 * @module domains/fulfillment/dhl-freight-settings
 */

import { fetchJson, getCsrfToken } from '../../core/http';

const SELECTOR_BUTTON = '[data-dhl-freight-test-connection]';
const SELECTOR_RESULT = '[data-dhl-freight-test-connection-result]';

const renderState = (resultEl, state, message = '') => {
    if (!resultEl) {
        return;
    }
    resultEl.classList.remove('text-success', 'text-danger', 'text-muted');
    if (state === 'loading') {
        resultEl.classList.add('text-muted');
        resultEl.textContent = 'Pruefe Verbindung …';
    } else if (state === 'success') {
        resultEl.classList.add('text-success');
        resultEl.textContent = `✓ ${message}`;
    } else if (state === 'error') {
        resultEl.classList.add('text-danger');
        resultEl.textContent = `✗ ${message}`;
    } else {
        resultEl.textContent = '';
    }
};

const handleClick = async (button) => {
    const url = button.getAttribute('data-url');
    if (!url) {
        return;
    }
    const resultEl = document.querySelector(SELECTOR_RESULT);

    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    renderState(resultEl, 'loading');

    try {
        const payload = await fetchJson(url, {
            method: 'POST',
            csrfToken: getCsrfToken(),
        });
        const message = (payload && typeof payload.message === 'string')
            ? payload.message
            : '';
        if (payload && payload.ok === true) {
            renderState(resultEl, 'success', message || 'Verbindung erfolgreich.');
        } else {
            renderState(resultEl, 'error', message || 'Verbindung fehlgeschlagen.');
        }
    } catch (error) {
        const message = error instanceof Error && error.message
            ? error.message
            : 'Verbindung zum Server fehlgeschlagen.';
        renderState(resultEl, 'error', message);
    } finally {
        button.disabled = false;
        button.removeAttribute('aria-busy');
    }
};

const init = () => {
    const button = document.querySelector(SELECTOR_BUTTON);
    if (!button) {
        return;
    }
    button.addEventListener('click', () => { void handleClick(button); });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

export { init };
