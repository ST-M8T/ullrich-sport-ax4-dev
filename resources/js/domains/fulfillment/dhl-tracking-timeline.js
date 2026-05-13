/**
 * DHL Tracking Timeline — Refresh-Handler.
 *
 * Bindet auf [data-action="dhl-tracking-refresh"] und laedt die Tracking-Events
 * von /api/admin/dhl/tracking/{trackingNumber}/events neu. Rebuilds die Timeline
 * im naechsten [data-tracking-timeline-wrapper] der zugehoerigen Container-Section.
 *
 * Engineering-Handbuch:
 *  - §40 Keine Inline-Scripts/onclick — Event-Delegation auf document.
 *  - §51 A11y: aria-busy waehrend Loading.
 *  - §53 Klare Zustaende: idle / loading / success / error.
 *  - §75.3 Keine duplizierte JS-Logik — escapeHtml aus core/string.
 *  - §75.4 Keine doppelten API-Calls — fetchJson aus core/http.
 *
 * @module domains/fulfillment/dhl-tracking-timeline
 */

import { escapeHtml } from '../../core/string';
import { fetchJson } from '../../core/http';

const ACTION_REFRESH = 'dhl-tracking-refresh';
const SELECTOR_ROOT = '.dhl-tracking-timeline';
const SELECTOR_WRAPPER = '[data-tracking-timeline-wrapper]';
const SELECTOR_STATUS = '[data-tracking-current-status]';
const SELECTOR_STATUS_LABEL = '[data-tracking-current-status-label]';
const SELECTOR_STATUS_CODE = '[data-tracking-current-status-code]';

const DEFAULT_BTN_LABEL = '<span class="refresh-icon me-1">&#8635;</span> Aktualisieren';
const LOADING_BTN_LABEL = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Lädt...';

const formatDate = (dateStr) => {
    if (!dateStr) {
        return '';
    }
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const formatTime = (dateStr) => {
    if (!dateStr) {
        return '';
    }
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    return date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
};

const buildEventHtml = (event, isFirst, isLast, deliveredFirst) => {
    const isManualSync = event.event_code === 'MANUAL_SYNC';
    const dotColor = isManualSync ? 'secondary' : 'primary';
    const borderClass = isLast ? '' : 'border-bottom';
    const connector = isLast ? '' : '<div class="vr h-100" style="width: 2px; min-height: 40px;"></div>';

    const badge = deliveredFirst && isFirst
        ? '<span class="badge bg-success ms-2">Zugestellt</span>'
        : (isManualSync ? '<span class="badge bg-secondary ms-2">Sync</span>' : '');

    const description = event.description
        ? `<div class="text-body mb-1">${escapeHtml(event.description)}</div>`
        : '';

    const locationParts = [event.facility, event.city, event.country].filter((part) => part);
    const location = locationParts.length
        ? `<div class="text-muted small"><span class="me-1">&#128205;</span>${locationParts.map(escapeHtml).join(' ')}</div>`
        : '';

    return `
        <div class="timeline-item mb-3 pb-3 ${borderClass}"
             data-event-code="${escapeHtml(event.event_code || '')}"
             data-occurred-at="${escapeHtml(event.occurred_at || '')}">
            <div class="d-flex gap-3">
                <div class="timeline-dot-wrapper d-flex flex-column align-items-center">
                    <div class="rounded-circle bg-${dotColor} bg-opacity-20 border border-${dotColor} text-${dotColor} d-flex align-items-center justify-content-center" style="width: 12px; height: 12px; min-width: 12px;"></div>
                    ${connector}
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div>
                            <span class="fw-semibold me-2">${escapeHtml(event.label || event.event_code || '—')}</span>
                            ${badge}
                        </div>
                        <small class="text-muted text-end">
                            ${escapeHtml(formatDate(event.occurred_at))}
                            <br>
                            ${escapeHtml(formatTime(event.occurred_at))}
                        </small>
                    </div>
                    ${description}
                    ${location}
                </div>
            </div>
        </div>
    `;
};

const rebuildTimeline = (wrapperEl, events, isDelivered) => {
    const html = events
        .map((event, index) => buildEventHtml(event, index === 0, index === events.length - 1, isDelivered))
        .join('');
    wrapperEl.innerHTML = `<div class="timeline">${html}</div>`;
};

const updateCurrentStatus = (rootEl, currentStatus) => {
    if (!currentStatus) {
        return;
    }
    const labelEl = rootEl.querySelector(SELECTOR_STATUS_LABEL);
    const codeEl = rootEl.querySelector(SELECTOR_STATUS_CODE);
    if (labelEl) {
        labelEl.textContent = currentStatus.label || currentStatus.code || '—';
    }
    if (codeEl) {
        codeEl.textContent = currentStatus.code || '';
    }
};

const setButtonLoading = (btn, loading) => {
    btn.disabled = loading;
    if (loading) {
        btn.setAttribute('aria-busy', 'true');
        btn.innerHTML = LOADING_BTN_LABEL;
    } else {
        btn.removeAttribute('aria-busy');
        btn.innerHTML = DEFAULT_BTN_LABEL;
    }
};

const handleRefresh = async (btn) => {
    const rootEl = btn.closest(SELECTOR_ROOT);
    if (!rootEl) {
        return;
    }

    const trackingNumber = rootEl.dataset.trackingNumber || '';
    const wrapperEl = rootEl.querySelector(SELECTOR_WRAPPER);
    if (!trackingNumber || !wrapperEl) {
        return;
    }

    setButtonLoading(btn, true);

    try {
        const url = `/api/admin/dhl/tracking/${encodeURIComponent(trackingNumber)}/events`;
        const data = await fetchJson(url);

        if (data && data.success && Array.isArray(data.events)) {
            rebuildTimeline(wrapperEl, data.events, Boolean(data.is_delivered));
            updateCurrentStatus(rootEl, data.current_status);
        } else {
            window.alert(data?.error || 'Fehler beim Laden der Tracking-Daten');
        }
    } catch (error) {
        console.error('DHL tracking refresh failed:', error);
        window.alert(error?.message || 'Fehler bei der Verbindung');
    } finally {
        setButtonLoading(btn, false);
    }
};

const onDocumentClick = (event) => {
    const target = event.target instanceof Element ? event.target.closest(`[data-action="${ACTION_REFRESH}"]`) : null;
    if (!target) {
        return;
    }
    event.preventDefault();
    handleRefresh(target);
};

if (typeof document !== 'undefined') {
    document.addEventListener('click', onDocumentClick);
}
