# UX Guidelines

Diese Richtlinie beschreibt die UX- und Accessibility-Vorgaben für das AX4-Admin-Interface (Stand: 2026-05-08). Sie ergänzt bestehende Projektstandards und fokussiert auf Navigation, modale Dialoge, Dark Mode, Komponenten-Bibliothek und Testprozesse.

> **Komponenten-Bibliothek**: vor jeder neuen UI-Struktur in [UI_COMPONENT_REFERENCE.md](UI_COMPONENT_REFERENCE.md) prüfen, ob es bereits eine passende anonyme Blade-Komponente gibt. Engineering-Handbuch §75 (Frontend-DRY) ist verbindlich.

## Komponenten-Disziplin (verbindlich)

- **Bootstrap-First-Prinzip**: keine eigenen Re-Implementierungen von Primitives (Button, Input, Select, Modal, Tabs, Card, Badge, Alert, Toast, Table, Tooltip). Wir nutzen Bootstrap-5-Klassen, eingewickelt in unsere `<x-ui.*>`-Komponenten.
- **Bestehende Bausteine zuerst**: jede neue Page nutzt `<x-ui.page-header>`, `<x-ui.section-header>`, `<x-ui.data-table>`, `<x-ui.action-link>`, `<x-ui.empty-state>`, `<x-ui.spinner>`, `<x-ui.info-card>`, `<x-ui.action-card>`, `<x-ui.definition-list>`.
- **Forms**: Inputs gehen über `<x-forms.input|select|textarea|checkbox>` plus `<x-forms.form>` und `<x-forms.form-actions>`. Kein direktes `<input class="form-control">`.
- **Filter-Leisten**: `<x-filters.filter-form>` und `<x-filters.filter-tabs>` — keine duplizierten Filter-Header-Strukturen.
- **Bei Bedarf** wird die neue Komponente in `UI_COMPONENT_REFERENCE.md` ergänzt (Pflicht). Sonst gilt das Cluster als Verstoß gegen DRY §75.

## Grundprinzipien

- **Konsistenz:** Layout- und Komponenten-Styles basieren auf CSS-Custom-Properties, die in `resources/css/app.css` für Light- und Dark-Mode hinterlegt sind.
- **Progressive Enhancement:** Navigation und Theme-Wechsel funktionieren ohne JavaScript (Basiszustand sichtbar), interaktive Features verbessern die Bedienung bei aktivem JS.
- **Keyboard-first:** Alle interaktiven Elemente sind mit `:focus-visible` klar erkennbar, Fokusfallen sichern modale Dialoge.
- **Dokumentierte Daten-Attribute:** UI-Logik nutzt semantische `data-*`-Attribute (`data-nav-toggle`, `data-theme-toggle`, `data-modal`, …). Bitte bei neuen Komponenten beibehalten.
- **Ubiquitous Language (DDD §4):** UI-Texte und Komponenten-Namen folgen dem fachlichen Vokabular des Bounded Context. Beispiel: „Kommissionierlisten", „Verpackungsprofile", „Vormontage" — keine englischen Übersetzungen.

## Responsives Verhalten

- **Breakpoints:** Die Hauptnavigation klappt unter 768 px ein (`body[data-nav-collapsed="true"]`). Ab Tablet-Größe bleibt sie dauerhaft sichtbar.
- **Pflicht-Viewports:** 360 / 768 / 1280 px werden bei jeder neuen Page geprüft. Kein horizontaler Overflow zugelassen.
- **Skip-Link:** „Zum Inhalt springen" (`.skip-link`) springt per `#main-content` direkt zur Hauptsektion.
- **Panels & Tabellen:** `flex`-Layouts skalieren zwischen 320–1440 px. Tabellen werden in `<div class="table-responsive">` gewickelt mit `<x-ui.data-table>` darin.
- **Nav-Overlay:** Auf kleinen Viewports blendet `body[data-nav-overlay="visible"]::before` eine semitransparente Ebene ein, damit Nutzer:innen den Fokus behalten.

## Accessibility-Richtlinien

- **ARIA & Rollen:**
  - Navigation in `resources/views/layouts/admin.blade.php` verwendet `<nav aria-label="Hauptnavigation">`.
  - Modale Dialoge (`monitoring`, `tracking`, `masterdata`) setzen `role="dialog"`, `aria-modal="true"` und verwalten `aria-hidden`.
- **Fokus-Management:**
  - Hilfsfunktionen (`resources/js/utils/a11y.js`) sammeln fokusierbare Elemente und halten den Fokus innerhalb geöffneter Modale.
  - `focusElement` stellt den vorherigen Fokus nach dem Schließen wieder her.
- **Tastatur-Navigation:**
  - Escape schließt mobile Navigation & Modale.
  - Tab/Shift+Tab zirkulieren innerhalb aktiver Dialoge.
  - Theme-Toggle akzeptiert Enter/Space und rotiert `auto → light → dark`.
- **Screenreader-Sichtbarkeit:**
  - Mobile Navigation erhält `aria-hidden`/`inert`, solange sie eingeklappt ist.
  - Statusmeldungen (`data-modal-status`) werden via Textcontent aktualisiert und bleiben mit `aria-live="polite"` lesbar (Topbar-Useranzeige).

## Dark-Mode-Verhalten

- **CSS-Custom-Properties:** `:root` definiert Basisflächen (`--surface-page`, `--surface-panel`, …); `html[data-theme-mode="dark"]` liefert dunkle Werte.
- **State-Management:** `resources/js/layout.js` speichert die Benutzerpräferenz (`localStorage: ax4:theme-preference`).
  - Reihenfolge: `auto → light → dark`.
  - Bei `auto` reagiert das UI auf System-Änderungen (`prefers-color-scheme`-Media-Query).
- **Design-Tokens nutzen:** Neue Komponenten sollten ausschließlich die vorhandenen Variablen (z. B. `var(--color-text-muted)`) anstelle fixer Farbwerte verwenden.

## Modale Dialoge

- **Monitoring & Tracking:**
  - Inhalte werden dynamisch injiziert, danach aktualisiert der Fokus-Manager die Tab-Reihenfolge.
  - Escape/Event außerhalb der Dialogbox schließt das Modal.
- **Masterdata:**
  - JS-generierte Dialoge erhalten automatisch Fokusfallen und geben Fokus an den Auslöser zurück.
- **Implementierungshinweis:** Bei neuen Modalen `getFocusableElements`/`trapFocus` aus `utils/a11y` verwenden, statt individuelle Lösungen nachzubauen.

## Layout-Vertrag (`layouts/admin.blade.php`)

Das einzige Admin-Layout (45 Verwendungen). `@extends('layouts.admin', [...])` nimmt folgende Parameter:

| Parameter | Pflicht | Zweck |
|---|---|---|
| `pageTitle` | empfohlen | Tab-Title und H1-Default. |
| `currentSection` | empfohlen | aktiviert die zugehörige Navigations-Group (z.B. `'fulfillment-orders'`). |
| `breadcrumbs` | optional | `array<['label','url']>` für Brotkrumen-Navigation. |

**Standard-Slots**: `@section('content')` für den Hauptbereich. Header und Sidebar werden via Composer befüllt.

## Testempfehlungen

- **Build prüfen:** `npm install` & `npm run build` (Vite) erzeugen Assets unter `public/build/`. Bitte vor Deployments ausführen.
- **System-Kartografie aktuell halten:** `php scripts/system-kartographie-gen.php --project-root=. --output-dir=docs` vor jedem Push laufen lassen — der CI-Job `tests` schlägt sonst fehl.
- **Responsives Testing (Browserstack):**
  1. iPhone 14 Pro (iOS 17, Safari)
  2. Pixel 8 (Android 14, Chrome)
  3. Surface Pro (Windows 11, Edge, Tablet-Viewport)
- **Accessibility-Checks:**
  - **Axe DevTools / axe-core CLI:** `npx @axe-core/cli http://localhost:8000 --tags wcag2a,wcag2aa`
  - **Lighthouse:** Chrome DevTools → *Accessibility* Audit (Desktop & Mobile).
- **Manuelle Checks:** Tab-Reihenfolge, Skip-Link, Screenreader-Kurztest (NVDA/VoiceOver) und Theme-Umschaltung mit Tastatur.

> Hinweis: Browserstack- und Axe-Scans sind noch nicht als Pipeline-Schritt automatisiert (siehe `SYSTEM_CLEANUP_BACKLOG.md` UI-1/UI-2). Bitte manuell durchführen.
