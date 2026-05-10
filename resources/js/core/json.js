/**
 * JSON utility functions
 * @module core/json
 */

/**
 * Formats a value as JSON string with indentation
 * @param {any} value - The value to format
 * @returns {string} Formatted JSON string
 */
export const formatJson = (value) => {
    if (value === null || value === undefined) {
        return '';
    }

    try {
        return JSON.stringify(value, null, 2);
    } catch (error) {
        return String(value);
    }
};

/**
 * Checks if a value has structured content (array/object with data)
 * @param {any} value - The value to check
 * @returns {boolean} True if value has structured content
 */
export const hasStructuredContent = (value) => {
    if (Array.isArray(value)) {
        return value.length > 0;
    }

    if (value && typeof value === 'object') {
        return Object.keys(value).length > 0;
    }

    return Boolean(value);
};


