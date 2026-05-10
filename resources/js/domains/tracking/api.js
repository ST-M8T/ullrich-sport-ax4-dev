/**
 * API functions for tracking overview
 * @module domains/tracking/api
 */

import { fetchJson } from '../../core/http';

/**
 * Fetches job data
 * @param {string} url - API endpoint URL
 * @param {string} csrfToken - CSRF token
 * @returns {Promise<Object>} Job data
 */
export const fetchJob = async (url, csrfToken) => {
    return fetchJson(url, { method: 'GET', csrfToken });
};

/**
 * Retries a job
 * @param {string} url - API endpoint URL
 * @param {string} csrfToken - CSRF token
 * @returns {Promise<Object>} Response data
 */
export const retryJob = async (url, csrfToken) => {
    return fetchJson(url, { method: 'POST', csrfToken });
};

/**
 * Marks a job as failed
 * @param {string} url - API endpoint URL
 * @param {string} reason - Failure reason
 * @param {string} csrfToken - CSRF token
 * @returns {Promise<Object>} Response data
 */
export const failJob = async (url, reason, csrfToken) => {
    return fetchJson(url, {
        method: 'POST',
        body: reason ? { reason } : {},
        csrfToken,
    });
};

/**
 * Fetches alert data
 * @param {string} url - API endpoint URL
 * @param {string} csrfToken - CSRF token
 * @returns {Promise<Object>} Alert data
 */
export const fetchAlert = async (url, csrfToken) => {
    return fetchJson(url, { method: 'GET', csrfToken });
};

/**
 * Acknowledges an alert
 * @param {string} url - API endpoint URL
 * @param {string} csrfToken - CSRF token
 * @returns {Promise<Object>} Response data
 */
export const acknowledgeAlert = async (url, csrfToken) => {
    return fetchJson(url, { method: 'POST', csrfToken });
};


