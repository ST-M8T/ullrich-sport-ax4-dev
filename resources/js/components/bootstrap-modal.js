/**
 * Lightweight Bootstrap-compatible modal behavior.
 *
 * Supports the subset used by Blade views: data-bs-toggle="modal",
 * data-bs-target, data-bs-dismiss="modal", and bootstrap.Modal.getInstance().
 */
class BootstrapModal {
    static instances = new WeakMap();

    constructor(element) {
        if (!(element instanceof HTMLElement)) {
            throw new Error('BootstrapModal requires an HTMLElement');
        }

        this.element = element;
        this.dialog = element.querySelector('.modal-dialog') ?? element;
        this.lastFocusedElement = null;
        this.handleKeydown = this.handleKeydown.bind(this);
        this.handleBackdropClick = this.handleBackdropClick.bind(this);

        BootstrapModal.instances.set(element, this);
    }

    static getInstance(element) {
        return element instanceof HTMLElement ? (BootstrapModal.instances.get(element) ?? null) : null;
    }

    static getOrCreateInstance(element) {
        return BootstrapModal.getInstance(element) ?? new BootstrapModal(element);
    }

    show() {
        this.lastFocusedElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        this.element.classList.add('show', 'is-open');
        this.element.removeAttribute('aria-hidden');
        this.element.setAttribute('aria-modal', 'true');
        this.element.setAttribute('role', 'dialog');
        document.body.classList.add('modal-open');
        document.addEventListener('keydown', this.handleKeydown, true);
        this.element.addEventListener('click', this.handleBackdropClick);

        const focusTarget = this.element.querySelector('[data-bs-dismiss], .btn-close, button, [href], input, select, textarea')
            ?? this.dialog;
        if (focusTarget instanceof HTMLElement) {
            focusTarget.focus({ preventScroll: true });
        }
    }

    hide() {
        this.element.classList.remove('show', 'is-open');
        this.element.setAttribute('aria-hidden', 'true');
        this.element.removeAttribute('aria-modal');
        document.removeEventListener('keydown', this.handleKeydown, true);
        this.element.removeEventListener('click', this.handleBackdropClick);

        if (!document.querySelector('.modal.show, .modal.is-open')) {
            document.body.classList.remove('modal-open');
        }

        if (this.lastFocusedElement instanceof HTMLElement) {
            this.lastFocusedElement.focus({ preventScroll: true });
            this.lastFocusedElement = null;
        }
    }

    handleKeydown(event) {
        if (event.key !== 'Escape') {
            return;
        }

        event.preventDefault();
        this.hide();
    }

    handleBackdropClick(event) {
        if (event.target === this.element) {
            this.hide();
        }
    }
}

function resolveTarget(trigger) {
    const selector = trigger.getAttribute('data-bs-target') ?? trigger.getAttribute('href');
    if (!selector || selector === '#') {
        return null;
    }

    try {
        return document.querySelector(selector);
    } catch {
        return null;
    }
}

document.addEventListener('click', (event) => {
    const trigger = event.target instanceof Element
        ? event.target.closest('[data-bs-toggle="modal"]')
        : null;

    if (trigger instanceof HTMLElement) {
        const modal = resolveTarget(trigger);
        if (modal instanceof HTMLElement) {
            event.preventDefault();
            BootstrapModal.getOrCreateInstance(modal).show();
        }
        return;
    }

    const dismiss = event.target instanceof Element
        ? event.target.closest('[data-bs-dismiss="modal"]')
        : null;

    if (dismiss instanceof HTMLElement) {
        const modal = dismiss.closest('.modal');
        if (modal instanceof HTMLElement) {
            event.preventDefault();
            BootstrapModal.getOrCreateInstance(modal).hide();
        }
    }
});

window.bootstrap = window.bootstrap ?? {};
window.bootstrap.Modal = BootstrapModal;

export { BootstrapModal };
