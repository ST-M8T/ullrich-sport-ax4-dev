/**
 * String utility functions
 * @module core/string
 */

/**
 * Escapes HTML special characters to prevent XSS
 * @param {string|null|undefined} value - The value to escape
 * @returns {string} Escaped HTML string
 */
export const escapeHtml = (value) => {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value).replace(/[&<>"']/g, (char) => {
        switch (char) {
            case '&':
                return '&amp;';
            case '<':
                return '&lt;';
            case '>':
                return '&gt;';
            case '"':
                return '&quot;';
            case '\'':
                return '&#039;';
            default:
                return char;
        }
    });
};


