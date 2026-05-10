/**
 * UI utility functions
 * @module core/ui
 */

/**
 * Sets status message on an element with type-based styling
 * @param {HTMLElement|null} element - The element to update
 * @param {string} message - The message to display
 * @param {string} type - Status type: 'success', 'error', 'info' (default: 'success')
 */
export const setStatus = (element, message, type = 'success') => {
    if (!element) {
        return;
    }

    element.classList.remove('alert-success', 'alert-danger', 'alert-info', 'd-none');

    if (!message) {
        element.textContent = '';
        element.classList.add('d-none');
        return;
    }

    element.textContent = message;

    if (type === 'error') {
        element.classList.add('alert-danger');
    } else if (type === 'info') {
        element.classList.add('alert-info');
    } else {
        element.classList.add('alert-success');
    }
};

/**
 * Applies loading state to a button during async operation
 * @param {HTMLElement|null} button - The button element
 * @param {Function} callback - Async callback function
 * @returns {Promise<any>} Result of the callback
 */
export const withLoadingState = async (button, callback) => {
    if (!button) {
        return callback();
    }

    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Bitte warten…';

    try {
        return await callback();
    } finally {
        button.disabled = false;
        button.textContent = originalText;
    }
};


