/**
 * HTTP utility functions
 * @module core/http
 */

/**
 * Reads the CSRF token from the standard Laravel meta tag.
 * Fallback: hidden `_token` input (used in classic Blade forms).
 *
 * Engineering-Handbuch §75.4: Eine Stelle für jede API-Header-Logik —
 * keine duplizierten `meta[name="csrf-token"]`-Lookups.
 *
 * @returns {string} The CSRF token, or an empty string if none is present.
 */
export const getCsrfToken = () => {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta?.getAttribute('content')) {
        return meta.getAttribute('content');
    }
    const input = document.querySelector('input[name="_token"]');
    return input?.value ?? '';
};

/**
 * Fetches JSON data from an endpoint
 * @param {string} url - The URL to fetch
 * @param {Object} options - Fetch options
 * @param {string} options.method - HTTP method (default: 'GET')
 * @param {any} options.body - Request body (will be JSON stringified)
 * @param {string} options.csrfToken - CSRF token for non-GET requests
 * @returns {Promise<Object>} Parsed JSON response
 * @throws {Error} If the request fails
 */
export const fetchJson = async (url, { method = 'GET', body = undefined, csrfToken = '' } = {}) => {
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    const options = { method, headers, credentials: 'same-origin' };

    if (method !== 'GET') {
        headers['Content-Type'] = 'application/json';

        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        options.body = body ? JSON.stringify(body) : JSON.stringify({});
    }

    const response = await fetch(url, options);
    const text = await response.text();

    let payload = {};

    if (text) {
        try {
            payload = JSON.parse(text);
        } catch (error) {
            payload = {};
        }
    }

    if (!response.ok) {
        const message = payload?.message ?? `Fehler (${response.status})`;
        throw new Error(message);
    }

    return payload;
};


