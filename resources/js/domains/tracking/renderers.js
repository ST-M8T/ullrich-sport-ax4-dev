/**
 * Renderer functions for tracking overview
 * @module domains/tracking/renderers
 */

import { escapeHtml } from '../../core/string';
import { formatJson, hasStructuredContent } from '../../core/json';

/**
 * Serializes a date row for display
 * @param {string} label - The label
 * @param {string} value - The value
 * @returns {string} HTML string
 */
export const serializeDateRow = (label, value) => {
    if (!value) {
        return '';
    }

    return `<div><strong>${escapeHtml(label)}:</strong> ${escapeHtml(value)}</div>`;
};

/**
 * Gets CSS class for job status badge
 * @param {string} status - Job status
 * @returns {string} CSS class
 */
export const jobStatusClass = (status) => {
    switch (status) {
        case 'completed':
            return 'bg-success';
        case 'failed':
            return 'bg-danger';
        case 'running':
            return 'bg-info';
        default:
            return 'bg-secondary';
    }
};

/**
 * Gets CSS class for alert severity badge
 * @param {string} severity - Alert severity
 * @returns {string} CSS class
 */
export const alertSeverityClass = (severity) => {
    switch (severity) {
        case 'critical':
        case 'error':
            return 'bg-danger';
        case 'warning':
            return 'bg-warning text-dark';
        default:
            return 'bg-secondary';
    }
};

/**
 * Renders details rows into a container
 * @param {HTMLElement} container - Container element
 * @param {Array<[string, string]>} rows - Array of [label, value] tuples
 */
export const renderDetails = (container, rows) => {
    if (!container) {
        return;
    }

    container.innerHTML = rows
        .map(([label, value]) => {
            return `
                <div class="modal-details__row">
                    <div class="modal-details__label">${escapeHtml(label)}</div>
                    <div class="modal-details__value">${escapeHtml(value ?? '—')}</div>
                </div>
            `;
        })
        .join('');
};

/**
 * Renders job history into a container
 * @param {HTMLElement} container - Container element
 * @param {Array} history - History items
 * @param {string|number} currentId - Current job ID
 */
export const renderJobHistory = (container, history, currentId) => {
    if (!container) {
        return;
    }

    if (!Array.isArray(history) || history.length === 0) {
        container.textContent = 'Keine weiteren Einträge.';
        return;
    }

    const list = history
        .map((item) => {
            const isCurrent = Number(item.id) === Number(currentId);
            const errorDisplay = item.last_error ? `<div class="mt-1 text-danger small">${escapeHtml(item.last_error)}</div>` : '';

            const metaParts = [
                `Status: ${escapeHtml(item.status ?? 'unbekannt')}`,
                `Versuche: ${escapeHtml(item.attempt ?? 0)}`,
            ];

            if (item.scheduled_at) {
                metaParts.push(`Scheduled: ${escapeHtml(item.scheduled_at)}`);
            }

            if (item.finished_at) {
                metaParts.push(`Finished: ${escapeHtml(item.finished_at)}`);
            }

            return `
                <li class="modal-history__item ${isCurrent ? 'modal-history__item--current' : ''}">
                    <div class="modal-history__title">${escapeHtml(item.job_type ?? 'Job')} · #${escapeHtml(item.id ?? '')}</div>
                    <div class="modal-history__meta">${metaParts.map((part) => `<span>${part}</span>`).join('')}</div>
                    ${errorDisplay}
                </li>
            `;
        })
        .join('');

    container.innerHTML = `<ul class="modal-history__list">${list}</ul>`;
};

/**
 * Renders alert history into a container
 * @param {HTMLElement} container - Container element
 * @param {Array} related - Related alerts
 * @param {string|number} currentId - Current alert ID
 */
export const renderAlertHistory = (container, related, currentId) => {
    if (!container) {
        return;
    }

    if (!Array.isArray(related) || related.length === 0) {
        container.textContent = 'Keine weiteren Alerts.';
        return;
    }

    const list = related
        .map((item) => {
            const isCurrent = Number(item.id) === Number(currentId);
            const metaParts = [
                `Severity: ${escapeHtml(item.severity ?? 'unknown')}`,
            ];

            if (item.created_at) {
                metaParts.push(`Erstellt: ${escapeHtml(item.created_at)}`);
            }

            if (item.acknowledged_at) {
                metaParts.push(`Bestätigt: ${escapeHtml(item.acknowledged_at)}`);
            }

            return `
                <li class="modal-history__item ${isCurrent ? 'modal-history__item--current' : ''}">
                    <div class="modal-history__title">${escapeHtml(item.alert_type ?? 'Alert')} · #${escapeHtml(item.id ?? '')}</div>
                    <div class="modal-history__meta">${metaParts.map((part) => `<span>${part}</span>`).join('')}</div>
                </li>
            `;
        })
        .join('');

    container.innerHTML = `<ul class="modal-history__list">${list}</ul>`;
};

/**
 * Sets JSON content on an element
 * @param {HTMLElement} element - Element to update
 * @param {any} data - Data to display
 * @param {string} emptyPlaceholder - Placeholder text when empty
 */
export const setJsonContent = (element, data, emptyPlaceholder) => {
    if (!element) {
        return;
    }

    if (hasStructuredContent(data)) {
        element.textContent = formatJson(data);
        element.classList.remove('text-muted');
    } else {
        element.textContent = emptyPlaceholder;
        element.classList.add('text-muted');
    }
};


