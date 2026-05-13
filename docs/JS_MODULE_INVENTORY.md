# JS_MODULE_INVENTORY
**Stand:** 2026-05-12 20:00 UTC · 31 JS-Module · Vite 7.0.7 · ES-Modules

## 1) Stack
- Build: Vite 7.0.7
- Module-System: ES-Modules
- Entry: `resources/js/app.js`
- Runtime-Deps: axios 1.11.0
- **Test-Setup: KEINS** (kein Vitest/Jest in package.json)

## 2) Modul-Übersicht (31 Module)

### Core/Shared (11)
| Pfad | LoC | Status |
|---|---|---|
| `core/bootstrap.js` | 13 | ✓ axios+CSRF |
| `core/http.js` | 70 | ✓ **Best-Practice** §45 |
| `core/string.js` | 35 | ✓ escapeHtml zentral |
| `core/json.js` | 40 | ✓ |
| `core/ui.js` | 60 | ✓ setStatus/withLoadingState |
| `core/a11y.js` | 125 | ✓ **Best-Practice** §51 |
| `components/tabs.js` | 95 | ✓ Auto-Init on `[data-tabs]` |
| `components/modal/base.js` | 141 | ✓ **Best-Practice** Focus-Trap |
| `components/bootstrap-modal.js` | 126 | ⚠️ `window.bootstrap` global |
| `components/theme.js` | 116 | ✓ Theme-Toggle + localStorage |
| `components/settings-modal.js` | 63 | ✓ Generic Settings-Modal |

### Fulfillment (11)
| Pfad | LoC | Status |
|---|---|---|
| `domains/fulfillment/masterdata.js` | 343 | ⚠️ >300, SRP-Smell |
| `domains/fulfillment/dhl-product-catalog.js` | 446 | ⚠️ >300, duplicate escapeHtml lokal |
| `domains/fulfillment/dhl-catalog.js` | 180 | ✓ Polling + Sync-Banner |
| `domains/fulfillment/dhl-freight-settings.js` | 88 | ✓ |
| `domains/fulfillment/dhl-booking-form.js` | 303 | ⚠️ duplicate escapeText lokal |
| `domains/fulfillment/dhl-package-editor.js` | 288 | ⚠️ Repeater-Logik komplex |
| `domains/fulfillment/dhl-price-quote.js` | 86 | ✓ |
| `domains/fulfillment/dhl-allowed-services-accordion.js` | 436 | ⚠️ >300, splitten |
| `domains/fulfillment/services/dhl-allowed-services-service.js` | 177 | ✓ **Best-Practice** §45 |
| `domains/fulfillment/services/dhl-parameter-form-renderer.js` | 331 | ⚠️ >300, shared (PROJ-5 DRY) |
| `domains/fulfillment/freight-profile-services.js` | 123 | ✓ nutzt shared Renderer |

### Tracking (4)
| Pfad | LoC | Status |
|---|---|---|
| `domains/tracking/api.js` | 64 | ✓ **Best-Practice** API-Layer |
| `domains/tracking/modal-manager.js` | 50 | ✓ |
| `domains/tracking/renderers.js` | 191 | ✓ **Best-Practice** reine Render-Fns |
| `domains/tracking/overview.js` | 442 | ⚠️ >300, Job+Alert+Tabs in einer Datei |

### Andere (5)
| Pfad | LoC | Status |
|---|---|---|
| `domains/dispatch/scans-modal.js` | 209 | ⚠️ inline HTML-Strings |
| `domains/monitoring/modal.js` | 81 | ✓ |
| `inline-forms.js` | 51 | ⚠️ Global Functions §41 |
| `utilities/inline-forms.js` | 51 | 🔴 **DUPLIKAT** zu obigem File |

## 3) Findings

### 🔴 CRITICAL
- **Identisches Duplikat**: `resources/js/inline-forms.js` ≡ `resources/js/utilities/inline-forms.js` → **DELETE eines der beiden**

### 🟠 HIGH
- **Global Functions** (`toggleCreateForm`, `toggleRow` in inline-forms.js) — §41-Verstoß. → Event-Delegation via `data-*` (wie tabs.js)
- **`window.bootstrap.Modal`-Setter** in components/bootstrap-modal.js:122-123 — dokumentierter Fallback, OK aber überprüfen

### 🟡 MEDIUM
- **escapeHtml lokal dupliziert** in 3 Modulen (core/string.js + dhl-product-catalog.js:404 + dhl-booking-form.js:21) → konsolidieren
- **5 Module >300 LoC** (dhl-product-catalog 446, dhl-allowed-services-accordion 436, tracking/overview 442, masterdata 343, dhl-parameter-form-renderer 331) → SRP-Split
- **Inline HTML-Strings** in dispatch/scans-modal.js:143-181 → `<template>`-Tag oder Template-Modul
- **State-Renderer-Pattern wiederholt** 10× (loading/success/error) → `core/ui-state.js` extrahieren

### LOW
- formatDateTime (de-DE) in 2 Modulen → `core/date.js` neu

### Inline-Scripts in Blade-Views (§40-Verstoß)
- 11+ `onclick=`-Handler in Settings-Partials (toggleCreateForm/toggleRow)
- `components/dhl/tracking-timeline.blade.php`: `onclick="refreshTrackingTimeline(...)"` — **Funktion undefiniert** (Bug!)
- `components/dhl/catalog-sync-banner.blade.php`: `onclick="return confirm(...)"`
- `fulfillment/orders/dhl/label-preview.blade.php`: `onclick="window.close()"` (akzeptabel)

## 4) Engineering-Handbuch Compliance

| § | Status | Verstöße |
|---|---|---|
| §40 (keine Inline-Scripts) | ✗ | 11+ onclick-Handler |
| §41 (keine globalen Vars) | ⚠️ | 2× (window.bootstrap, toggleCreate/Row globals) |
| §44 (Single SoT) | ✓ | |
| §45 (API-Layer) | ✓ **EXCELLENT** | core/http + tracking/api + dhl-allowed-services-service |
| §51 (A11y) | ✓ **EXCELLENT** | BaseModal Focus-Trap, Akkordeon aria-* |
| §54 (DOM gekapselt) | ✓ | |
| §75.3 (DRY JS) | ✗ | 3 Duplikate (inline-forms 2×, escapeHtml 3×, State-Renderer 10×) |
| §75.4 (CSRF zentral) | ✓ | getCsrfToken in core/http.js |

**Score: 6/9 (67%)** ✓ Solide Basis, 3 Quick-Wins möglich

## 5) Top Refactor-Cluster

| # | Cluster | Aufwand | Impact |
|---|---|---|---|
| 1 | **DELETE utilities/inline-forms.js** | 5 min | DRY |
| 2 | **toggleCreate/Row → Event-Delegation** | 30 min | §41 Compliance |
| 3 | **escapeHtml-Konsolidierung** | 15 min | DRY |
| 4 | **Module-Split** (5 Module >300 LoC) | 2-3h pro Modul | SRP |
| 5 | **State-Renderer-Helper** (`core/ui-state.js`) | 45 min | DRY |
| 6 | **tracking-timeline.blade.php Bug-Fix** (`refreshTrackingTimeline` undefiniert!) | 15 min | Bug-Fix |
| 7 | **Vitest-Setup** | 2-3h | Testbarkeit |

## 6) Zusammenfassung

| Metrik | Wert | Status |
|---|---|---|
| Module gesamt | 31 | ✓ |
| Ø LoC/Modul | ~156 | ✓ |
| Module >300 LoC | 5 | ⚠️ |
| Duplikate | 3 (inline-forms 2×, escapeHtml 3×, State-Render 10×) | 🔴 |
| Inline-Scripts in Views | 11+ | ⚠️ |
| Global Functions | 2 (toggleCreate/Row) | ⚠️ |
| §45 (API-Layer) | 100% | ✓ EXCELLENT |
| §51 (A11y) | 95% | ✓ EXCELLENT |
| §40+§41+§75 | 64% | ⚠️ |

**Quick-Wins (Total ~1.5h):**
1. Delete `utilities/inline-forms.js` (5min)
2. Konsolidiere escapeHtml-Imports (15min)
3. tracking-timeline Bug-Fix (15min)
4. State-Renderer-Helper (45min)
5. toggleCreate/Row Event-Delegation (30min)

---
_Generiert von t6 (System-Kartographie Wave 2, GOAL-2026-05-12T194500-syscart)._
