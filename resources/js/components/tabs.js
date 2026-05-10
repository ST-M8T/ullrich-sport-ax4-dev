/**
 * Tabs Component
 * @module components/tabs
 */

/**
 * Controller for tab navigation
 */
export class TabsController {
    constructor(root) {
        this.root = root;
        this.queryParam = root.dataset.tabsQueryParam || null;
        this.buttons = Array.from(root.querySelectorAll('[data-tab-button]'));
        this.panels = Array.from(root.querySelectorAll('[data-tab-panel]')).map((panel) => ({
            key: panel.dataset.tabPanel || '',
            element: panel,
        })).filter((panel) => panel.key !== '');
        this.activeKey = null;

        this.handleClick = this.handleClick.bind(this);
        this.register();
    }

    register() {
        if (!this.buttons.length || !this.panels.length) {
            return;
        }

        this.buttons.forEach((button) => {
            button.addEventListener('click', this.handleClick);
        });

        const initialKey = this.root.dataset.initialTab || this.buttons[0]?.dataset.tabButton || this.panels[0]?.key || '';
        this.setActive(initialKey, { updateUrl: false });
    }

    handleClick(event) {
        const button = event.currentTarget;
        if (!(button instanceof HTMLElement)) {
            return;
        }

        const targetKey = button.dataset.tabButton || '';
        this.setActive(targetKey);
    }

    setActive(tabKey, { updateUrl = true } = {}) {
        if (!this.panels.length) {
            return;
        }

        const fallbackKey = this.panels[0].key;
        const nextKey = this.panels.some((panel) => panel.key === tabKey) ? tabKey : fallbackKey;
        if (!nextKey || nextKey === this.activeKey) {
            if (!this.activeKey) {
                this.activeKey = nextKey;
            }
            return;
        }

        this.activeKey = nextKey;

        this.buttons.forEach((button) => {
            const isActive = (button.dataset.tabButton || '') === nextKey;
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        this.panels.forEach((panel) => {
            const isActive = panel.key === nextKey;
            panel.element.hidden = !isActive;
        });

        if (updateUrl && this.queryParam && typeof URL === 'function') {
            try {
                const url = new URL(window.location.href);
                url.searchParams.set(this.queryParam, nextKey);
                window.history.replaceState({}, '', url.toString());
            } catch {
                // URL API not supported; skip history synchronization.
            }
        }
    }
}

function initialiseTabs() {
    const containers = Array.from(document.querySelectorAll('[data-tabs]'));
    containers.forEach((container) => {
        if (container instanceof HTMLElement) {
            new TabsController(container);
        }
    });
}

document.addEventListener('DOMContentLoaded', initialiseTabs);
