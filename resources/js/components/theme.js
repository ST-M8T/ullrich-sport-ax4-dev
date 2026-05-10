/**
 * Theme Component
 * @module components/theme
 */

const THEME_STORAGE_KEY = 'ax4:theme-preference';
const THEME_SEQUENCE = ['auto', 'light', 'dark'];

const systemThemeQuery = window.matchMedia?.('(prefers-color-scheme: dark)');

const getSystemTheme = () => (systemThemeQuery?.matches ? 'dark' : 'light');

const normalizePreference = (value) => {
    if (THEME_SEQUENCE.includes(value)) {
        return value;
    }

    return 'auto';
};

const readStoredTheme = () => {
    try {
        const stored = window.localStorage?.getItem(THEME_STORAGE_KEY);
        return normalizePreference(stored ?? 'auto');
    } catch (error) {
        return 'auto';
    }
};

const storeTheme = (value) => {
    try {
        window.localStorage?.setItem(THEME_STORAGE_KEY, value);
    } catch (error) {
        // ignore storage issues (private mode, etc.)
    }
};

const themeLabel = (preference, resolved) => {
    switch (preference) {
        case 'light':
            return 'Darstellung: Hell';
        case 'dark':
            return 'Darstellung: Dunkel';
        default:
            return resolved === 'dark' ? 'Darstellung: Automatisch (Dunkel)' : 'Darstellung: Automatisch (Hell)';
    }
};

const updateTheme = (preference, { persist = true } = {}) => {
    const normalized = normalizePreference(preference);
    const resolved = normalized === 'auto' ? getSystemTheme() : normalized;
    const root = document.documentElement;
    const button = document.querySelector('[data-theme-toggle]');
    const label = button?.querySelector('[data-theme-toggle-label]');

    root.setAttribute('data-theme', normalized);
    root.setAttribute('data-theme-mode', resolved);

    if (button) {
        button.setAttribute('data-theme-current', normalized);
        button.setAttribute('aria-label', `${themeLabel(normalized, resolved)} – umschalten`);
    }

    if (label) {
        label.textContent = themeLabel(normalized, resolved);
    }

    if (persist) {
        storeTheme(normalized);
    }
};

const setupThemeToggle = () => {
    updateTheme(readStoredTheme(), { persist: false });

    const button = document.querySelector('[data-theme-toggle]');

    if (!button) {
        return;
    }

    const rotateTheme = () => {
        const current = button.getAttribute('data-theme-current') ?? readStoredTheme();
        const index = THEME_SEQUENCE.indexOf(normalizePreference(current));
        const next = THEME_SEQUENCE[(index + 1) % THEME_SEQUENCE.length];
        updateTheme(next);
    };

    button.addEventListener('click', rotateTheme);

    button.addEventListener('keydown', (event) => {
        if (event.key === ' ' || event.key === 'Enter') {
            event.preventDefault();
            rotateTheme();
        }
    });

    systemThemeQuery?.addEventListener?.('change', () => {
        const preference = button.getAttribute('data-theme-current') ?? 'auto';
        if (normalizePreference(preference) === 'auto') {
            updateTheme('auto', { persist: false });
        }
    });
};

const initTheme = () => {
    setupThemeToggle();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTheme);
} else {
    initTheme();
}


