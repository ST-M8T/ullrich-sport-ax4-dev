/**
 * Monitoring Modal
 * @module domains/monitoring/modal
 */

import { BaseModal } from '../../components/modal/base';

const SELECTOR_MODAL = '[data-monitoring-modal]';
const SELECTOR_MODAL_BODY = '[data-monitoring-modal-body]';
const SELECTOR_MODAL_TITLE = '[data-monitoring-modal-title]';
const SELECTOR_TRIGGER = '[data-monitoring-modal-target]';

class MonitoringModal extends BaseModal {
    /**
     * @param {HTMLElement} element
     */
    constructor(element) {
        super(element, {
            closeSelector: '[data-monitoring-modal-close]',
        });

        this.body = element.querySelector(SELECTOR_MODAL_BODY);
        this.title = element.querySelector(SELECTOR_MODAL_TITLE);
    }

    /**
     * @param {HTMLElement} trigger
     */
    open(trigger) {
        if (!this.body || !this.title) {
            return;
        }

        const targetId = trigger.getAttribute('data-monitoring-modal-target');

        if (!targetId) {
            return;
        }

        const template = document.getElementById(targetId);

        if (!template) {
            return;
        }

        this.body.innerHTML = template.innerHTML;
        this.title.textContent = trigger.getAttribute('data-monitoring-modal-title') ?? 'Details';

        super.open();
    }

    close() {
        super.close();

        if (this.body) {
            this.body.innerHTML = '';
        }
    }
}

function initialiseMonitoringModal() {
    const modalElement = document.querySelector(SELECTOR_MODAL);

    if (!(modalElement instanceof HTMLElement)) {
        return;
    }

    const modal = new MonitoringModal(modalElement);

    document.querySelectorAll(SELECTOR_TRIGGER).forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            modal.open(trigger);
        });
    });
}

document.addEventListener('DOMContentLoaded', initialiseMonitoringModal);


