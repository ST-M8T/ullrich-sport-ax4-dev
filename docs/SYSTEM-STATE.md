# AX4 System State
Stand: 2026-05-11

## 1. Route- & View-Inventar

| Metrik | Wert |
| --- | --- |
| Routen gesamt | 123 |
| API-Routen (v1) | 20 |
| Web-Routen | 103 |
| Blade-Views | 108 |
| Permissions | 19 |

### 1.1 Route-Verteilung nach Oberfläche

| Oberfläche | Anzahl |
| --- | --- |
| api | 20 |
| web | 103 |

### 1.2 API-Routen (v1)

| Methode | URI | Permission |
| --- | --- | --- |
| GET | /v1/dispatch-lists | - |
| GET | /v1/dispatch-lists/{list}/scans | - |
| POST | /v1/dispatch-lists/{list}/scans | - |
| GET | /v1/health/live | - |
| GET | /v1/health/ready | - |
| GET | /v1/settings/{key} | auth.admin + can:configuration.settings.manage (t32) |
| GET | /v1/shipments/{trackingNumber} | - |
| GET | /v1/tracking-alerts | - |
| GET | /v1/tracking-jobs | - |
| GET | /admin/log-files | admin.logs.view |
| DELETE | /admin/log-files/{file} | admin.logs.view |
| GET | /admin/log-files/{file} | admin.logs.view |
| POST | /admin/log-files/{file}/actions/download | admin.logs.view |
| GET | /admin/log-files/{file}/entries | admin.logs.view |
| GET | /admin/system-settings | configuration.settings.manage |
| POST | /admin/system-settings | configuration.settings.manage |
| DELETE | /admin/system-settings/{settingKey} | configuration.settings.manage |
| GET | /admin/system-settings/{settingKey} | configuration.settings.manage |
| PATCH | /admin/system-settings/{settingKey} | configuration.settings.manage |
| GET | /admin/system-status | admin.setup.view |

### 1.3 Web-Routen nach Bereich

| Bereich | Routen |
| --- | --- |
| configuration | 22 |
| fulfillment | 49 |
| identity | 8 |
| tracking | 6 |
| monitoring | 3 |
| logs | 2 |
| csv-export | 4 |
| dispatch | 4 |
| login/logout | 3 |
| setup | 1 |

---

## 2. Berechtigungen (19 Permissions)

| Permission | Label | Routen |
| --- | --- | ---: |
| admin.access | Allgemeiner Admin-Zugang | 99 |
| admin.logs.view | System-Logs einsehen | 7 |
| admin.setup.view | Setup-Uebersicht anzeigen | 2 |
| configuration.integrations.manage | Integrationen verwalten | 4 |
| configuration.mail_templates.manage | Mail-Vorlagen verwalten | 7 |
| configuration.notifications.manage | Benachrichtigungen verwalten | 5 |
| configuration.settings.manage | Systemeinstellungen verwalten | 11 |
| dispatch.lists.manage | Dispatch-Listen verwalten | 4 |
| fulfillment.csv_export.manage | CSV-Export steuern | 4 |
| fulfillment.masterdata.manage | Fulfillment-Stammdaten verwalten | 37 |
| fulfillment.orders.view | Auftraege einsehen | 10 |
| fulfillment.shipments.manage | Sendungen verwalten | 2 |
| identity.users.manage | Benutzerverwaltung | 8 |
| monitoring.audit_logs.view | Audit-Logs einsehen | 1 |
| monitoring.domain_events.view | Domain Events einsehen | 1 |
| monitoring.system_jobs.view | System-Jobs ueberwachen | 1 |
| tracking.alerts.manage | Tracking-Alerts verwalten | 1 |
| tracking.jobs.manage | Tracking-Jobs verwalten | 3 |
| tracking.overview.view | Tracking-Uebersicht anzeigen | 2 |

---

## 3. Rollen (7 + wildcard *)

| Rolle | Sichtbare Routen |
| --- | --- |
| admin | 110 |
| leiter | 99 |
| operations | 65 |
| configuration | 29 |
| support | 12 |
| identity | 8 |
| viewer | 13 |
| noaccess | 0 |

---

## 4. Navigation (t14 + t22)

### 4.1 Struktur: 5 Top-Level-Gruppen, 18 Sub-Items

| Gruppe | Label | Sub-Items |
| --- | --- | :---: |
| operations | Operations | Dispatch-Listen, CSV-Export, Aufträge, Sendungen |
| stammdaten-benutzer | Stammdaten & Benutzer | Stammdaten (Assembly, Freight, Packaging, Senders, Sender-Rules, Variations), Benutzerverwaltung |
| tracking | Tracking | Tracking-Übersicht, Jobs, Alerts |
| system | System (ehem. Monitoring) | System-Status, System-Jobs |
| konfiguration | Konfiguration | Integrationen, Mail-Vorlagen, Einstellungen, Benachrichtigungen |

### 4.2 Menü-Sub-Items (18)

| Gruppe | Label | Route | Permission |
| --- | --- | --- | --- |
| operations | Dispatch-Listen | dispatch-lists | dispatch.lists.manage |
| operations | CSV-Export | csv-export | fulfillment.csv_export.manage |
| operations | Auftraege | fulfillment-orders | fulfillment.orders.view |
| operations | Sendungen | fulfillment-shipments | fulfillment.shipments.manage |
| stammdaten-benutzer | Stammdaten | fulfillment-masterdata | fulfillment.masterdata.manage |
| stammdaten-benutzer | Benutzerverwaltung | identity-users | identity.users.manage |
| tracking | Tracking-Übersicht | tracking-overview | tracking.overview.view |
| tracking | System-Jobs | monitoring-system-jobs | monitoring.system_jobs.view |
| tracking | Alerts | tracking-alerts | tracking.overview.view |
| system | System-Status | monitoring-health | admin.setup.view |
| konfiguration | Integrationen | configuration-integrations | configuration.integrations.manage |
| konfiguration | Mail-Vorlagen | configuration-mail-templates | configuration.mail_templates.manage |
| konfiguration | Einstellungen | configuration-settings | configuration.settings.manage |
| konfiguration | Benachrichtigungen | configuration-notifications | configuration.notifications.manage |
| (Logs) | System-Logs | monitoring-logs | admin.logs.view |
| (Logs) | Audit-Logs | monitoring-audit-logs | monitoring.audit_logs.view |
| (Logs) | Domain-Events | monitoring-domain-events | monitoring.domain_events.view |

*Logs sind als Sub-Sektion unter System oder Monitoring gruppiert.*

---

## 5. Implementierte Fixes und Refactorings

### 5.1 t14 + t22 — Navigation/Menue
- 6 Top-Level-Gruppen auf 5 reduziert (Monitoring -> System)
- Stammdaten + Verwaltung zusammengefasst zu "Stammdaten & Benutzer"
- 18 Sub-Items unter den 5 Gruppen
- NavigationService + Tests aktualisiert (Snapshots in t21)

### 5.2 t32 — Security-Fix
- GET /api/v1/settings/{key} jetzt geschuetzt mit `auth.admin + can:configuration.settings.manage`
- Vorher: keine Auth-Pruefung

### 5.3 t29 — DDD-Refactoring
- `PaginatorLinkGeneratorInterface` verschoben nach `Domain/Shared/`
- `LaravelPaginatorLinkAdapter` verschoben nach `Infrastructure/Pagination/`
- Domain-Layer: 0 Illuminate-Imports

### 5.4 t30 + t31 — A11y-Fixes
- `tabs.blade.php`: `aria-selected` statt `aria-current=page` fuer Tab-Navigation
- Focus-Trap in Modals: bereits in `base.js` (Bootstrap 5) implementiert

### 5.5 t22 — Breadcrumb-Fixes
- `monitoring/system-jobs`: Self-Link entfernt (letzter Breadcrumb-Punkt nicht verlinkt)
- `configuration/integrations`: `currentSection` korrigiert
- 4 identity-Seiten mit Breadcrumbs: index, create, show, edit
- 18 masterdata-submodule mit Breadcrumbs (Assembly, Freight, Packaging, Senders, Sender-Rules, Variations)
- 4 detail-pages mit parent-links: Integration-Show, Order-Show, User-Show, Setting-Edit

### 5.6 t24 — DRY-Refactoring
- `SenderRuleController` nutzt jetzt `MasterdataControllerHelpers` Trait
- Redundante CRUD-Logik eliminiert

---

## 6. Test-Abdeckung

| Test | Pfad | Status |
| --- | --- | --- |
| NavigationServiceTest | tests/Unit/Support/UI/NavigationServiceTest.php | Snapshot-upgedatet (t21) |
| NavigationSnapshotTest | tests/Feature/Layout/NavigationSnapshotTest.php | Snapshot-upgedatet (t21) |
| NavigationServiceFailClosedTest | tests/Feature/Layout/NavigationServiceFailClosedTest.php | Bestehend |
| AdminNavigationTest | tests/Browser/AdminNavigationTest.php | Bestehend |

---

## 7. Dokumentations-Dateien

| Datei | Beschreibung |
| --- | --- |
| SYSTEM_ROUTE_KARTOGRAPHIE.md | Alle 123 Routen mit URI, Permission, Middleware, Action |
| SYSTEM_VIEW_KARTOGRAPHIE.md | 52 geroutete + 56 nicht-geroutete Blade-Views |
| SYSTEM_PERMISSION_MATRIX.md | 19 Permissions mit Routen-Coverage |
| SYSTEM_MENU_ROLE_MATRIX.md | Menü-Sichtbarkeit je Rolle |
| SYSTEM_AUDIT_REPORT.md | Qualitaets-Gate-Check (0 Duplikate, 0 unbenutzte Permissions) |
| SYSTEM_ROUTE_VISIBILITY_MATRIX.md | Routen-Sichtbarkeit je Rolle |
| SYSTEM_CLEANUP_BACKLOG.md | Offene Bereinigungspunkte |
| SYSTEM_REORGANISATION_ROADMAP.md | Strategische Reorganisations-Roadmap |
| CROSS_ROLE_PERMISSION_LEAK_DETECTION.md | Cross-Role-Permission-Analyse |

---

## 8. Technischer Stack

| Komponente | Technologie |
| --- | --- |
| Backend | Laravel (PHP 8.x) |
| Frontend | Blade-Templates + Bootstrap 5 |
| Auth | Laravel Sanctum (API), Session (Web) |
| Datenbank | MySQL (vermutlich) |
| API-Dokumentation | OpenAPI 3.0 (openapi.yaml) |
| Tests | PHPUnit + Dusk |

---

*Generiert: 2026-05-11 — AX4 Development*