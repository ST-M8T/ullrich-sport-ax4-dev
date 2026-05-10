/**
 * Base Modal class
 * @module components/modal/base
 */

import { focusElement, getFocusableElements, trapFocus } from '../../core/a11y';

/**
 * Base class for modal dialogs
 * Provides common functionality for accessibility, focus management, and keyboard handling
 */
export class BaseModal {
    /**
     * @param {HTMLElement} element - The modal container element
     * @param {Object} options - Configuration options
     * @param {string} options.dialogSelector - Selector for dialog element (default: '.app-modal__dialog')
     * @param {string} options.backdropSelector - Selector for backdrop element (default: '.app-modal__backdrop')
     * @param {string} options.closeSelector - Selector for close buttons (default: '[data-modal-close]')
     */
    constructor(element, options = {}) {
        if (!(element instanceof HTMLElement)) {
            throw new Error('BaseModal requires an HTMLElement');
        }

        this.element = element;
        this.dialogSelector = options.dialogSelector || '.app-modal__dialog';
        this.backdropSelector = options.backdropSelector || '.app-modal__backdrop';
        this.closeSelector = options.closeSelector || '[data-modal-close]';

        this.dialog = element.querySelector(this.dialogSelector) ?? element;
        this.backdrop = element.querySelector(this.backdropSelector);
        this.closeButtons = Array.from(element.querySelectorAll(this.closeSelector));
        this.lastFocusedElement = null;
        this.focusableElements = [];

        this.handleKeydown = this.handleKeydown.bind(this);

        if (this.dialog && !this.dialog.hasAttribute('tabindex')) {
            this.dialog.setAttribute('tabindex', '-1');
        }

        this.ensureAccessibility();
        this.registerCloseHandlers();
    }

    /**
     * Ensures modal has proper ARIA attributes
     */
    ensureAccessibility() {
        if (!this.element.hasAttribute('role')) {
            this.element.setAttribute('role', 'dialog');
        }

        this.element.setAttribute('aria-modal', 'true');
        this.element.setAttribute('aria-hidden', this.element.classList.contains('is-open') ? 'false' : 'true');
    }

    /**
     * Registers close button and backdrop click handlers
     */
    registerCloseHandlers() {
        this.closeButtons.forEach((button) => {
            button.addEventListener('click', () => this.close());
        });

        if (this.backdrop) {
            this.backdrop.addEventListener('click', () => this.close());
        }
    }

    /**
     * Checks if modal is currently open
     * @returns {boolean}
     */
    isOpen() {
        return this.element.classList.contains('is-open');
    }

    /**
     * Opens the modal
     * @param {Object} options - Open options
     * @param {HTMLElement} options.focusTarget - Element to focus (default: first focusable or dialog)
     */
    open(options = {}) {
        this.lastFocusedElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;

        this.element.classList.add('is-open');
        this.element.setAttribute('aria-hidden', 'false');
        document.body.classList.add('app-modal-open');

        document.addEventListener('keydown', this.handleKeydown, true);

        this.focusableElements = getFocusableElements(this.dialog ?? this.element);
        const focusTarget = options.focusTarget ?? this.focusableElements[0] ?? this.closeButtons[0] ?? this.dialog;
        focusElement(focusTarget);
    }

    /**
     * Closes the modal
     */
    close() {
        if (!this.isOpen()) {
            return;
        }

        this.element.classList.remove('is-open');
        this.element.setAttribute('aria-hidden', 'true');

        if (!document.querySelector('.app-modal.is-open')) {
            document.body.classList.remove('app-modal-open');
        }

        document.removeEventListener('keydown', this.handleKeydown, true);

        this.focusableElements = [];

        if (this.lastFocusedElement) {
            focusElement(this.lastFocusedElement);
            this.lastFocusedElement = null;
        }
    }

    /**
     * Handles keyboard events
     * @param {KeyboardEvent} event
     */
    handleKeydown(event) {
        if (!this.isOpen()) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            this.close();
            return;
        }

        trapFocus(event, this.focusableElements);
    }
}

