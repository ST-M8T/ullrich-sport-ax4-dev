/**
 * HTTP utility functions
 * @module core/http
 */

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
    const headers = { Accept: 'application/json' };
    const options = { method, headers };

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


