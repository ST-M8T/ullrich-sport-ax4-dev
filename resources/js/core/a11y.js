/**
 * Accessibility utility functions
 * @module core/a11y
 */

const FOCUSABLE_SELECTORS = [
    'a[href]',
    'area[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
    '[role="button"]:not([aria-disabled="true"])',
];

const isHTMLElement = (element) => element instanceof HTMLElement || element instanceof HTMLButtonElement;

const isVisible = (element) => {
    if (!isHTMLElement(element)) {
        return false;
    }

    const style = window.getComputedStyle(element);

    if (style.visibility === 'hidden' || style.display === 'none') {
        return false;
    }

    const rect = element.getBoundingClientRect();
    return rect.height > 0 && rect.width > 0;
};

/**
 * Gets all focusable elements within a root element
 * @param {Element} root - The root element to search within
 * @returns {HTMLElement[]} Array of focusable elements
 */
export const getFocusableElements = (root) => {
    if (!(root instanceof Element)) {
        return [];
    }

    return Array.from(root.querySelectorAll(FOCUSABLE_SELECTORS.join(','))).filter((element) => {
        if (!(element instanceof HTMLElement)) {
            return false;
        }

        if (element.hasAttribute('disabled') || element.getAttribute('aria-hidden') === 'true') {
            return false;
        }

        const tabIndex = element.getAttribute('tabindex');
        if (tabIndex && Number(tabIndex) < 0) {
            return false;
        }

        return isVisible(element);
    });
};

/**
 * Focuses an element with scroll prevention
 * @param {HTMLElement} element - The element to focus
 */
export const focusElement = (element) => {
    if (!isHTMLElement(element)) {
        return;
    }

    try {
        element.focus({ preventScroll: true });
    } catch (error) {
        element.focus();
    }
};

/**
 * Traps focus within a set of focusable elements
 * @param {KeyboardEvent} event - The keyboard event
 * @param {HTMLElement[]} focusableElements - Array of focusable elements
 * @returns {boolean} True if focus was trapped
 */
export const trapFocus = (event, focusableElements) => {
    if (event.key !== 'Tab') {
        return false;
    }

    const elements = Array.isArray(focusableElements) ? focusableElements.filter(isHTMLElement) : [];

    if (elements.length === 0) {
        return false;
    }

    const first = elements[0];
    const last = elements[elements.length - 1];
    const activeElement = document.activeElement;

    if (event.shiftKey) {
        if (activeElement === first || !elements.includes(activeElement)) {
            event.preventDefault();
            focusElement(last);
            return true;
        }
        return false;
    }

    if (activeElement === last) {
        event.preventDefault();
        focusElement(first);
        return true;
    }

    return false;
};

/**
 * Checks if user prefers reduced motion
 * @returns {boolean} True if reduced motion is preferred
 */
export const prefersReducedMotion = () =>
    window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false;


