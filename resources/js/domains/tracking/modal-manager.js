/**
 * Modal manager for tracking overview
 * @module domains/tracking/modal-manager
 */

import { BaseModal } from '../../components/modal/base';

/**
 * Manages modal state and accessibility for tracking overview modals
 */
export class TrackingModalManager {
    /**
     * @param {HTMLElement} modal - The modal element
     */
    constructor(modal) {
        if (!(modal instanceof HTMLElement)) {
            throw new Error('TrackingModalManager requires an HTMLElement');
        }

        this.modal = modal;
        this.baseModal = new BaseModal(modal, {
            dialogSelector: '.app-modal__dialog, .modal-dialog',
        });
    }

    /**
     * Opens the modal
     */
    open() {
        this.baseModal.open();
    }

    /**
     * Closes the modal
     */
    close() {
        this.baseModal.close();
    }

    /**
     * Checks if modal is open
     * @returns {boolean}
     */
    isOpen() {
        return this.baseModal.isOpen();
    }
}


