/**
 * Inline Forms Toggle Functions
 * DRY: Wiederverwendbare Funktionen für alle inline-Formulare
 */

function toggleCreateForm(id) {
    const form = document.getElementById(id);
    const toggle = document.getElementById(id + 'Toggle');
    if (!form) return;
    
    if (form.style.display === 'none' || !form.style.display) {
        form.style.display = 'block';
        if (toggle) toggle.textContent = 'Abbrechen';
    } else {
        form.style.display = 'none';
        if (toggle) {
            const originalText = toggle.getAttribute('data-original-text') || 'Anlegen';
            toggle.textContent = originalText;
        }
    }
}

function toggleRow(id) {
    const row = document.getElementById(id);
    if (!row) return;
    
    if (row.style.display === 'none' || !row.style.display) {
        row.style.display = 'table-row';
    } else {
        row.style.display = 'none';
    }
}

// Auto-initialize toggle buttons
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[onclick*="toggleCreateForm"], [onclick*="toggleRow"]').forEach(btn => {
        const onclick = btn.getAttribute('onclick');
        if (onclick && onclick.includes('toggleCreateForm')) {
            const match = onclick.match(/toggleCreateForm\(['"]([^'"]+)['"]\)/);
            if (match) {
                const toggle = document.getElementById(match[1] + 'Toggle');
                if (toggle && !toggle.getAttribute('data-original-text')) {
                    toggle.setAttribute('data-original-text', toggle.textContent.trim());
                }
            }
        }
    });
});


