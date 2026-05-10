/**
 * Settings Modal Component
 * @module components/settings-modal
 */

import { BaseModal } from './modal/base';

class SettingsModal extends BaseModal {
    constructor(element) {
        super(element, {
            closeSelector: '[data-settings-modal-close]',
        });

        this.titleElement = element.querySelector('[data-settings-modal-title]');
        this.subtitleElement = element.querySelector('[data-settings-modal-subtitle]');
        this.bodyElement = element.querySelector('[data-settings-modal-body]');
    }

    open(trigger) {
        const templateId = trigger.getAttribute('data-settings-modal-template');

        if (!templateId || !this.bodyElement || !this.titleElement) {
            return;
        }

        const template = document.getElementById(templateId);

        if (!template) {
            return;
        }

        this.bodyElement.innerHTML = template.innerHTML;
        this.titleElement.textContent = trigger.getAttribute('data-settings-modal-title') ?? 'Details';

        super.open();
    }

    close() {
        super.close();

        if (this.bodyElement) {
            this.bodyElement.innerHTML = '';
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.querySelector('[data-settings-modal]');

    if (!(modalElement instanceof HTMLElement)) {
        return;
    }

    const modal = new SettingsModal(modalElement);

    document.querySelectorAll('[data-settings-modal-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            modal.open(trigger);
        });
    });
});
