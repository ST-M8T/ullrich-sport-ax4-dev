# UI_CSS_AUDIT
**Stand:** 2026-05-12 20:00 UTC · CSS/Tailwind/Inline-Style-Konsistenz

## 1) Token-Konfiguration

**Color-Tokens (resources/css/variables.css):**
- Surfaces: `--surface-page/-panel/-topbar/-sidebar/-overlay`
- Text: `--color-text`, `--color-text-muted`
- Brand: `--brand-primary` (#50AF47), `--brand-primary-hover`, `--brand-danger`, `--brand-dark`
- Alerts: `--color-alert-{success|error|warning|info}-{bg|border|text}`
- Borders: `--color-border`, `--color-border-strong`
- Focus-Ring: `--color-focus-ring`, `--color-focus-ring-muted`

**Spacing:** Custom `.px-1` (1rem), sonst Tailwind-Standard.

**Breakpoints (layout.css):** Mobile <576 / Tablet 576-991 / Desktop ≥992 — **legacy Bootstrap-Breakpoints, nicht Tailwind-Standard**.

## 2) Findings

### HIGH — Magic Values + Inline-Styles (16 Vorkommen)

| Datei | Zeile | Typ | Befund | Fix |
|---|---|---|---|---|
| `mail/dhl-catalog-sync-failed.blade.php` | 4 | inline-style | `color: #1f2937;` | → `var(--color-text)` |
| `mail/dhl-catalog-sync-failed.blade.php` | 5 | inline-style | `color: #b91c1c;` | → `var(--brand-danger)` |
| `mail/dhl-catalog-sync-failed.blade.php` | 13,15-28 | inline-style | `border: 1px solid #e5e7eb;` (11×) | → CSS-Klasse `.mail-table` |
| `mail/dhl-catalog-sync-failed.blade.php` | 37 | inline-style | `color:#6b7280; font-size:12px;` | → `var(--color-text-muted)` + `.mail-footer` |
| `mail/domain-event-alert.blade.php` | 7-11 | `<style>`-Block | inline-styles im Mail-Template | → mail-Template-CSS-Datei |
| `mail/domain-event-alert.blade.php` | 13,17,21,22 | inline-style | `font-family: Arial; color: #1f2933;` (4×) | → CSS-Klasse |
| `configuration/settings/partials/settings.blade.php` | 167,228,264 | inline-style | `style="display: none;"` (3×) | → `.d-none` |
| `configuration/settings/partials/verwaltung/sections/notifications.blade.php` | 12 | inline-style | `style="display: none;"` | → `.d-none` |
| `configuration/settings/partials/verwaltung/sections/identity-users.blade.php` | 12,78 | inline-style | `style="display: none;"` (2×) | → `.d-none` |
| `fulfillment/orders/dhl/label-preview.blade.php` | – | inline-style | `max-height: 400px;` (2×) | → Tailwind `max-h-[400px]` |
| `configuration/settings/partials/verwaltung/notifications.blade.php` | – | inline-style | `max-width: 200px;` | → `max-w-xs` |

**Summary HIGH:** 16 Vorkommen Magic-Hex, 11 inline `display:none`, 2 Email-Templates mit `<style>`-Blöcken.

### MEDIUM — Duplizierte Klassen-Cluster (14 distinct Patterns)

| Pattern | Vorkommen | Top-Files | Konsolidierung |
|---|---|---|---|
| `d-flex gap-2 align-items-center` | 6 | orders/index, settings/*, identity/users | → `.control-group` Blade-Component |
| `d-flex justify-content-between align-items-center mb-3` | 5 | orders/*, settings/* | → `.header-actions` |
| `d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2` | 4 | admin/settings/*, orders/* | → `.page-controls` (existiert als `page-header`?) |
| `d-flex justify-content-end gap-2` | 3 | settings/*, orders/* | → `.button-group-right` |
| `alert alert-{X} d-flex justify-content-between align-items-center` | 3 | settings/*, orders/* | → `.alert-with-action` |

### LOW — Spacing/Breakpoint-Inkonsistenzen

- Nur **1 `lg:`-Vorkommen** in allen Views (sidebar-tabs) — Tailwind-Breakpoints werden kaum genutzt; meiste Responsive via legacy-`@media` in CSS-Modulen
- Gap-Tokens gemischt: `gap-1`, `gap-2`, `gap-3`, `gap-4` ohne klare Konvention
- Padding-Tokens: 12 Views `px-4`, 7 `px-6`, 3 `px-8`
- Mail-Padding gemischt: 12px / 1rem / 1.5rem

## 3) Top-Konsolidierungs-Kandidaten

| # | Cluster | Vorkommen | Aufwand | Impact |
|---|---|---|---|---|
| 1 | **Mail-Template Inline-Styles extrahieren** | 25+ in 2 Mail-Templates | 2-3h | Email-Konsistenz |
| 2 | **`display: none` → `.d-none`** | 6 Views (settings/verwaltung) | 1-2h | JS-Toggle-Kompatibilität |
| 3 | **Flex-Cluster-Components** (`button-group`, `control-bar`, `header-controls`) | 14 Patterns | 2-3h | DRY |
| 4 | **Alert-Variant-Erweiterung** | 25+ alerts | 1-2h | Konsistenz + A11y |
| 5 | **CSS-Module-Spacing-Normalisierung** | 304 px/rem-Werte | 3-4h | Token-Einheitlichkeit |
| 6 | **Tailwind-Responsive durchsetzen** (md:/lg:/xl: statt @media) | ganzes System | LARGE | langfristig — separates Goal |

## 4) Engineering-Handbuch Compliance

| § | Status | Befund |
|---|---|---|
| §47 (keine zufälligen globalen Regeln) | ✗ | `alert/btn/.admin-*` teils unbegrenzt, kein BEM-Namespace |
| §48 (keine unklaren Klassen) | ✓ | Keine `temp/test/fix/new`-Klassen |
| §49 (Design Tokens) | ⚠️ | Tokens definiert, aber inline-Styles + JS-Klassen umgehen sie |
| §50 (Responsive konsistent) | ✗ | Tailwind-Breakpoints kaum genutzt, legacy @media |
| §75.2 (keine doppelte CSS-Logik) | ✗ | 304 Magic-Spacing-Werte + Inline-Styles |

**Score:** 2/5 bestanden.

## 5) Zusammenfassung

- **Findings:** 16 HIGH · 6 MEDIUM · 5 LOW
- **Quick-Wins (1-3h):** `display:none`→`.d-none`, Mail-CSS-Extraktion, Flex-Components
- **Geschätzter Refactor-Aufwand Gesamt:** 10-15h für Top-5 Cluster

---
_Generiert von t5 (System-Kartographie Wave 2, GOAL-2026-05-12T194500-syscart)._
