/**
 * DHL Package Editor — Repeater fuer pieces[i][...] im Booking-UI.
 *
 * @module domains/fulfillment/dhl-package-editor
 *
 * Engineering-Handbuch:
 *   §52 Formularregel: Inline-Validation, Schutz vor Mehrfachabsendung,
 *        klare Fehlertexte. Backend-Validation bleibt Pflicht.
 *   §53 Loading/Empty-State: 0 Rows -> Hinweis + grayed-out Submit.
 *   §75 striktes DRY: Eine Quelle der Wahrheit fuer das Row-Markup
 *        (Server-Render der ersten Row wird via cloneNode als Template
 *        verwendet — keine duplizierte HTML-Struktur in JS).
 */

const SELECTORS = Object.freeze({
    form: '[data-package-editor-form]',
    rowsContainer: '[data-package-editor-rows]',
    row: '[data-package-editor-row]',
    addButton: '[data-package-editor-add]',
    removeButton: '[data-package-editor-remove]',
    submitButton: '[data-package-editor-submit]',
    submitLabel: '[data-package-editor-submit-label]',
    submitSpinner: '[data-package-editor-spinner]',
    emptyHint: '[data-package-editor-empty]',
});

const PIECE_NAME_PREFIX = 'pieces[';

/**
 * Index-Reset: Nach Add/Remove muss jedes pieces[i][...] in laufender
 * Reihenfolge nummeriert werden, sonst hat das Backend Luecken.
 */
function reindexRows(rowsContainer) {
    const rows = rowsContainer.querySelectorAll(SELECTORS.row);
    rows.forEach((row, index) => {
        const label = `Paket ${index + 1}`;
        row.setAttribute('aria-label', label);

        row.querySelectorAll('[name^="pieces["]').forEach((input) => {
            input.name = input.name.replace(/^pieces\[\d+\]/, `pieces[${index}]`);

            const ariaLabel = input.getAttribute('aria-label');
            if (ariaLabel) {
                input.setAttribute(
                    'aria-label',
                    ariaLabel.replace(/^Paket \d+/, label)
                );
            }
        });

        const removeButton = row.querySelector(SELECTORS.removeButton);
        if (removeButton) {
            removeButton.setAttribute('aria-label', `${label} entfernen`);
        }
    });
}

/**
 * Klont die erste Row als Template. Damit bleibt das HTML-Markup einer Row
 * ausschliesslich im Blade-Partial (§75.1 — keine doppelte UI-Struktur).
 */
function cloneRowFromTemplate(form, defaultPackageType) {
    const firstRow = form.querySelector(SELECTORS.row);
    if (!firstRow) {
        return null;
    }

    const clone = firstRow.cloneNode(true);

    clone.querySelectorAll('input').forEach((input) => {
        if (input.type === 'number') {
            input.value = input.name.endsWith('[number_of_pieces]') ? '1' : '';
        } else if (input.name && input.name.endsWith('[package_type]')) {
            input.value = defaultPackageType;
        } else {
            input.value = '';
        }
        input.classList.remove('is-invalid');
    });

    return clone;
}

function validateNumber(input, { min, allowEmpty }) {
    const raw = input.value.trim();
    if (raw === '') {
        return allowEmpty;
    }
    const value = Number(raw);
    return Number.isFinite(value) && value >= min;
}

function isRowValid(row) {
    const fields = [
        { sel: '[name$="[number_of_pieces]"]', min: 1, allowEmpty: false },
        { sel: '[name$="[weight]"]', min: 0.01, allowEmpty: false },
        { sel: '[name$="[length]"]', min: 1, allowEmpty: true },
        { sel: '[name$="[width]"]', min: 1, allowEmpty: true },
        { sel: '[name$="[height]"]', min: 1, allowEmpty: true },
    ];

    let allValid = true;

    fields.forEach(({ sel, min, allowEmpty }) => {
        const input = row.querySelector(sel);
        if (!input) {
            return;
        }
        const valid = validateNumber(input, { min, allowEmpty });
        input.classList.toggle('is-invalid', !valid);
        if (!valid) {
            allValid = false;
        }
    });

    return allValid;
}

function updateEmptyState(form) {
    const rowsContainer = form.querySelector(SELECTORS.rowsContainer);
    const submit = form.querySelector(SELECTORS.submitButton);
    const emptyHint = form.querySelector(SELECTORS.emptyHint);
    if (!rowsContainer || !submit) {
        return;
    }

    const rowCount = rowsContainer.querySelectorAll(SELECTORS.row).length;
    const isEmpty = rowCount === 0;

    submit.disabled = isEmpty;
    submit.classList.toggle('disabled', isEmpty);
    if (emptyHint) {
        emptyHint.classList.toggle('d-none', !isEmpty);
    }
}

function attachRowEvents(row) {
    row.querySelectorAll('input').forEach((input) => {
        input.addEventListener('input', () => {
            if (input.classList.contains('is-invalid')) {
                input.classList.remove('is-invalid');
            }
        });
        input.addEventListener('blur', () => {
            isRowValid(row);
        });
    });
}

function handleAdd(form) {
    const rowsContainer = form.querySelector(SELECTORS.rowsContainer);
    if (!rowsContainer) {
        return;
    }
    const defaultType = form.dataset.defaultPackageType || 'PAL';
    const newRow = cloneRowFromTemplate(form, defaultType);
    if (!newRow) {
        return;
    }
    rowsContainer.appendChild(newRow);
    attachRowEvents(newRow);
    reindexRows(rowsContainer);
    updateEmptyState(form);
}

function handleRemove(form, rowElement) {
    const rowsContainer = form.querySelector(SELECTORS.rowsContainer);
    if (!rowsContainer) {
        return;
    }
    rowElement.remove();
    reindexRows(rowsContainer);
    updateEmptyState(form);
}

function handleSubmit(form, event) {
    const rowsContainer = form.querySelector(SELECTORS.rowsContainer);
    const rows = rowsContainer
        ? rowsContainer.querySelectorAll(SELECTORS.row)
        : [];

    if (rows.length === 0) {
        event.preventDefault();
        updateEmptyState(form);
        return;
    }

    let allValid = true;
    rows.forEach((row) => {
        if (!isRowValid(row)) {
            allValid = false;
        }
    });

    if (!allValid) {
        event.preventDefault();
        const firstInvalid = form.querySelector('.is-invalid');
        if (firstInvalid) {
            firstInvalid.focus();
        }
        return;
    }

    // Schutz vor Mehrfachabsendung + Loading-State (§52, §53)
    const submit = form.querySelector(SELECTORS.submitButton);
    if (submit) {
        submit.disabled = true;
        submit.classList.add('disabled');
        submit.setAttribute('aria-busy', 'true');

        const labelEl = submit.querySelector(SELECTORS.submitLabel);
        const spinnerEl = submit.querySelector(SELECTORS.submitSpinner);
        const loadingText = submit.dataset.labelLoading || 'Sende ...';

        if (labelEl) {
            labelEl.textContent = loadingText;
        }
        if (spinnerEl) {
            spinnerEl.classList.remove('d-none');
        }
    }
}

function initForm(form) {
    if (form.dataset.packageEditorInitialised === '1') {
        return;
    }
    form.dataset.packageEditorInitialised = '1';

    const rowsContainer = form.querySelector(SELECTORS.rowsContainer);
    if (rowsContainer) {
        rowsContainer
            .querySelectorAll(SELECTORS.row)
            .forEach((row) => attachRowEvents(row));
        reindexRows(rowsContainer);
    }

    form.addEventListener('click', (event) => {
        const addTarget = event.target.closest(SELECTORS.addButton);
        if (addTarget && form.contains(addTarget)) {
            event.preventDefault();
            handleAdd(form);
            return;
        }

        const removeTarget = event.target.closest(SELECTORS.removeButton);
        if (removeTarget && form.contains(removeTarget)) {
            event.preventDefault();
            const row = removeTarget.closest(SELECTORS.row);
            if (row) {
                handleRemove(form, row);
            }
        }
    });

    // Add-Button kann ausserhalb des <form> liegen (z.B. im Card-Header).
    const externalAddButtons = document.querySelectorAll(SELECTORS.addButton);
    externalAddButtons.forEach((button) => {
        if (form.contains(button)) {
            return;
        }
        // Heuristik: zugehoeriger Add-Button liegt im selben Card-Container.
        const card = button.closest('.card, [class*="info-card"], section, article, div');
        if (card && card.contains(form)) {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                handleAdd(form);
            });
        }
    });

    form.addEventListener('submit', (event) => handleSubmit(form, event));

    updateEmptyState(form);
}

function init() {
    document.querySelectorAll(SELECTORS.form).forEach((form) => initForm(form));
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

export { init };
