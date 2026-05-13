/**
 * Frontend service layer for the DHL "allowed services" catalog endpoints.
 *
 * Engineering-Handbuch:
 *  - §35-§39 Frontend-Schichten: Komponenten rufen NICHT direkt `fetch` —
 *    sie reichen alle Catalog-Calls durch dieses Modul.
 *  - §45 zentraler API-Layer: eine Stelle pro Endpoint, AbortController-fähig.
 *  - §75 DRY: Auth-Error-/HTTP-Error-Behandlung einmalig (statt in jedem
 *    Komponenten-Controller).
 *
 * Endpoints (definiert in PROJ-5 t26):
 *  - GET  /api/admin/dhl/catalog/allowed-services
 *  - POST /api/admin/dhl/catalog/allowed-services/intersection
 *
 * @module domains/fulfillment/services/dhl-allowed-services-service
 */

import { getCsrfToken } from '../../../core/http.js';

/**
 * Domain error class for the service layer.
 * Components can branch on `kind` to render a meaningful state.
 */
export class DhlAllowedServicesError extends Error {
    /**
     * @param {'auth'|'forbidden'|'validation'|'not_found'|'server'|'network'|'aborted'} kind
     * @param {string} message
     * @param {object|null} payload Server payload (validation errors etc.)
     */
    constructor(kind, message, payload = null) {
        super(message);
        this.name = 'DhlAllowedServicesError';
        this.kind = kind;
        this.payload = payload;
    }
}

const buildHeaders = (method) => {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    if (method !== 'GET') {
        headers['Content-Type'] = 'application/json';
        const token = getCsrfToken();
        if (token) {
            headers['X-CSRF-TOKEN'] = token;
        }
    }
    return headers;
};

const mapHttpError = (status, payload) => {
    if (status === 401) {
        return new DhlAllowedServicesError('auth', 'Bitte erneut anmelden.', payload);
    }
    if (status === 403) {
        return new DhlAllowedServicesError('forbidden', 'Keine Berechtigung für den DHL-Katalog.', payload);
    }
    if (status === 404) {
        return new DhlAllowedServicesError('not_found', 'Endpoint nicht gefunden.', payload);
    }
    if (status === 422) {
        return new DhlAllowedServicesError(
            'validation',
            payload?.message || 'Ungültige Eingaben.',
            payload,
        );
    }
    return new DhlAllowedServicesError(
        'server',
        payload?.message || `Server-Fehler (${status}).`,
        payload,
    );
};

const parseResponse = async (response) => {
    const text = await response.text();
    if (!text) {
        return {};
    }
    try {
        return JSON.parse(text);
    } catch (_err) {
        return {};
    }
};

const performRequest = async (url, { method = 'GET', body = undefined, signal } = {}) => {
    let response;
    try {
        response = await fetch(url, {
            method,
            headers: buildHeaders(method),
            credentials: 'same-origin',
            signal,
            body: body !== undefined ? JSON.stringify(body) : undefined,
        });
    } catch (err) {
        if (err && err.name === 'AbortError') {
            throw new DhlAllowedServicesError('aborted', 'Anfrage abgebrochen.');
        }
        throw new DhlAllowedServicesError('network', 'Netzwerk-Fehler.');
    }

    const payload = await parseResponse(response);

    if (!response.ok) {
        throw mapHttpError(response.status, payload);
    }
    return payload;
};

/**
 * Endpoint URL resolution — components can pass an explicit URL (from
 * route('api.dhl.catalog.allowed-services') on the server), but we keep a
 * sane default to avoid breaking on missing config.
 */
const DEFAULT_SHOW_URL = '/api/admin/dhl/catalog/allowed-services';
const DEFAULT_INTERSECTION_URL = '/api/admin/dhl/catalog/allowed-services/intersection';

/**
 * Loads the allowed services for ONE routing context.
 *
 * @param {object} params
 * @param {string} params.productCode
 * @param {string} params.fromCountry  ISO-3166-1 alpha-2
 * @param {string} params.toCountry    ISO-3166-1 alpha-2
 * @param {string} params.payerCode    SENDER | RECIPIENT | THIRD_PARTY
 * @param {string} [params.endpointUrl]
 * @param {AbortSignal} [params.signal]
 * @returns {Promise<object>}          JSON payload from the server.
 * @throws {DhlAllowedServicesError}
 */
export const fetchAllowedServices = async ({
    productCode,
    fromCountry,
    toCountry,
    payerCode,
    endpointUrl,
    signal,
} = {}) => {
    if (!productCode || !fromCountry || !toCountry || !payerCode) {
        throw new DhlAllowedServicesError('validation', 'Routing-Daten unvollständig.');
    }
    const base = endpointUrl || DEFAULT_SHOW_URL;
    const url = new URL(base, window.location.origin);
    url.searchParams.set('product_code', productCode);
    url.searchParams.set('from_country', fromCountry);
    url.searchParams.set('to_country', toCountry);
    url.searchParams.set('payer_code', payerCode);

    return performRequest(url.pathname + url.search, { method: 'GET', signal });
};

/**
 * Loads the intersection of allowed services for multiple routings (bulk).
 *
 * @param {object} params
 * @param {Array<{product_code:string,from_country:string,to_country:string,payer_code:string}>} params.routings
 * @param {string} [params.endpointUrl]
 * @param {AbortSignal} [params.signal]
 * @returns {Promise<object>}
 * @throws {DhlAllowedServicesError}
 */
export const fetchIntersection = async ({ routings, endpointUrl, signal } = {}) => {
    if (!Array.isArray(routings) || routings.length === 0) {
        throw new DhlAllowedServicesError('validation', 'Routings fehlen.');
    }
    const url = endpointUrl || DEFAULT_INTERSECTION_URL;
    return performRequest(url, {
        method: 'POST',
        body: { routings },
        signal,
    });
};
