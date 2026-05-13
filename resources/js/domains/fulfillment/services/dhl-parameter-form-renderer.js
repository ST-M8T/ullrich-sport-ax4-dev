/**
 * DHL-Service-Parameter-Form-Renderer.
 *
 * Wandelt ein JSON-Schema (DHL parameterSchema) in Bootstrap-5-Formularfelder
 * um. Wird sowohl vom Versandprofil-Form (PROJ-4) als auch vom Buchungs-/
 * Akkordeon-UI (PROJ-5) genutzt — eine einzige Quelle für die Render-Logik.
 *
 * Engineering-Handbuch:
 *  - §75 DRY: zentrale Stelle für JSON-Schema → DOM-Mapping. Keine Kopien in
 *    Domain-/Profil-/Buchungs-Modulen.
 *  - §35-§39 Frontend-Schichten: reine UI-Transformation, kein API-/State-Zugriff.
 *  - §51 A11y: jedes Feld hat ein verknüpftes `<label for>`, Pflichtfelder
 *    haben `*` + `aria-required="true"`.
 *  - §62 KISS: unterstützte Schema-Features:
 *      type: string | integer | number | boolean | object (max. 2 Tiefen)
 *      enum, default, format=date|date-time|email|phone|tel
 *      minimum/maximum (number), minLength/maxLength/pattern (string)
 *    Tiefer/oneOf/anyOf → Fallback `<textarea>` mit JSON-Hinweis.
 *
 * @module domains/fulfillment/services/dhl-parameter-form-renderer
 */

const MAX_NESTING_DEPTH = 2;

const fieldIdFor = (prefix, propertyName) =>
    `${prefix}__${propertyName}`.replace(/[^a-zA-Z0-9_-]/g, '_');

const buildFieldName = (prefix, propertyName) =>
    `${prefix}[parameters][${propertyName}]`;

const buildNestedFieldName = (prefix, propertyName) =>
    `${prefix}[${propertyName}]`;

const createLabel = (forId, text, required) => {
    const label = document.createElement('label');
    label.className = 'form-label small mb-1';
    label.setAttribute('for', forId);
    label.textContent = required ? `${text} *` : text;
    return label;
};

const labelTextFor = (propertyName, propertySchema) =>
    (propertySchema && (propertySchema.title || propertySchema.description)) || propertyName;

const valueOrDefault = (value, propertySchema) => {
    if (value !== undefined && value !== null && value !== '') {
        return value;
    }
    if (propertySchema && Object.prototype.hasOwnProperty.call(propertySchema, 'default')) {
        return propertySchema.default;
    }
    return undefined;
};

const isEnum = (propertySchema) =>
    propertySchema && Array.isArray(propertySchema.enum) && propertySchema.enum.length > 0;

const isBooleanType = (propertySchema) =>
    propertySchema && propertySchema.type === 'boolean';

const isNumericType = (propertySchema) =>
    propertySchema && (propertySchema.type === 'integer' || propertySchema.type === 'number');

const isObjectType = (propertySchema) =>
    propertySchema && propertySchema.type === 'object'
    && propertySchema.properties && typeof propertySchema.properties === 'object';

const isUnsupportedComplexSchema = (propertySchema) => {
    if (!propertySchema) {
        return false;
    }
    return Array.isArray(propertySchema.oneOf)
        || Array.isArray(propertySchema.anyOf)
        || Array.isArray(propertySchema.allOf);
};

const createEnumSelect = (input, propertySchema, value) => {
    input = document.createElement('select');
    input.className = 'form-select form-select-sm';
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = '— Bitte wählen —';
    input.appendChild(placeholder);
    propertySchema.enum.forEach((entry) => {
        const opt = document.createElement('option');
        opt.value = String(entry);
        opt.textContent = String(entry);
        if (value !== undefined && value !== null && String(value) === String(entry)) {
            opt.selected = true;
        }
        input.appendChild(opt);
    });
    return input;
};

const createBooleanInput = (wrapper, fieldName, value) => {
    // Hidden "0" first, then checkbox "1" — so unchecked posts "0".
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = fieldName;
    hidden.value = '0';
    wrapper.appendChild(hidden);

    const input = document.createElement('input');
    input.type = 'checkbox';
    input.className = 'form-check-input ms-1';
    input.value = '1';
    if (value === true || value === '1' || value === 1) {
        input.checked = true;
    }
    return input;
};

const createNumberInput = (propertySchema, value) => {
    const input = document.createElement('input');
    input.type = 'number';
    input.className = 'form-control form-control-sm';
    input.step = propertySchema.type === 'integer' ? '1' : 'any';
    if (typeof propertySchema.minimum === 'number') {
        input.min = String(propertySchema.minimum);
    }
    if (typeof propertySchema.maximum === 'number') {
        input.max = String(propertySchema.maximum);
    }
    if (value !== undefined && value !== null && value !== '') {
        input.value = String(value);
    }
    return input;
};

const createStringInput = (propertySchema, value) => {
    const format = typeof propertySchema.format === 'string' ? propertySchema.format : null;
    const maxLen = typeof propertySchema.maxLength === 'number' ? propertySchema.maxLength : null;

    let input;
    if (format === 'date') {
        input = document.createElement('input');
        input.type = 'date';
    } else if (format === 'date-time') {
        input = document.createElement('input');
        input.type = 'datetime-local';
    } else if (format === 'email') {
        input = document.createElement('input');
        input.type = 'email';
    } else if (format === 'phone' || format === 'tel') {
        input = document.createElement('input');
        input.type = 'tel';
        input.pattern = propertySchema.pattern || '^\\+?[0-9 .\\-()]{5,20}$';
    } else if (maxLen !== null && maxLen > 200) {
        input = document.createElement('textarea');
        input.rows = 3;
    } else {
        input = document.createElement('input');
        input.type = 'text';
    }

    input.className = (input.tagName === 'TEXTAREA' ? 'form-control form-control-sm' : 'form-control form-control-sm');

    if (typeof propertySchema.minLength === 'number') {
        input.minLength = propertySchema.minLength;
    }
    if (typeof propertySchema.maxLength === 'number') {
        input.maxLength = propertySchema.maxLength;
    }
    if (typeof propertySchema.pattern === 'string' && input.type !== 'tel') {
        input.pattern = propertySchema.pattern;
    }
    if (typeof value === 'string') {
        input.value = value;
    } else if (value !== undefined && value !== null) {
        input.value = String(value);
    }
    return input;
};

const createFallbackJsonTextarea = (propertySchema, value, fieldName, fieldId) => {
    const textarea = document.createElement('textarea');
    textarea.className = 'form-control form-control-sm font-monospace';
    textarea.rows = 4;
    textarea.id = fieldId;
    textarea.name = fieldName;
    textarea.placeholder = 'JSON';
    if (value !== undefined && value !== null) {
        try {
            textarea.value = typeof value === 'string' ? value : JSON.stringify(value, null, 2);
        } catch (_err) {
            textarea.value = '';
        }
    }
    const hint = document.createElement('small');
    hint.className = 'text-muted d-block mt-1';
    hint.textContent = 'Komplexes Schema — bitte JSON eingeben. Server-Validierung greift.';
    return { control: textarea, hint };
};

/**
 * Renders ONE parameter field from a JSON-Schema property.
 *
 * @param {object} propertySchema  JSON-Schema sub-object for this property.
 * @param {string} propertyName    Property key inside the schema.
 * @param {*}      value           Current value (from default_parameters or user input).
 * @param {string} fieldPrefix     Form-field prefix, e.g. "additional_services[COD]".
 * @param {boolean} required       Whether the property is in schema.required.
 * @param {number} depth           Internal recursion depth.
 * @returns {HTMLElement}          A `<div>` wrapper containing label + input.
 */
export const renderParameterField = (
    propertySchema,
    propertyName,
    value,
    fieldPrefix,
    required = false,
    depth = 0,
) => {
    const fieldId = fieldIdFor(fieldPrefix, propertyName);
    const fieldName = depth === 0
        ? buildFieldName(fieldPrefix, propertyName)
        : buildNestedFieldName(fieldPrefix, propertyName);

    const wrapper = document.createElement('div');
    wrapper.className = depth === 0 ? 'mb-2' : 'mb-2 ps-3 border-start';

    wrapper.appendChild(createLabel(fieldId, labelTextFor(propertyName, propertySchema), required));

    // Resolve effective value (user → schema default).
    const effectiveValue = valueOrDefault(value, propertySchema);

    // Unsupported complex schema → JSON fallback.
    if (isUnsupportedComplexSchema(propertySchema) || (isObjectType(propertySchema) && depth >= MAX_NESTING_DEPTH)) {
        const { control, hint } = createFallbackJsonTextarea(propertySchema, effectiveValue, fieldName, fieldId);
        if (required) {
            control.required = true;
            control.setAttribute('aria-required', 'true');
        }
        wrapper.appendChild(control);
        wrapper.appendChild(hint);
        return wrapper;
    }

    // Nested object (within depth limit) → recursive sub-form.
    if (isObjectType(propertySchema)) {
        const subRequired = Array.isArray(propertySchema.required) ? propertySchema.required : [];
        const subValues = (effectiveValue && typeof effectiveValue === 'object') ? effectiveValue : {};
        const nestedPrefix = fieldName;

        Object.keys(propertySchema.properties).forEach((nestedName) => {
            const nestedSchema = propertySchema.properties[nestedName];
            if (!nestedSchema || typeof nestedSchema !== 'object') {
                return;
            }
            const nestedValue = Object.prototype.hasOwnProperty.call(subValues, nestedName)
                ? subValues[nestedName]
                : undefined;
            const nestedField = renderParameterField(
                nestedSchema,
                nestedName,
                nestedValue,
                nestedPrefix,
                subRequired.includes(nestedName),
                depth + 1,
            );
            wrapper.appendChild(nestedField);
        });
        return wrapper;
    }

    let input;
    if (isEnum(propertySchema)) {
        input = createEnumSelect(null, propertySchema, effectiveValue);
    } else if (isBooleanType(propertySchema)) {
        input = createBooleanInput(wrapper, fieldName, effectiveValue);
    } else if (isNumericType(propertySchema)) {
        input = createNumberInput(propertySchema, effectiveValue);
    } else {
        input = createStringInput(propertySchema, effectiveValue);
    }

    input.id = fieldId;
    input.name = fieldName;
    if (required) {
        input.required = true;
        input.setAttribute('aria-required', 'true');
    }
    wrapper.appendChild(input);
    return wrapper;
};

/**
 * Renders the full parameter form for a service into the given container.
 * Idempotent: calling twice on the same container clears and re-renders.
 *
 * @param {HTMLElement} container   Target element to fill.
 * @param {object|null} schema      JSON-Schema (root has type:object, properties).
 * @param {object}      values      Current values map.
 * @param {string}      fieldPrefix Form-field prefix, e.g. "additional_services[COD]".
 */
export const renderParameterForm = (container, schema, values, fieldPrefix) => {
    container.innerHTML = '';
    if (!schema || typeof schema !== 'object' || !schema.properties || typeof schema.properties !== 'object') {
        return;
    }
    const required = Array.isArray(schema.required) ? schema.required : [];
    const safeValues = (values && typeof values === 'object') ? values : {};

    Object.keys(schema.properties).forEach((propertyName) => {
        const propertySchema = schema.properties[propertyName];
        if (!propertySchema || typeof propertySchema !== 'object') {
            return;
        }
        const field = renderParameterField(
            propertySchema,
            propertyName,
            Object.prototype.hasOwnProperty.call(safeValues, propertyName) ? safeValues[propertyName] : undefined,
            fieldPrefix,
            required.includes(propertyName),
            0,
        );
        container.appendChild(field);
    });
};

/**
 * True if a schema produces any visible field.
 * @param {object|null} schema
 * @returns {boolean}
 */
export const hasRenderableProperties = (schema) =>
    !!(schema && typeof schema === 'object'
        && schema.properties && typeof schema.properties === 'object'
        && Object.keys(schema.properties).length > 0);
