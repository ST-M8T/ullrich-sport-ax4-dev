/**
 * DHL Katalog — Sync-Status-Polling + Audit-Diff-Dialog (PROJ-6 / t16).
 *
 * Engineering-Handbuch:
 *  - §40 Keine Inline-Scripts: alles in einem ESM-Modul.
 *  - §41 Keine globalen Variablen / DOM-Manipulation gekapselt.
 *  - §45 Kein Direktzugriff auf fetch in Komponenten — kapselt fetchJson.
 *  - §53 Klare Zustaende: idle / loading / running / success / error.
 *  - §75 Eine Stelle fuer das Sync-Polling und die Dialog-Logik.
 *
 * @module domains/fulfillment/dhl-catalog
 */

import { fetchJson } from '../../core/http';

const SELECTOR_POLL_ROOT = '[data-dhl-catalog-sync-poll]';
const SELECTOR_BANNER = '[data-dhl-catalog-sync-banner]';
const SELECTOR_BANNER_TITLE = '[data-dhl-catalog-sync-title]';
const SELECTOR_BANNER_DESCRIPTION = '[data-dhl-catalog-sync-description]';
const SELECTOR_TRIGGER_BTN = '[data-dhl-catalog-sync-trigger]';

const SELECTOR_DIFF_TRIGGER = '[data-dhl-audit-diff-trigger]';
const SELECTOR_DIFF_CLOSE = '[data-dhl-audit-diff-close]';

const LEVEL_CLASSES = {
    success: 'alert-success',
    danger: 'alert-danger',
    warning: 'alert-warning',
};

const formatDateTime = (iso) => {
    if (!iso) return '';
    try {
        const d = new Date(iso);
        return d.toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'short' });
    } catch (_e) {
        return iso;
    }
};

const updateBanner = (banner, payload) => {
    if (!banner) return;

    const titleEl = banner.querySelector(SELECTOR_BANNER_TITLE);
    const descEl = banner.querySelector(SELECTOR_BANNER_DESCRIPTION);
    const triggerBtn = banner.querySelector(SELECTOR_TRIGGER_BTN);

    let level;
    let title;
    let description;

    if (payload.running) {
        level = 'warning';
        title = 'DHL-Katalog-Sync laeuft …';
        description = payload.last_attempt_at
            ? `Gestartet ${formatDateTime(payload.last_attempt_at)}.`
            : '';
    } else if (payload.last_attempt_at && (!payload.last_success_at
        || new Date(payload.last_success_at) < new Date(payload.last_attempt_at))) {
        level = 'danger';
        title = `Letzter Sync fehlgeschlagen (${payload.consecutive_failures ?? 0} Fehler in Folge)`;
        description = payload.last_error
            ? String(payload.last_error).slice(0, 200)
            : 'Keine Fehlermeldung erfasst.';
    } else if (payload.last_success_at) {
        level = 'success';
        title = 'Letzter Sync erfolgreich';
        description = `Erfolgreich am ${formatDateTime(payload.last_success_at)} Uhr.`;
    } else {
        level = 'warning';
        title = 'Noch kein Sync ausgefuehrt';
        description = 'Bitte ersten DHL-Katalog-Sync starten.';
    }

    Object.values(LEVEL_CLASSES).forEach((cls) => banner.classList.remove(cls));
    banner.classList.add(LEVEL_CLASSES[level]);
    banner.dataset.level = level;

    if (titleEl) titleEl.textContent = title;
    if (descEl) descEl.textContent = description;

    if (triggerBtn) {
        triggerBtn.disabled = Boolean(payload.running);
        triggerBtn.setAttribute('aria-disabled', payload.running ? 'true' : 'false');
    }
};

const startPolling = () => {
    const root = document.querySelector(SELECTOR_POLL_ROOT);
    if (!root) return;

    const url = root.getAttribute('data-status-url');
    const intervalMs = Math.max(1000, parseInt(root.getAttribute('data-interval-ms') || '5000', 10));

    if (!url) return;

    const banner = document.querySelector(SELECTOR_BANNER);
    if (!banner) return;

    let timerId = null;
    let active = false;

    const tick = async () => {
        if (active) return;
        active = true;
        try {
            const payload = await fetchJson(url, { method: 'GET' });
            updateBanner(banner, payload);

            // Polling nur fortsetzen, solange ein Lauf erkannt wird ODER
            // der Anfangszustand "running" war.
            if (!payload.running && timerId !== null) {
                window.clearInterval(timerId);
                timerId = null;
            }
        } catch (_e) {
            // Bewusst stumm — Polling soll Hauptseite nicht stoeren.
        } finally {
            active = false;
        }
    };

    // Initial-Status sofort lesen; falls running → Intervall starten.
    tick().then(() => {
        if (banner.dataset.level === 'warning' && banner.textContent.includes('laeuft')) {
            timerId = window.setInterval(tick, intervalMs);
        }
    });

    // Beim Sync-Trigger-Submit: nach kurzer Verzoegerung Polling starten.
    const triggerBtn = banner.querySelector(SELECTOR_TRIGGER_BTN);
    if (triggerBtn) {
        triggerBtn.closest('form')?.addEventListener('submit', () => {
            triggerBtn.disabled = true;
            if (timerId === null) {
                timerId = window.setInterval(tick, intervalMs);
            }
        });
    }
};

const bindDiffDialogs = () => {
    document.querySelectorAll(SELECTOR_DIFF_TRIGGER).forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-dialog-target');
            if (!targetId) return;
            const dialog = document.getElementById(targetId);
            if (dialog && typeof dialog.showModal === 'function') {
                dialog.showModal();
            }
        });
    });

    document.querySelectorAll(SELECTOR_DIFF_CLOSE).forEach((btn) => {
        btn.addEventListener('click', () => {
            const dialog = btn.closest('dialog');
            if (dialog && typeof dialog.close === 'function') {
                dialog.close();
            }
        });
    });
};

const init = () => {
    if (!document.querySelector(SELECTOR_POLL_ROOT)
        && !document.querySelector(SELECTOR_DIFF_TRIGGER)) {
        return;
    }
    startPolling();
    bindDiffDialogs();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

export { init };
