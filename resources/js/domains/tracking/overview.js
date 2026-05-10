/**
 * Tracking Overview
 * @module domains/tracking/overview
 */

import { TrackingModalManager } from './modal-manager';
import { renderDetails, renderJobHistory, renderAlertHistory, setJsonContent, jobStatusClass, alertSeverityClass, serializeDateRow } from './renderers';
import { fetchJob, retryJob, failJob, fetchAlert, acknowledgeAlert } from './api';
import { setStatus, withLoadingState } from '../../core/ui';
import { escapeHtml } from '../../core/string';
import { formatJson, hasStructuredContent } from '../../core/json';

const initTrackingOverview = () => {
    const root = document.querySelector('[data-tracking-overview]');
    if (!root) {
        return;
    }

    const tabButtons = Array.from(root.querySelectorAll('[data-tab-button]'));
    const tabPanels = Array.from(root.querySelectorAll('[data-tab-panel]'));
    const initialTab = root.dataset.initialTab || 'jobs';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const jobModal = document.querySelector('[data-modal="job"]');
    const alertModal = document.querySelector('[data-modal="alert"]');

    if (!jobModal || !alertModal) {
        return;
    }

    const jobModalManager = new TrackingModalManager(jobModal);
    const alertModalManager = new TrackingModalManager(alertModal);

    const jobElements = {
        status: jobModal.querySelector('[data-modal-status]'),
        details: jobModal.querySelector('[data-job-modal-details]'),
        history: jobModal.querySelector('[data-job-modal-history]'),
        payload: jobModal.querySelector('[data-job-modal-payload]'),
        result: jobModal.querySelector('[data-job-modal-result]'),
        reason: jobModal.querySelector('[data-job-fail-reason]'),
        retryBtn: jobModal.querySelector('.js-job-retry'),
        failBtn: jobModal.querySelector('.js-job-fail'),
    };

    const alertElements = {
        status: alertModal.querySelector('[data-modal-status]'),
        details: alertModal.querySelector('[data-alert-modal-details]'),
        related: alertModal.querySelector('[data-alert-modal-related]'),
        metadata: alertModal.querySelector('[data-alert-modal-metadata]'),
        ackBtn: alertModal.querySelector('.js-alert-ack'),
    };

    const jobShowTemplate = root.dataset.jobShowTemplate || '';
    const jobRetryTemplate = root.dataset.jobRetryTemplate || '';
    const jobFailTemplate = root.dataset.jobFailTemplate || '';
    const alertShowTemplate = root.dataset.alertShowTemplate || '';
    const alertAckTemplate = root.dataset.alertAckTemplate || '';

    const activateTab = (tab) => {
        const target = tab || 'jobs';

        tabButtons.forEach((button) => {
            const isActive = (button.dataset.tabButton || 'jobs') === target;
            button.classList.toggle('btn-primary', isActive);
            button.classList.toggle('btn-outline-primary', !isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        tabPanels.forEach((panel) => {
            panel.hidden = (panel.dataset.tabPanel || 'jobs') !== target;
        });
    };

    const renderJobModal = (data) => {
        const job = data?.job;
        const history = data?.history ?? [];

        if (!job) {
            throw new Error('Job konnte nicht geladen werden.');
        }

        jobModal.dataset.jobId = String(job.id);

        renderDetails(jobElements.details, [
            ['Job-ID', job.id],
            ['Job-Typ', job.job_type],
            ['Status', job.status?.toUpperCase()],
            ['Versuche', job.attempt],
            ['Scheduled', job.scheduled_at ?? '—'],
            ['Started', job.started_at ?? '—'],
            ['Finished', job.finished_at ?? '—'],
            ['Created', job.created_at ?? '—'],
            ['Updated', job.updated_at ?? '—'],
            ['Letzter Fehler', job.last_error ?? '—'],
        ]);

        renderJobHistory(jobElements.history, history, job.id);
        setJsonContent(jobElements.payload, job.payload, 'Payload leer');
        setJsonContent(jobElements.result, job.result, 'Result leer');

        updateJobRow(job);

        if (data?.message) {
            setStatus(jobElements.status, data.message, 'success');
        } else {
            setStatus(jobElements.status, '', 'success');
        }
    };

    const renderAlertModal = (data) => {
        const alert = data?.alert;
        const related = data?.similar ?? [];

        if (!alert) {
            throw new Error('Alert konnte nicht geladen werden.');
        }

        alertModal.dataset.alertId = String(alert.id);

        renderDetails(alertElements.details, [
            ['Alert-ID', alert.id],
            ['Alert-Typ', alert.alert_type],
            ['Severity', alert.severity?.toUpperCase()],
            ['Channel', alert.channel ?? '—'],
            ['Nachricht', alert.message],
            ['Erstellt', alert.created_at ?? '—'],
            ['Gesendet', alert.sent_at ?? '—'],
            ['Bestätigt', alert.acknowledged_at ?? '—'],
        ]);

        renderAlertHistory(alertElements.related, related, alert.id);
        setJsonContent(alertElements.metadata, alert.metadata, 'Keine Daten');

        updateAlertRow(alert);

        if (data?.message) {
            setStatus(alertElements.status, data.message, 'success');
        } else {
            setStatus(alertElements.status, '', 'success');
        }

        if (alertElements.ackBtn) {
            if (alert.is_acknowledged) {
                alertElements.ackBtn.disabled = true;
                alertElements.ackBtn.textContent = 'Bereits bestätigt';
            } else {
                alertElements.ackBtn.disabled = false;
                alertElements.ackBtn.textContent = 'Acknowledge';
            }
        }
    };

    const updateJobRow = (job) => {
        const row = root.querySelector(`[data-job-row="${job.id}"]`);
        if (!row) {
            return;
        }

        const statusCell = row.querySelector('[data-job-status]');
        if (statusCell) {
            const badge = statusCell.querySelector('.badge');
            if (badge) {
                badge.className = `badge ${jobStatusClass(job.status)} text-uppercase`;
                badge.textContent = job.status ?? 'unbekannt';
            }
        }

        const timelineCell = row.querySelector('[data-job-timestamps]');
        if (timelineCell) {
            const fragments = [
                serializeDateRow('Scheduled', job.scheduled_at),
                serializeDateRow('Started', job.started_at),
                serializeDateRow('Finished', job.finished_at),
                serializeDateRow('Created', job.created_at),
            ].filter(Boolean);

            timelineCell.innerHTML = fragments.join('');
        }

        const attemptCell = row.querySelector('[data-job-attempt]');
        if (attemptCell) {
            attemptCell.textContent = job.attempt ?? '0';
        }

        const errorCell = row.querySelector('[data-job-error]');
        if (errorCell) {
            errorCell.textContent = job.last_error ?? '—';
        }

        const dataCell = row.querySelector('[data-job-data]');
        if (dataCell) {
            dataCell.innerHTML = '';

            if (hasStructuredContent(job.payload)) {
                const payloadDetails = document.createElement('details');
                payloadDetails.classList.add('mb-1');
                const summary = document.createElement('summary');
                summary.textContent = 'Payload';
                payloadDetails.appendChild(summary);
                const pre = document.createElement('pre');
                pre.classList.add('mb-0');
                pre.textContent = formatJson(job.payload);
                payloadDetails.appendChild(pre);
                dataCell.appendChild(payloadDetails);
            } else {
                const payloadEmpty = document.createElement('span');
                payloadEmpty.classList.add('text-muted');
                payloadEmpty.textContent = 'Payload leer';
                dataCell.appendChild(payloadEmpty);
            }

            if (hasStructuredContent(job.result)) {
                const resultDetails = document.createElement('details');
                const summary = document.createElement('summary');
                summary.textContent = 'Result';
                const pre = document.createElement('pre');
                pre.classList.add('mb-0');
                pre.textContent = formatJson(job.result);
                resultDetails.appendChild(summary);
                resultDetails.appendChild(pre);
                dataCell.appendChild(resultDetails);
            } else {
                const resultEmpty = document.createElement('span');
                resultEmpty.classList.add('text-muted');
                resultEmpty.textContent = 'Result leer';
                dataCell.appendChild(resultEmpty);
            }
        }
    };

    const updateAlertRow = (alert) => {
        const row = root.querySelector(`[data-alert-row="${alert.id}"]`);
        if (!row) {
            return;
        }

        const severityCell = row.querySelector('[data-alert-severity]');
        if (severityCell) {
            const badge = severityCell.querySelector('.badge');
            if (badge) {
                badge.className = `badge ${alertSeverityClass(alert.severity)} text-uppercase`;
                badge.textContent = alert.severity ?? 'info';
            }
        }

        const statusCell = row.querySelector('[data-alert-status]');
        if (statusCell) {
            let html = '';

            if (alert.created_at) {
                html += `<div><strong>Erstellt:</strong> ${escapeHtml(alert.created_at)}</div>`;
            }

            if (alert.sent_at) {
                html += `<div><strong>Gesendet:</strong> ${escapeHtml(alert.sent_at)}</div>`;
            }

            if (alert.acknowledged_at) {
                html += `<div><strong>Bestätigt:</strong> ${escapeHtml(alert.acknowledged_at)}</div>`;
            } else {
                html += '<span class="badge bg-warning text-dark mt-1">Offen</span>';
            }

            statusCell.innerHTML = html;
        }

        const metadataCell = row.querySelector('[data-alert-metadata]');
        if (metadataCell) {
            metadataCell.innerHTML = '';

            if (hasStructuredContent(alert.metadata)) {
                const details = document.createElement('details');
                const summary = document.createElement('summary');
                summary.textContent = 'Details';
                const pre = document.createElement('pre');
                pre.classList.add('mb-0');
                pre.textContent = formatJson(alert.metadata);
                details.appendChild(summary);
                details.appendChild(pre);
                metadataCell.appendChild(details);
            } else {
                const empty = document.createElement('span');
                empty.classList.add('text-muted');
                empty.textContent = 'Keine Daten';
                metadataCell.appendChild(empty);
            }
        }
    };

    const openJobModal = (jobId) => {
        if (!jobId || !jobShowTemplate) {
            return;
        }

        setStatus(jobElements.status, 'Lade Job-Details…', 'info');
        jobElements.details.innerHTML = '<p class="text-muted mb-0">Lade Details…</p>';
        jobElements.history.textContent = 'Lade Historie…';
        jobElements.payload.textContent = '–';
        jobElements.result.textContent = '–';
        if (jobElements.reason) {
            jobElements.reason.value = '';
        }

        jobModalManager.open();

        fetchJob(jobShowTemplate.replace('__JOB__', jobId), csrfToken)
            .then((data) => {
                renderJobModal(data);
            })
            .catch((error) => {
                setStatus(jobElements.status, error.message, 'error');
            });
    };

    const openAlertModal = (alertId) => {
        if (!alertId || !alertShowTemplate) {
            return;
        }

        setStatus(alertElements.status, 'Lade Alert-Details…', 'info');
        alertElements.details.innerHTML = '<p class="text-muted mb-0">Lade Details…</p>';
        alertElements.related.textContent = 'Lade weitere Alerts…';
        alertElements.metadata.textContent = '–';

        alertModalManager.open();

        fetchAlert(alertShowTemplate.replace('__ALERT__', alertId), csrfToken)
            .then((data) => {
                renderAlertModal(data);
            })
            .catch((error) => {
                setStatus(alertElements.status, error.message, 'error');
            });
    };

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => activateTab(button.dataset.tabButton || 'jobs'));
    });

    root.addEventListener('click', (event) => {
        const jobTrigger = event.target.closest('.js-open-job-modal');
        if (jobTrigger) {
            event.preventDefault();
            openJobModal(jobTrigger.getAttribute('data-job-id'));
            return;
        }

        const alertTrigger = event.target.closest('.js-open-alert-modal');
        if (alertTrigger) {
            event.preventDefault();
            openAlertModal(alertTrigger.getAttribute('data-alert-id'));
        }
    });

    jobElements.retryBtn?.addEventListener('click', () => {
        const jobId = jobModal.dataset.jobId;
        if (!jobId || !jobRetryTemplate) {
            return;
        }

        withLoadingState(jobElements.retryBtn, async () => {
            try {
                const data = await retryJob(jobRetryTemplate.replace('__JOB__', jobId), csrfToken);
                renderJobModal(data);
                setStatus(jobElements.status, data?.message ?? 'Job wurde erneut eingeplant.', 'success');
            } catch (error) {
                setStatus(jobElements.status, error.message, 'error');
            }
        });
    });

    jobElements.failBtn?.addEventListener('click', () => {
        const jobId = jobModal.dataset.jobId;
        if (!jobId || !jobFailTemplate) {
            return;
        }

        const reason = jobElements.reason?.value?.trim() ?? '';

        withLoadingState(jobElements.failBtn, async () => {
            try {
                const data = await failJob(jobFailTemplate.replace('__JOB__', jobId), reason, csrfToken);
                renderJobModal(data);
                setStatus(jobElements.status, data?.message ?? 'Job wurde als failed markiert.', 'success');
            } catch (error) {
                setStatus(jobElements.status, error.message, 'error');
            }
        });
    });

    alertElements.ackBtn?.addEventListener('click', () => {
        const alertId = alertModal.dataset.alertId;
        if (!alertId || !alertAckTemplate) {
            return;
        }

        withLoadingState(alertElements.ackBtn, async () => {
            try {
                const data = await acknowledgeAlert(alertAckTemplate.replace('__ALERT__', alertId), csrfToken);
                renderAlertModal(data);
                setStatus(alertElements.status, data?.message ?? 'Alert wurde bestätigt.', 'success');
            } catch (error) {
                setStatus(alertElements.status, error.message, 'error');
            }
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => {
            const modal = button.closest('[data-modal]');
            if (modal === jobModal) {
                jobModalManager.close();
            } else if (modal === alertModal) {
                alertModalManager.close();
            }
        });
    });

    [jobModal, alertModal].forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                if (modal === jobModal) {
                    jobModalManager.close();
                } else if (modal === alertModal) {
                    alertModalManager.close();
                }
            }
        });
    });

    activateTab(initialTab);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTrackingOverview);
} else {
    initTrackingOverview();
}


