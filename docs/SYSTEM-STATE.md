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

### 5.7 t33 — Settings UX Fix
- Doppelte Success-Meldung in `configuration.settings.index` entfernt. Flash Messages kommen zentral aus `layouts.admin` ueber `x-flash-messages`.
- Settings-Gruppen speichern leitet jetzt auf `tab=settings&settings_group={group}` zurueck. DHL bleibt nach dem Speichern direkt sichtbar.
- Die primaere Settings-Navigation nutzt das Label der aktiven Setting-Gruppe. Dadurch ist `DHL Integration` in der linken Settings-Navigation sichtbar.
- Build-Check: `npm run build` erfolgreich am 2026-05-11.
- QA-Check: `php artisan test tests/Feature/Configuration/ConfigurationManagementTest.php --filter='system_setting_group_redirects_back_to_saved_group|settings_page_shows_flash_once_and_exposes_dhl_navigation'` erfolgreich am 2026-05-11.
- Offene Risiken oder Blocker: PHPUnit meldet weiterhin die bestehende PHP 8.5 Deprecation `PDO::MYSQL_ATTR_SSL_CA` in `config/database.php:62`; kein Blocker fuer diese Aenderung.

### 5.8 t34 — Layout Footer Fix
- `x-ui.info-card` erzwingt keine volle Hoehe mehr. Lange Inhalte wie Tracking-Tabellen bleiben dadurch im normalen Dokumentfluss.
- Der Admin-Footer ist kompakt und ohne Bootstrap-Grid-Abstand gerendert.
- Build-Check: `npm run build` erfolgreich am 2026-05-11.
- QA-Check: `php artisan test tests/Feature/Layout/AdminLayoutSnapshotTest.php tests/Feature/Configuration/ConfigurationManagementTest.php --filter='layout_renders_components_snapshot|settings_page_shows_flash_once_and_exposes_dhl_navigation'` erfolgreich am 2026-05-11.
- Offene Risiken oder Blocker: Reale Browserpruefung der Auftragsdetailseite war wegen Auth und lokalen Daten nicht vollstaendig moeglich. PHPUnit meldet weiterhin die bestehende PHP 8.5 Deprecation `PDO::MYSQL_ATTR_SSL_CA` in `config/database.php:62`.

### 5.9 t35 — Order Detail Definition List Fix
- Auftragsdetaildaten werden nicht mehr als komplexes Inline-Array im Blade-Komponentenattribut aufgebaut. Dadurch wird `?->` nicht mehr als Tag-Ende fehlinterpretiert und PHP-Fragmente werden nicht mehr im Browser ausgegeben.
- `x-ui.definition-list` escaped normale Werte standardmaessig und rendert bewusstes HTML nur noch ueber `Htmlable` oder View-Objekte.
- Die User-Detailseite markiert Badge-Markup explizit als `HtmlString`, damit die gemeinsame Komponente konsistent bleibt.
- QA-Check: `php artisan test tests/Feature/Fulfillment/ShipmentOrderControllerTest.php` erfolgreich am 2026-05-11.
- QA-Check: `php artisan test tests/Feature/Identity/UserManagementTest.php` erfolgreich am 2026-05-11.
- Offene Risiken oder Blocker: PHPUnit meldet weiterhin die bestehende PHP 8.5 Deprecation `PDO::MYSQL_ATTR_SSL_CA` in `config/database.php:62`.

### 5.10 t36 — Order Detail Tabellenlayout Fix
- Tabellenkoepfe in der zentralen Tabellenkomponente sind jetzt linksbuendig ausgerichtet. Header und Zellinhalt stehen dadurch wieder optisch auf derselben Spalte.
- Die Auftragsdetailseite nutzt `x-ui.data-table` ohne zusaetzlich verschachtelten `.table-responsive` Wrapper.
- Build-Check: `npm run build` erfolgreich am 2026-05-11.
- QA-Check: `php artisan test tests/Feature/Fulfillment/ShipmentOrderControllerTest.php --filter='it_shows_order_details'` erfolgreich am 2026-05-11.
- Offene Risiken oder Blocker: PHPUnit meldet weiterhin die bestehende PHP 8.5 Deprecation `PDO::MYSQL_ATTR_SSL_CA` in `config/database.php:62`.

### 5.11 t37 — Pagination Styling Fix
- Zentrale CSS-Regeln fuer `.pagination`, `.page-item` und `.page-link` ergaenzt.
- Auftrags-, Sendungs- und Dispatchlisten nutzen dadurch dieselbe Pagination-Darstellung statt nackter Browser-Links.
- Build-Check: `npm run build` erfolgreich am 2026-05-11.
- QA-Check: `php artisan test tests/Feature/Fulfillment/ShipmentOrderControllerTest.php tests/Feature/Fulfillment/ShipmentAdminTest.php tests/Feature/Dispatch/DispatchListFeatureTest.php --filter='filters_orders_by_combined_criteria|it_lists_shipments|list_page|index'` erfolgreich am 2026-05-11. Der Filter traf den Auftragslistenfall.
- Offene Risiken oder Blocker: PHPUnit meldet weiterhin die bestehende PHP 8.5 Deprecation `PDO::MYSQL_ATTR_SSL_CA` in `config/database.php:62`.

### 5.12 t38 — Modal Bundle Fix
- Der lokale Bootstrap-kompatible Modal Controller wird jetzt im zentralen JavaScript Entry `resources/js/app.js` geladen.
- Bootstrap-aehnliche Modals mit `data-bs-toggle`, `data-bs-target`, `data-bs-dismiss` und `bootstrap.Modal.getInstance()` sind dadurch aktiv statt nur als sichtbares HTML im Seitenfluss vorhanden.
- Build-Check: `npm run build` erfolgreich am 2026-05-11.
- QA-Check: `php artisan test tests/Feature/Dispatch/DispatchListFeatureTest.php` und `php artisan test tests/Feature/Fulfillment/ShipmentOrderControllerTest.php` erfolgreich am 2026-05-11.
- Offene Risiken oder Blocker: PHPUnit meldet weiterhin die bestehende PHP 8.5 Deprecation `PDO::MYSQL_ATTR_SSL_CA` in `config/database.php:62`.

---

## 6. Test-Abdeckung

| Test | Pfad | Status |
| --- | --- | --- |
| NavigationServiceTest | tests/Unit/Support/UI/NavigationServiceTest.php | Snapshot-upgedatet (t21) |
| NavigationSnapshotTest | tests/Feature/Layout/NavigationSnapshotTest.php | Snapshot-upgedatet (t21) |
| NavigationServiceFailClosedTest | tests/Feature/Layout/NavigationServiceFailClosedTest.php | Bestehend |
| AdminNavigationTest | tests/Browser/AdminNavigationTest.php | Bestehend |
| ConfigurationManagementTest | tests/Feature/Configuration/ConfigurationManagementTest.php | Settings Flash und DHL Navigation geprueft (t33) |
| AdminLayoutSnapshotTest | tests/Feature/Layout/AdminLayoutSnapshotTest.php | Footer und Card Layout geprueft (t34) |
| ShipmentOrderControllerTest | tests/Feature/Fulfillment/ShipmentOrderControllerTest.php | Auftragsdetail ohne Blade-Fragmente, ohne verschachtelte Tabellenwrapper und Fulfillment Order Rendering geprueft (t35, t36, t38) |
| UserManagementTest | tests/Feature/Identity/UserManagementTest.php | Definition-List HTML-Werte geprueft (t35) |
| DispatchListFeatureTest | tests/Feature/Dispatch/DispatchListFeatureTest.php | Dispatchlisten Rendering und Modal Bundle Integration geprueft (t38) |

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

---

## 9. DHL-Freight Integration (t2-t10)

### 9.1 Gateways (4 Contracts + 4 Implementations)

| Interface | Implementierung |
| --- | --- |
| DhlFreightGatewayInterface | DhlFreightGateway |
| DhlTrackingGatewayInterface | DhlTrackingGateway |
| DhlAuthenticationGatewayInterface | DhlAuthenticationGateway |
| DhlPushGatewayInterface | DhlPushGateway |

### 9.2 Application Services (7)

| Service | Verantwortung |
| --- | --- |
| DhlShipmentBookingService | Buchungslogik (t2) |
| DhlLabelService | Label-Generierung (t3) |
| DhlPriceQuoteService | Preisanfragen (t4) |
| DhlPayloadMapperService | Payload-Mapping (t5) |
| DhlBulkBookingService | Mehrfach-Buchung (t6) |
| DhlCancellationService | Stornierung (t7) |
| DhlProductCatalogService | Produktkatalog (ProductCatalog) |

### 9.3 DTOs (7 + 2 Collections)

| DTO | Zweck |
| --- | --- |
| DhlServiceDto | Service-Struktur |
| DhlProductDto | Produkt-Struktur |
| DhlTimeTableEntryDto | Zeitplan-Eintrag |
| DhlBookingRequestDto | Buchungsanfrage |
| DhlBookingResponseDto | Buchungsantwort |
| DhlPriceQuoteRequestDto | Preisanfrage |
| DhlPriceQuoteResponseDto | Preisangebot |
| DhlEventCodeLabel | Event-Code Label |
| DhlServiceOptionCollection | Service-Option-Sammlung |

### 9.4 Masterdata: FreightProfile CRUD

| Komponente | Pfad |
| --- | --- |
| Entity | Domain/Entities/FreightProfile |
| Repository | Application/Repositories/FreightProfileRepository |
| Service | Application/Services/FreightProfileService |
| Controller | Http/Controllers/Admin/FreightProfileController |
| Views | resources/views/admin/fulfillment/freight-profiles/ |
| Requests | Http/Requests/FreightProfile/ |

### 9.5 Migrations (3)

| Migration | Inhalt |
| --- | --- |
| dhl_fields in shipment_orders | tracking_number, label_url, booking_reference, dhl_product, dhl_service |
| dhl_fields in freight_profiles | dhl_product_code, dhl_service_options, estimated_delivery_days |
| cancellation fields | cancellation_reason, cancelled_at, cancelled_by |

### 9.6 API-Endpunkte (14)

| Methode | URI | Beschreibung | Task |
| --- | --- | --- | --- |
| GET | /api/admin/dhl/products | DHL-Produktliste | t2 |
| GET | /api/admin/dhl/services | Zusatzservices | t2 |
| POST | /api/admin/dhl/validate-services | Service-Validierung | t2 |
| GET | /api/admin/dhl/timetable | Zeitplantabelle | t4 |
| POST | /api/admin/dhl/booking | Buchung | t2 |
| GET | /api/admin/dhl/booking/{id} | Buchungsstatus | t2 |
| GET | /api/admin/dhl/price-quote | Preisanfrage | t4 |
| GET | /api/admin/dhl/label/{id} | Label-Abruf | t3 |
| DELETE | /api/admin/dhl/shipment/{id} | Stornierung | t7 |
| POST | /api/admin/dhl/bulk-book | Mehrfach-Buchung | t6 |
| POST | /api/admin/dhl/bulk-cancel | Mehrfach-Stornierung | t7 |
| GET | /api/admin/dhl/tracking/{trackingNumber}/events | Tracking-Events | t8 |
| GET | /admin/fulfillment/orders/{order}/dhl/label/preview | Label-Vorschau | t3 |
| GET | /admin/fulfillment/orders/{order}/dhl/label/download | PDF-Download | t3 |

### 9.7 Neue Komponenten (4)

| Komponente | Typ | Task |
| --- | --- | --- |
| x-dhl.product-catalog-modal | Blade-Component | t2 |
| x-dhl.tracking-timeline | Blade-Component | t8 |
| x-dhl.bulk-booking-modal | Blade-Component | t6 |
| x-ui.modal | Blade-Component | t3 |

### 9.8 Konfiguration

**config/services.php:**
- DHL Auth Credentials fuer Bearer Token
- DHL Freight Credentials und Auth Modus
- API Endpoint URLs
- Timeout Einstellungen

**ENV-Variablen (DHL_AUTH_*):**
- DHL_AUTH_BASE_URL
- DHL_AUTH_USERNAME
- DHL_AUTH_PASSWORD
- DHL_AUTH_PATH

**ENV-Variablen (DHL_FREIGHT_*):**
- DHL_FREIGHT_BASE_URL
- DHL_FREIGHT_API_KEY
- DHL_FREIGHT_API_SECRET
- DHL_FREIGHT_AUTH

### 9.9 QA Nachweis 2026-05-11

**Build Check:** `npm run build` erfolgreich. Neues Asset: `public/build/assets/css/app-C38LkUfX.css`.

**QA Check:** `php artisan test tests/Unit/Infrastructure/Integrations/DhlAuthenticationGatewayTest.php tests/Unit/Infrastructure/Integrations/DhlFreightGatewayTest.php tests/Feature/Fulfillment/Integrations/DhlShipmentBookingTest.php` erfolgreich mit 40 Assertions.

**Offene Risiken:** Die lokale `.env` enthaelt aktuell keine DHL Auth und Freight Secrets. Reale DHL Sandbox Buchungen koennen erst funktionieren, wenn `DHL_AUTH_USERNAME`, `DHL_AUTH_PASSWORD`, `DHL_FREIGHT_API_KEY` und je nach Auth Modus `DHL_FREIGHT_API_SECRET` gesetzt sind. Die Testausgabe enthaelt weiterhin eine bestehende PHP 8.5 Deprecation aus `config/database.php` fuer `PDO::MYSQL_ATTR_SSL_CA`.

---

*Generiert: 2026-05-11 — AX4 Development*
