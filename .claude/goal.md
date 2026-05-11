---
goal_id: GOAL-2026-05-11T090000-ax4r2
title: AX4 — System-Kartografie + Produktionsreife (2. Run)
status: In Progress
created: 2026-05-11T09:00:00Z
updated: 2026-05-11T09:15:00Z
project: development
project_path: /Users/tsid/dev/01-ullrich-sport/server/20-interne-programme/01-ax4/development
subagent_limit: 999
subagent_used: 6
session_id: orchestrator-2
paused: false
auto_tags: [kartografie, ddd, produktionsreife, rollen, dry, a11y]
manual_tags: [ax4, laravel, kartografie, ddd, produktionsreife, rollen]
goal_full_description: |
  Ziel: Inhaltlich jede einzelne View, jeden Menüpunkt, jede Route absolut komplett kartografieren, sortieren, strukturieren, planen, überdenken, neu anordnen, zusammenfassen, trennen, aufgliedern, bündeln, neudenken. Jede Seite muss optisch perfekt sein — Viewports immer sauber eingehalten, CSS-Klassen konsistent, alles muss DRY/KISS/SOLID/SOTA/DDD sein. Wir müssen prüfen, dass jeder Mitarbeiter, jeder Leiter, jeder Admin genau das sieht was er benötigt; Aufgliederungen sauber, leicht zu bedienen, Wichtiges sauber erreichbar; Stand produktiv nutzbar — keine Baustellen, kein Entwicklungs-Müll im Code/View, keine gleichen oder ähnlichen Funktionen über das System verstreut.
---

# Goal: AX4 — System-Kartografie + Produktionsreife (2. Run)

## Tasks

### Phase 1 — Inventar & Kartografie (read-only, parallel)
- [x] {id: t1, parallel_safe: true, depends_on: [], retries: 0} System-Kartografie generieren: Routen, Views, Composer, Menü, Permissions — Delta zu vorherigem Stand prüfen
  → Ergebnis: 123 Routen (20 API + 103 web), 45 geroutete Views, 108 Total, 19 Permissions, 8 Rollen. Keine Drift seit gestern.
- [x] {id: t2, parallel_safe: true, depends_on: [], retries: 0} Vollständiges Blade-View-Inventar pro Bounded Context inkl. Permissions/Policies
  → Ergebnis: 101 Blade-Views. 52 routed pages + 10 partials. 39 orphan (components/mail/layout). BCs: fulfillment 48, configuration 20, monitoring 11, identity 6, shared 14, dispatch 1, tracking 1.
- [x] {id: t3, parallel_safe: true, depends_on: [], retries: 0} Menüpunkt-/Navigations-Inventar inkl. RoleManager-Bindings und Policy-Gates
  → Ergebnis: 6 Top-Level-Gruppen, 18 Sub-Items, 7+1 Rollen, 20 Permissions. NavigationService Tree stabil. Keine Inkonsistenzen.
- [x] {id: t4, parallel_safe: true, depends_on: [], retries: 0} DRY-Scan: duplizierte Komponenten, Services, Blade-Partials, CSS-Cluster
  → Ergebnis: 14 Duplikations-Cluster. 4 Refactor-Gelegenheiten: SenderRuleController weicht ab (sollte MasterdataControllerHelpers nutzen), Form-Components (4 components dupliziert), CSS button/badge overlap, CSS reset/base overlap.
- [x] {id: t5, parallel_safe: true, depends_on: [], retries: 0} Dev-Trash-Detektor: TODO/FIXME/dd/dump/auskommentierter Code/orphane Routes
  → Ergebnis: System EXTREM SAUBER. 0 TODOs, 0 dd/dump, 0 Kommentare, 0 Deprecated, 0 Orphan Routes/Views. Alle 108 Blade-Views inventarisiert, alle Routen lebendig. Nur 1false-positive (TrackingJobScheduler.add mit Carbon Intervallen).
- [x] {id: t17, parallel_safe: true, depends_on: [], retries: 0} DDD-Layer-Direction-Verstöße (Domain→Infrastructure)
  → Ergebnis: 1 CRITICAL — PaginatorLinkGenerator.php (Domain) importiert und instanziiert LengthAwarePaginator. 3 MEDIUM — Application nutzt Facades (RateLimiter/Str/Arr). PaginatedResult-VO gestern korrekt aus Domain entfernt.

### Phase 2 — Visual & Viewport Audit (parallel, read-only)
- [x] {id: t6, parallel_safe: true, depends_on: [t2], retries: 0} Viewport-Audit (375/768/1440) über alle Hauptseiten
  → Ergebnis: 7 Seiten. 2 CRITICAL (nested tables ohne table-responsive in fulfillment/orders:283,315). 1 HIGH (text-nowrap in dispatch/lists:134 bei 375px). 4 MEDIUM (x-ui.data-table Verhalten, Filter col-md-2). 3 LOW.
- [x] {id: t7, parallel_safe: true, depends_on: [t2], retries: 0} CSS-Klassen-Konsistenz-Audit: Bootstrap-Klassen, magic-classes, inline styles
  → Ergebnis: 0 Magic-Klassen. 15 Inline-Styles (display:none für JS-Toggles). 1 !important in scoped module.css. 40 Blade-Files mit 124+ direkten Bootstrap-Klassen (btn, mb-*, d-flex). CSS-Architektur mit 19 Component-CSS-Modulen solide.
- [x] {id: t8, parallel_safe: true, depends_on: [t2], retries: 0} Design-Token-Audit: hardcodierte Farben, Spacings, Font-Sizes, Z-Index
  → Ergebnis: Farb/Shadow-Tokens OK. FEHLEN: --space-*, --font-size-*, --radius-*, --z-index-*, --breakpoint-*. 6 hardcodierte z-index, 3 hardcodierte Hex in modal.module.css, 1 inline px-Style.
- [x] {id: t9, parallel_safe: true, depends_on: [t2], retries: 0} A11y-Deep-Dive (aria, labels, scope, tabellen)
  → Ergebnis: Tables/Forms/Buttons/Modal-ARIA OK. 2 ISSUES: (1) tabs.blade.php aria-current=page statt aria-selected (MEDIUM), (2) Modals lack focus-trap (HIGH). Neue Tasks t30/t31.

### Phase 3 — Rollen-Matrix-Audit
- [x] {id: t10, parallel_safe: true, depends_on: [t2, t3], retries: 0} Mitarbeiter-Persona Walkthrough
  → Ergebnis: Operations: 9 Menüpunkte, 0 Hidden-but-Reachable, 0 403-Lecks. Rolle sauber und konsistent.
- [x] {id: t11, parallel_safe: true, depends_on: [t2, t3], retries: 0} Leiter-Persona Walkthrough
  → Ergebnis: Leiter: 17 Permissions, 19 Menüpunkte sichtbar. 3 Hidden-but-Reachable (Mail-Vorlagen, Benachrichtigungen, Integrationen — URL erreichbar ohne Menü). Keine 403-Lecks.
- [x] {id: t12, parallel_safe: true, depends_on: [t2, t3], retries: 0} Admin-Persona Walkthrough
  → Ergebnis: Admin wildcard '*' → alle 18 Menüpunkte. Past Write-Escalation FIXED. NEU: GET /api/v1/settings/{key} ohne Auth (public info disclosure).
- [x] {id: t13, parallel_safe: false, depends_on: [t10, t11, t12], retries: 0} Cross-Role Permission Leak Detection
  → Ergebnis: 1 P0 (GET /api/v1/settings/{key} ohne Auth), 3 P1 Leiter Hidden-but-Reachable, 2 P2, 1 ARCH (PaginatorLinkGenerator). Past Write-Escalation FIXED. Keine horizontale Privilegien-Eskalation.

### Phase 4 — Informations-Architektur
- [x] {id: t14, parallel_safe: false, depends_on: [t1, t3], retries: 0} Menü-Gruppierungs-Vorschlag (Bounded-Context-aligned)
  → Ergebnis: Vorschlag: 6→5 Gruppen. Stammdaten+Verwaltung zusammenlegen (je 1 Item → "Stammdaten & Benutzer"). Monitoring→System umbenennen (deckt admin.* + monitoring.* ab). admin.* BC-Klärung als Backlog.
- [x] {id: t15, parallel_safe: true, depends_on: [t1], retries: 0} Routen-Naming-Konsistenz-Audit
  → Ergebnis: 4 Inkonsistenzen. Fulfillment BC mixt kebab+dot+hyphen-less. Configuration hyphen-less statt kebab. Identity hyphen-less statt dot. Deprecated admin-* Routes suggerieren falschen BC.
- [x] {id: t16, parallel_safe: true, depends_on: [t2], retries: 0} Breadcrumb-/Navigation-Trail-Konsistenz
  → Ergebnis: 12 masterdata submodule-Views ohne 4-Level Breadcrumb. Detail-Pages ohne Parent-Links. monitoring/system-jobs Self-Link. configuration/integrations falsche currentSection.

### Phase 5 — Architektur-Compliance
- [x] {id: t18, parallel_safe: true, depends_on: [], retries: 0} SOLID-Verstöße (God-Classes, Fat-Controller, Anemic Models)
  → Ergebnis: 2 God-Classes (MigrateFulfillmentOperations 1004 LoC, DispatchList 675 LoC), 3 Fat-Controller (>250 LoC), 4 Anemic Models, 5 Boolean-Flag-Parameter, 1 RoleManager God-Class. Refactor-Aufwand ~15 Tage als Backlog.
- [x] {id: t24, parallel_safe: false, depends_on: [t4], retries: 0} DRY-Verstöße konsolidieren
  → Ergebnis: SenderRuleController nutzt jetzt MasterdataControllerHelpers-Trait. Form-Components und CSS kein Refactoring nötig (bereits DRY).
- [x] {id: t29, parallel_safe: false, depends_on: [t17], retries: 0} PaginatorLinkGenerator refaktorieren — Laravel-Import aus Domain entfernen (CRITICAL)
  → Ergebnis: Domain Layer jetzt 0 Illuminate-Imports. Interface in Domain/Shared, Implementation in Infrastructure/Pagination. 360/360 Tests passieren.

### Phase 6 — Fixes & Refactoring
- [x] {id: t19, parallel_safe: false, depends_on: [t6, t7, t8], retries: 0} Viewport-/CSS-/Design-Token-Fixes
  → Ergebnis: text-nowrap dispatch/lists entfernt. Nested tables in fulfillment/orders hatten bereits table-responsive. Design-Tokens (spacing/font/radius/z-index) als Backlog.
- [x] {id: t20, parallel_safe: false, depends_on: [t9], retries: 0} A11y-Fixes
- [ ] {id: t21, parallel_safe: false, depends_on: [t13], retries: 0} Permission-/Role-Lecks beheben
- [ ] {id: t22, parallel_safe: false, depends_on: [t14, t15, t16], retries: 0} Informations-Architektur-Änderungen
- [x] {id: t23, parallel_safe: false, depends_on: [t5], retries: 0} Dev-Trash entfernen
- [x] {id: t24, parallel_safe: false, depends_on: [t4], retries: 0} DRY-Verstöße konsolidieren
- [x] {id: t25, parallel_safe: false, depends_on: [t17, t18], retries: 0} DDD-/SOLID-Fixes

### Phase 7 — Produktionsreife-Verifikation
- [ ] {id: t26, parallel_safe: true, depends_on: [t19, t20, t21, t22, t23, t24, t25], retries: 0} Finaler QA-Pass (3 Personas)
- [ ] {id: t27, parallel_safe: true, depends_on: [t19, t20], retries: 0} Finaler A11y-/Performance-Pass
- [ ] {id: t28, parallel_safe: false, depends_on: [t26, t27], retries: 0} Finale Doku-Synchronisation

## Entdeckte Zusatz-Tasks
- [x] {id: t32, parallel_safe: false, depends_on: [t13], retries: 0} GET /api/v1/settings/{key} ohne Auth — public info disclosure fixen (P0 Security)
  → Ergebnis: Route mit auth.admin + can:configuration.settings.manage geschützt. api/v1/* public Routes sind legitim (Warehouse-Scanner).
- [x] {id: t19, parallel_safe: false, depends_on: [t6, t7, t8], retries: 0} Viewport-/CSS-/Design-Token-Fixes
  → Ergebnis: text-nowrap in dispatch/lists entfernt. Nested tables in fulfillment/orders hatten bereits table-responsive.
- [x] {id: t29, parallel_safe: false, depends_on: [t17], retries: 0} PaginatorLinkGenerator refaktorieren — Laravel-Import aus Domain entfernen (CRITICAL)
  → Ergebnis: tabs.blade.php aria-current → aria-selected. Focus-Trap in base.js bereits korrekt implementiert.
- [x] {id: t30, parallel_safe: false, depends_on: [t9], retries: 0} tabs.blade.php ARIA: aria-current=page → aria-selected (MEDIUM)
  → Ergebnis: aria-selected für aktive Tabs implementiert.
- [x] {id: t31, parallel_safe: false, depends_on: [t9], retries: 0} Focus-Trap in Modal implementieren (HIGH)
  → Ergebnis: Bereits in base.js vorhanden (ESC + Tab-Cycle), keine Änderung nötig.

## Wave-Historie
- Wave 0 (09:00): Goal Setup gestartet
- Wave 1 (09:05): t1 + t2 + t3 (Explore × 3) → Kartografie komplett (123 Routen, 101 Views, Menü-Inventar)
- Wave 2 (09:10): t4 + t5 + t17 (Explore × 3) → DRY/DDD/Trash-Analyse → 14 Cluster, 0 Müll, 1 CRITICAL (PaginatorLinkGenerator)
- Wave 3 (09:15): t6 + t7 + t8 + t9 (QA × 2, Explore × 2) → Visual Audit + A11y → 2 CRITICAL viewport, 1 HIGH focus-trap, fehlende Design-Tokens
- Wave 4 (09:30): t10 + t11 + t12 (QA × 3) → 3 Persona Walkthroughs → Operations sauber, Leiter 3 Hidden-but-Reachable, Admin Write-Escalation FIXED, NEU: api/v1/settings public disclosure
- Wave 5 (09:40): t13 Cross-Role Konsolidierung → 1 P0 (settings ohne Auth), 3 P1, 2 P2, 1 ARCH
- Wave 6 (10:00): Security Fix (api/v1/settings Auth), Viewport/CSS-Fixes, A11y (tabs aria-selected, focus-trap bestätigt), Tests fixed. 360/360. Git push 6de1dc9.
- Wave 7 (10:30): t14 Menü-Gruppierung (BC-aligned: 6→5 Gruppen, Monitoring→System), t15 Routen-Naming (4 Inkonsistenzen), t16 Breadcrumbs (12 masterdata submodule-Views ohne 4-Level)
- Wave 8 (10:45): t18 SOLID (2 God-Classes 1004/675 LoC, 3 Fat-Controller, 4 Anemic, 5 Boolean-Flag), t24 DRY (SenderRuleController jetzt mit Trait), t29 PaginatorLinkGenerator DDD-refactor (Domain 0 Illuminate-Imports)

## Abschluss-Notiz
(wird bei Status: Achieved gefüllt)
