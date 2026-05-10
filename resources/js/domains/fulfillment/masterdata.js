/**
 * Fulfillment Masterdata
 * @module domains/fulfillment/masterdata
 */

import { BaseModal } from '../../components/modal/base';

class MasterdataModal extends BaseModal {
    constructor() {
        const container = document.createElement('div');
        container.className = 'app-modal';
        container.innerHTML = `
            <div class="app-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="masterdata-modal-title">
                <div class="app-modal__header">
                    <div>
                        <h3 class="app-modal__title h4" id="masterdata-modal-title" data-modal-title>Details</h3>
                        <p class="app-modal__subtitle text-muted small" data-modal-subtitle></p>
                    </div>
                    <button type="button" class="btn-close app-modal__close" aria-label="Schließen" data-modal-close></button>
                </div>
                <div class="app-modal__body" data-modal-body></div>
            </div>
            <div class="app-modal__backdrop" data-modal-close></div>
        `;

        container.setAttribute('aria-hidden', 'true');
        document.body.appendChild(container);

        super(container);

        this.titleElement = container.querySelector('[data-modal-title]');
        this.subtitleElement = container.querySelector('[data-modal-subtitle]');
        this.bodyElement = container.querySelector('[data-modal-body]');
    }

    open({ title, subtitle, content }) {
        this.titleElement.textContent = title || 'Details';

        if (subtitle) {
            this.subtitleElement.textContent = subtitle;
            this.subtitleElement.classList.remove('d-none');
        } else {
            this.subtitleElement.textContent = '';
            this.subtitleElement.classList.add('d-none');
        }

        this.bodyElement.innerHTML = content || '';

        super.open();
    }
}

class MasterdataSection {
    constructor(sectionElement, modal) {
        this.section = sectionElement;
        this.modal = modal;
        this.table = this.section.querySelector('[data-masterdata-table]');
        this.tbody = this.table ? this.table.tBodies[0] : null;
        this.filterInput = this.section.querySelector('[data-masterdata-filter]');
        this.exportButton = this.section.querySelector('[data-masterdata-export]');
        this.countElement = this.section.querySelector('[data-masterdata-count]');
        this.sortButtons = Array.from(this.section.querySelectorAll('[data-masterdata-sort]'));
        this.detailButtons = Array.from(this.section.querySelectorAll('[data-masterdata-detail-trigger]'));
        this.totalCount = this.countElement ? Number(this.countElement.dataset.total || 0) : 0;
        this.activeSort = null;
        this.rows = [];

        this.initialise();
    }

    static escapeCsvValue(value) {
        const stringValue = value ?? '';
        if (/[",\n]/.test(stringValue)) {
            return '"' + stringValue.replace(/"/g, '""') + '"';
        }
        return stringValue;
    }

    initialise() {
        if (!this.table || !this.tbody) {
            return;
        }

        this.rows = Array.from(this.tbody.querySelectorAll('tr[data-masterdata-row]'));
        this.rows.forEach((row) => {
            row.dataset.filterValue = row.textContent.toLowerCase();
        });

        this.sortButtons.forEach((button) => {
            button.dataset.sortState = 'none';
            const headerCell = button.closest('th');
            if (headerCell) {
                headerCell.setAttribute('aria-sort', 'none');
            }
            button.addEventListener('click', () => this.sortBy(button));
        });

        if (this.filterInput) {
            this.filterInput.addEventListener('input', () => this.applyFilter());
        }

        if (this.exportButton) {
            this.exportButton.addEventListener('click', () => this.exportCsv());
        }

        this.detailButtons.forEach((button) => {
            button.addEventListener('click', () => this.openDetails(button));
        });

        this.applyFilter();
        this.updateSortIndicators();
    }

    sortBy(button) {
        const columnIndex = Number(button.dataset.column);
        if (Number.isNaN(columnIndex)) {
            return;
        }

        const sortType = button.dataset.sortType || 'string';
        let direction = 'asc';

        if (this.activeSort && this.activeSort.button === button) {
            direction = this.activeSort.direction === 'asc' ? 'desc' : 'asc';
        }

        this.activeSort = { button, columnIndex, sortType, direction };
        this.applySort();
    }

    applySort() {
        if (!this.tbody || !this.rows.length || !this.activeSort) {
            this.updateSortIndicators();
            return;
        }

        const { columnIndex, sortType, direction } = this.activeSort;

        const collator = new Intl.Collator(undefined, { sensitivity: 'base', numeric: false });

        const sortedRows = [...this.rows].sort((rowA, rowB) => {
            const valueA = this.getSortValue(rowA, columnIndex, sortType);
            const valueB = this.getSortValue(rowB, columnIndex, sortType);

            if (valueA === valueB) {
                return 0;
            }

            if (valueA === null) {
                return direction === 'asc' ? 1 : -1;
            }

            if (valueB === null) {
                return direction === 'asc' ? -1 : 1;
            }

            if (sortType === 'number') {
                return direction === 'asc' ? valueA - valueB : valueB - valueA;
            }

            const comparison = collator.compare(String(valueA), String(valueB));
            return direction === 'asc' ? comparison : -comparison;
        });

        sortedRows.forEach((row) => {
            this.tbody.appendChild(row);
        });

        this.rows = sortedRows;
        this.updateSortIndicators();
    }

    getSortValue(row, columnIndex, sortType) {
        const cell = row.cells[columnIndex];
        if (!cell) {
            return null;
        }

        const rawValue = cell.dataset.sortValue !== undefined ? cell.dataset.sortValue : cell.textContent.trim();

        if (sortType === 'number') {
            const numeric = parseFloat(rawValue.replace(',', '.'));
            return Number.isNaN(numeric) ? null : numeric;
        }

        return rawValue.toLowerCase();
    }

    updateSortIndicators() {
        this.sortButtons.forEach((button) => {
            const state = this.activeSort && this.activeSort.button === button ? this.activeSort.direction : 'none';
            button.dataset.sortState = state;

            const headerCell = button.closest('th');
            if (headerCell) {
                const ariaValue = state === 'none' ? 'none' : (state === 'asc' ? 'ascending' : 'descending');
                headerCell.setAttribute('aria-sort', ariaValue);
            }
        });
    }

    applyFilter() {
        if (!this.filterInput) {
            this.updateCount();
            return;
        }

        const term = this.filterInput.value.trim().toLowerCase();
        let visible = 0;

        this.rows.forEach((row) => {
            const haystack = row.dataset.filterValue || row.textContent.toLowerCase();
            const matches = !term || haystack.includes(term);
            row.classList.toggle('d-none', !matches);
            if (matches) {
                visible += 1;
            }
        });

        this.visibleCount = visible;
        this.updateCount();
    }

    updateCount() {
        if (!this.countElement) {
            return;
        }

        const total = Number.isNaN(this.totalCount) || this.totalCount === 0 ? this.rows.length : this.totalCount;
        const visible = typeof this.visibleCount === 'number' ? this.visibleCount : this.rows.filter((row) => !row.classList.contains('d-none')).length;

        let label;
        if (total === 0) {
            label = 'Keine Datensätze';
        } else if (total === 1) {
            label = '1 Datensatz';
        } else {
            label = `${total} Datensätze`;
        }

        if (visible !== total) {
            label += ` · ${visible} sichtbar`;
        }

        this.countElement.textContent = label;
    }

    openDetails(button) {
        const templateId = button.dataset.detailTemplate;
        if (!templateId) {
            return;
        }

        const escapedId = typeof CSS !== 'undefined' && typeof CSS.escape === 'function'
            ? CSS.escape(templateId)
            : templateId.replace(/[\s.:#\[\],=]/g, '\\$&');

        const template = this.section.querySelector(`#${escapedId}`);
        if (!template) {
            return;
        }

        this.modal.open({
            title: button.dataset.detailTitle || 'Details',
            subtitle: button.dataset.detailSubtitle || '',
            content: template.innerHTML,
        });
    }

    exportCsv() {
        if (!this.table) {
            return;
        }

        const headerCells = Array.from(this.table.querySelectorAll('thead th'));
        if (!headerCells.length) {
            return;
        }

        const includedIndexes = headerCells.reduce((indices, header, index) => {
            if (!header.hasAttribute('data-export-ignore')) {
                indices.push(index);
            }
            return indices;
        }, []);

        if (!includedIndexes.length) {
            return;
        }

        const headerRow = includedIndexes.map((index) => headerCells[index].textContent.trim());
        const visibleRows = this.rows.filter((row) => !row.classList.contains('d-none'));

        const bodyRows = visibleRows.map((row) => {
            const cells = Array.from(row.cells);
            return includedIndexes.map((index) => {
                const cell = cells[index];
                if (!cell) {
                    return '';
                }
                const value = cell.dataset.exportValue ?? cell.dataset.sortValue ?? cell.textContent.trim();
                return value.replace(/\s+/g, ' ').trim();
            });
        });

        const csvMatrix = [headerRow, ...bodyRows];
        const csvContent = csvMatrix.map((row) => row.map(MasterdataSection.escapeCsvValue).join(',')).join('\r\n');

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const timestamp = new Date().toISOString().replace(/[-:]/g, '').split('.')[0];
        const baseName = this.section.dataset.exportName || 'masterdata';
        const fileName = `${baseName}-${timestamp}.csv`;

        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }
}

function initialiseMasterdataPage() {
    const root = document.querySelector('[data-fulfillment-masterdata]');
    if (!root) {
        return;
    }

    const sections = Array.from(root.querySelectorAll('[data-masterdata-section]'));
    if (!sections.length) {
        return;
    }

    const modal = new MasterdataModal();
    sections.forEach((section) => new MasterdataSection(section, modal));
}

document.addEventListener('DOMContentLoaded', initialiseMasterdataPage);


