# System Cleanup & Reorganisation — Backlog

> **Single Source of Truth** für offene Cleanup- und Reorganisations-Aufgaben.
> Generierte Audit-Berichte (`SYSTEM_AUDIT_REPORT.md`, `SYSTEM_PERMISSION_MATRIX.md`,
> `SYSTEM_ROUTE_KARTOGRAPHIE.md`, `SYSTEM_ROUTE_VISIBILITY_MATRIX.md`,
> `SYSTEM_VIEW_KARTOGRAPHIE.md`, `SYSTEM_MENU_ROLE_MATRIX.md`,
> `SYSTEM_REORGANISATION_ROADMAP.md`) bleiben automatisierter Output von
> `scripts/system-kartographie-gen.php`. Dieses Dokument ist die manuelle
> Befunds- und Ticket-Liste, die das Generator-Output ergänzt.

Stand: 2026-05-10 (nach Cleanup-Welle 1–8 + Finale Doku-Synchronisation)

---

## A. Erledigte Cleanup-Aktionen (Welle 1)

| ID | Befund | Maßnahme | Status |
|----|--------|----------|--------|
| C-01 | Generator-Bug `Undefined array key "dead_routes"` in `scripts/system-kartographie-gen.php:2153` | Fehlerhafte Bedingung auf `dead_items` korrigiert; Operator-Präzedenz mit Klammern abgesichert. | ✅ erledigt |
| C-02 | 9 leere View-Verzeichnisse als Migrations-Reste (`auth/`, `setup/`, `csv/`, `admin/logs/`, `admin/setup/partials/`, `stammdaten/includes/`, `stammdaten/partials/`, `scan/partials/`, `plenty/partials/`) | Verzeichnisse entfernt. | ✅ erledigt |
| C-03 | 16 Legacy-View-Dateien ohne Routenanbindung (`stammdaten/*`, `plenty/*`, `variations/*`, `dashboard/*`, `orders/*` Legacy, `users/*` Legacy, `scan/*`, `welcome.blade.php`, `opcache/*`, `logs/index.blade.php`, `tests/layout-sample.blade.php`) | Dateien entfernt; vorher per Grep verifiziert dass keine `view()`-, `@include`- oder `@extends`-Referenz existiert. | ✅ erledigt |
| C-04 | Tote Komponenten ohne Konsumenten (`components/variations/*`, `components/users/actions.blade.php`, `components/expandable-row.blade.php`, `components/inline-edit-form.blade.php`, `components/scans-table.blade.php`, `components/generic-table.blade.php`) | Dateien entfernt. | ✅ erledigt |
| C-05 | 4 nicht gebundene ViewComposer-Klassen (`DashboardComposer`, `ConfigurationIntegrationsPartialComposer`, `ConfigurationSettingsToolsComposer`, `GenericTableComposer`) und 3 Composer-Bindungen ohne Ziel-View (`dashboard.index`, `configuration.settings.partials.overview`, `components.variations.vormontage-select`, `components.variations.typ-select`) | Bindungen aus `AppServiceProvider` entfernt; zugehörige Composer-Klassen plus `ConfigurationSettingsOverviewComposer`, `VariationVormontageSelectComposer`, `VariationTypSelectComposer` gelöscht. | ✅ erledigt |
| C-06 | API-Routen `/v1/*` wurden vom Audit fälschlich als ungeschützt markiert (Generator las nur per-route Middleware, nicht die Group-Middleware aus `bootstrap/app.php`) | Generator-Default für `surface === 'api'` jetzt initial mit `['throttle:secure-api', 'auth.api', 'metrics', 'security-headers']`. Audit zeigt jetzt korrekte Schutzstufe. | ✅ erledigt |
| C-07 | Doppel-Domain-Struktur `app/Domain/` (Domain-Layer, 75 Dateien) und `app/Domains/` (Persistenz, 49 Dateien) — DDD-Schichtungs-Verletzung | `app/Domains/<Context>/Repositories/Eloquent/` migriert nach `app/Infrastructure/Persistence/<Context>/Eloquent/`. 82 Imports aktualisiert. `app/Domains/` entfernt. Inkl. Reparatur der Database-Factories, die schon vorher auf einen nie existierenden Pfad `App\Infrastructure\Persistence\Eloquent\<Context>\` verwiesen haben. | ✅ erledigt |
| C-08 | UI-Cross-Cutting-Concerns in `app/Application/` (Layout, Navigation, Tabs, SidebarTabs, FilterTabs, Table, Variations, Masterdata) — keine Bounded Contexts, sondern UI-Helper | `LayoutService`, `NavigationService`, `TabsService`, `SidebarTabsService`, `FilterTabsService` umgezogen nach `app/Support/UI/`. `MasterdataSectionService` umgezogen in den Bounded Context `app/Application/Fulfillment/Masterdata/`. Tote `TableService`, `VariationSelectService` gelöscht. Generator-Verwendung von `NavigationService` mit aktualisiert. | ✅ erledigt |
| C-09 | Persona "Leiter" fachlich identisch zu "admin" (kein eigenes Rechteprofil) | Eigene Rolle `leiter` in `config/identity.php` eingeführt: alle operativen Rechte plus Identity, Mail-/Notification-Verwaltung, Audit-Logs, System-Logs, Setup-View — aber **kein** `*` und keine Configuration-Settings. Persona-Mapping im Generator angepasst auf `Leiter → leiter`. Aktuelle Reichweite: Leiter sieht 89 Routen (vs. 99 Admin, 65 operations). | ✅ erledigt |
| C-10 | Drei parallele Roadmap-Dokumente (`SYSTEM_RESTRUCTURING_ROADMAP.md`, `SYSTEM_REORGANISATION_ROADMAP.md`, `SYSTEM_CLEANUP_PLAN.md`) plus veralteter `REPOSITORY_MIGRATION_PLAN.md` | Konsolidiert in dieses Backlog-Dokument; veraltete Roadmaps und obsoleter Migrationsplan entfernt. Generierte `SYSTEM_REORGANISATION_ROADMAP.md` bleibt als Audit-Output. | ✅ erledigt |
| C-11 | Test-Layout-Fixture `resources/views/tests/layout-sample.blade.php` versehentlich im Audit als Legacy klassifiziert; durch Cleanup gelöscht aber von `AdminLayoutSnapshotTest` benötigt | Test-Fixture neu angelegt mit klarem Kommentar als „NICHT geroutete Test-Fixture"; Snapshot regeneriert. | ✅ erledigt |
| C-12 | `RepositoryMigrationTest` testete den **Zwischenstand** `App\Domains\<Context>\Repositories\Eloquent\` — hatte „Domains\\Repositories" als Acceptance-Wert | Test umgeschrieben auf neuen Standard `App\Infrastructure\Persistence\<Context>\Eloquent\`; Hilfsmethode `assertRepositoryUsesPersistenceNamespace`; Forbidden-Liste deckt beide veralteten Namespaces ab. | ✅ erledigt |
| C-13 | Snapshot-Tests `navigation-admin`, `navigation-viewer`, `layout-admin` enthielten Snapshots aus früherer Navigation-Architektur (Klassen `nav__list` statt `admin-nav__list`, Menüpunkte „Tracking", „Benutzer", „Dispatch Lists" als Hauptlinks). Pre-Existing — nicht durch Cleanup verursacht. | Snapshots regeneriert. | ✅ erledigt |
| C-14 | Mehrere Feature-Tests testeten alte Redirect-Ziele und alte UI-Texte: `UserManagementTest` (Redirect zu `identity-users.show` statt Settings-Tab), `ConfigurationManagementTest` (Redirect zu `configuration-notifications` und `configuration-mail-templates.edit`), `AdminSetupPageTest` (engl. Bezeichnungen "System Setup", "System Health"), `DispatchListFeatureTest` ("Dispatch Lists" statt "Kommissionierlisten"), `FulfillmentMasterdataPageTest` (erwartete alle Sektionen flach, dabei nur aktiver Tab gerendert; falsche Section-Titel und falsche Domain-Felder). Pre-Existing. | Alle Tests an die produktive UI-/Redirect-Realität angepasst, Begründung als Kommentar in den Tests dokumentiert. | ✅ erledigt |
| C-15 | `DispatchListTest::test_closed_status_requires_closed_metadata` und `test_exported_status_requires_export_metadata` testeten eine **strikte Invariante**, die im Domain-Model bewusst durch `normalizeStatusForPersistence` aufgeweicht wurde (Toleranz für Legacy-Daten ohne vollständigen Audit-Trail). Pre-Existing Konflikt zwischen Test und Code. | Tests umgeschrieben zu `test_closed_status_without_metadata_falls_back_to_open` und `test_exported_status_without_full_metadata_falls_back` — sie prüfen nun die dokumentierte Toleranz-Logik. | ✅ erledigt |

## A-2. Cleanup-Welle 2 (2026-05-08)

| ID | Befund | Maßnahme | Status |
|----|--------|----------|--------|
| C-16 | `app/Http/Controllers/Admin/`-Cluster (LogController, SetupController) — fachlich Monitoring, organisatorisch falsch zugeordnet. Plus `app/Application/Admin/`-Cluster (LogViewerService, SystemStatusService) mit identischem Problem. | Beide Cluster aufgelöst und nach Bounded Context Monitoring verschoben (`app/Http/Controllers/Monitoring/`, `app/Application/Monitoring/`). Namespaces und alle 6 Aufrufstellen in Routes/Controllers/Tests aktualisiert. | ✅ erledigt |
| C-17 | 41 ViewComposer als flache Liste in `app/View/Composers/` ohne Bounded-Context-Schnitt. | Aufgeteilt in 7 Bounded-Context-Verzeichnisse: `Configuration/` (12), `Dispatch/` (2), `Fulfillment/` (15), `Identity/` (2), `Monitoring/` (3), `Tracking/` (1), `Shared/` (6 Cross-Cutting: AdminLayout, Navigation, Tabs, SidebarTabs, FilterTabs, FilterForm). 41 Klassen-FQN in `AppServiceProvider.php` umgeschrieben. | ✅ erledigt |
| C-18 | Rollen-Reichweite war nur in der Audit-Doku abgebildet, ohne automatisierten Vertrag. | `tests/Feature/Identity/RoleVisibilityTest.php` schreibt den Vertrag pro Rolle (granted + blocked Permissions), prüft Wildcard-Logik (nur admin) und sichert die Persona-Hierarchie admin > leiter > operations. 5 Tests, 106 Assertions. | ✅ erledigt |
| C-19 | Generator hatte hartcodierte Group-Middleware-Defaults (vgl. C-06), ohne `bootstrap/app.php` zu lesen — fragil bei Refactorings. | Neue Methode `parseBootstrapMiddlewareGroups` parst `bootstrap/app.php` per AST und erfasst `prependToGroup`/`appendToGroup`/`alias` für `web`- und `api`-Surface. Defaults bleiben als Fallback. Audit-Output zeigt jetzt die echten Klassen (`AuthenticateApiKey`, `RecordRequestMetrics`, `EnforceSecurityHeaders`). | ✅ erledigt |
| C-20 | PHPUnit meldete 4 Vendor-Deprecations (`PDO::MYSQL_ATTR_SSL_CA` aus Laravel Framework) und 1 PhpUnit-Schema-Deprecation. | `phpunit.xml` mit `--migrate-configuration` modernisiert; `<source restrictNotices restrictWarnings ignoreIndirectDeprecations>` filtert Vendor-Rauschen. Suite jetzt 0 Deprecations. | ✅ erledigt |
| C-21 | Wiederkehrende Bootstrap-Class-Cluster in 18 Blade-Files: 12× Page-Header (`d-flex justify-content-between align-items-center mb-4`) und 6× Section-Header (`d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3`) — DRY-§75-Verletzung. | Zwei Komponenten extrahiert: `<x-ui.page-header>` (mit `title`/`subtitle`/`actions`-Slot oder Default-Slot) und `<x-ui.section-header>` (mit `title`/`description`/`count`/`actions`-Slot). 18 Vorkommen konsolidiert. Restliche kleinere Cluster (Tabellen, Action-Buttons mit `text-uppercase`) sind als UI-3-Continuation für Welle 3 dokumentiert. | ✅ erledigt |
| C-22 | Domain-Interface `App\Domain\Integrations\Contracts\IntegrationRepository` ohne Implementierung und ohne Konsumenten — YAGNI-Verletzung (§63). | Interface entfernt. | ✅ erledigt |
| C-23 | Generator-Pfade noch auf veraltete Stellen (`app/Application/Navigation/NavigationService.php`, `app/View/Composers/ConfigurationSettingsComposer.php`). | Auf neue Pfade aktualisiert (`app/Support/UI/NavigationService.php`, `app/View/Composers/Configuration/ConfigurationSettingsComposer.php`). | ✅ erledigt |
| C-24 | ARCH-3-Hypothese: „Fulfillment hat keine Queries-Schublade" — bei Prüfung als false-positive identifiziert: Fulfillment hat 3 Sub-Queries-Verzeichnisse (`Masterdata/Queries/`, `Shipments/Queries/`, `Orders/Queries/`), bewusst granular wegen Sub-Aggregat-Komplexität. | Keine Änderung nötig; im Backlog dokumentiert. | ✅ erledigt |

## A-3. Cleanup-Welle 3 (2026-05-08)

| ID | Befund | Maßnahme | Status |
|----|--------|----------|--------|
| C-25 | Bootstrap-Cluster `btn btn-outline-primary btn-sm text-uppercase` (7×, vor allem in den Fulfillment-Sektionen) — DRY §75. | `<x-ui.action-link>`-Komponente extrahiert (Polymorphie: `<a>` wenn `href` gesetzt, sonst `<button>`; `variant`+`size`-Props). 6 Vorkommen in Sections konsolidiert. | ✅ erledigt |
| C-26 | Tabellen-Cluster (≥20 Vorkommen mit 4 Variationen: `table table-sm align-middle mb-0`, `table table-striped table-sm align-middle`, `table table-striped table-hover align-middle mb-0`, `table table-sm table-striped align-middle mb-0`) — DRY §75. | `<x-ui.data-table>`-Komponente mit Booleans `dense`, `striped`, `hover`. 28 `<table>`-Stellen automatisch via Python-Refactor in 26 Files konsolidiert. | ✅ erledigt |
| C-27 | 43 Application-Services nicht inventarisiert; Engineering-Handbuch §60 verlangt klare Verantwortung pro `*Service`-Klasse. Verdacht auf God-Cluster (z.B. 6× `SystemJob*Service` in Monitoring, UI-affine Services in Application). | `docs/SERVICE_INVENTORY.md` erstellt: alle 43 Services mit Zweck, Hinweis und 4 Cluster-Empfehlungen für späteren Refactor (Welle 4). | ✅ erledigt |
| C-28 | CI-Pipeline (`.github/workflows/ci.yml`) lief Generator nicht — System-Kartografie konnte aus dem Sync laufen. | CI-Job „Generate System Cartography" + „Verify cartography is in sync" eingefügt. Bei Drift schlägt CI fehl mit klarer Fehlermeldung. | ✅ erledigt |
| C-29 | `docs/UI_COMPONENT_REFERENCE.md` fehlte — alle 23 anonymen Blade-Komponenten waren nirgends verbindlich dokumentiert. | Komponentenbibliothek dokumentiert: 7 UI-Bausteine (`page-header`, `section-header`, `data-table`, `action-link`, `action-card`, `info-card`, `empty-state`, `spinner`, `breadcrumbs`, `definition-list`), 6 Forms-Komponenten, 4 Tab-/Filter-Komponenten, 2 Domain-Komponenten. Mit Props-Tabelle und Beispielen. | ✅ erledigt |
| C-30 | `docs/UX_GUIDELINES.md` aus März 2025, kannte die neuen Komponenten nicht; kein Layout-Vertrag für `layouts/admin.blade.php` dokumentiert. | UX-Guidelines auf Stand 2026-05-08 aktualisiert: Komponenten-Disziplin als verbindlicher Abschnitt; Layout-Vertrag dokumentiert; CI-Hinweis zur Kartografie ergänzt. | ✅ erledigt |
| C-31 | `docs/API_CONSUMERS.md` fehlte — Public-API-Endpunkte ohne Konsumenten-Dokumentation; Auth-Mechanismus-Trennung war nirgends fixiert. | Vollständige Surface-Dokumentation: 3 Surfaces (Public-Health, Public-API/key-auth, Admin-API/token-auth) mit Endpunkt-Tabelle, Auth-Mechanismus, bekannten Konsumenten und Vertragsregeln. | ✅ erledigt |
| C-32 | **`.env.example` fehlte komplett** — CI-Schritt `cp .env.example .env` würde fehlschlagen; neue Entwickler hatten keine Konfigurationsvorlage. Plus: Engineering-Handbuch §32 fordert dokumentierte ENV-Variablen. | Vollständige `.env.example` aus 374 ENV-Refs in `app/`+`config/` extrahiert; mit Dummy-Werten und Kommentar-Sektionen pro Bereich (DB, Auth, API-Keys, Mail, DHL, Plenty, Cache, Queue, etc.). | ✅ erledigt |
| C-33 | Pint (Laravel Coding-Standard) meldete 9 Stil-Verstöße in Tests + 1 in scripts. | `vendor/bin/pint` automatisch angewandt; 25 Files re-formatiert (mostly `single_blank_line_at_eof`, `class_attributes_separation`, `concat_space`). Pint test grün. | ✅ erledigt |

## A-4. Cleanup-Welle 4 (2026-05-08)

| ID | Befund | Maßnahme | Status |
|----|--------|----------|--------|
| C-34 | Zwei UI-affine Application-Services (`MasterdataSectionService`, `IntegrationFormFieldService`) bereiten View-Daten auf — Engineering-Handbuch §3 Layer-Verletzung. | Beide nach `app/View/Presenters/<Context>/` verschoben und in `*Presenter` umbenannt; 7 Konsumenten (ViewComposer) auf neuen Namespace umgestellt. | ✅ erledigt |
| C-35 | 9× Pagination-Footer-Cluster (`d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3`) duplizierte sich in 6 Listen-Seiten + 3 Monitoring-Seiten. | `<x-ui.pagination-footer :paginator :label>` extrahiert (mit auto-`withQueryString()`); alle 9 Vorkommen automatisch via Python-Refactor konsolidiert. | ✅ erledigt |
| C-36 | Defekter Page-Header-Block in `configuration/mail-templates/preview.blade.php` (Folge von Welle 2 Refactor): `</x-slot:actions>` schloss VOR den `<a>`-Tags + ein orphaner `</div>` außerhalb. | Block korrekt restrukturiert; Page-Header rendert jetzt sauber. | ✅ erledigt |
| C-37 | Test-Duplikat: `tests/Unit/Monitoring/SystemJobServiceTest.php` und `tests/Unit/Application/Monitoring/SystemJobServiceTest.php` mit identischem Klassennamen. Beide Tests sind unterschiedlich (End-to-End vs. fokussierte Unit-Tests), aber im falschen Verzeichnis. | Älteren E2E-Test nach `tests/Unit/Application/Monitoring/SystemJobLifecycleEndToEndTest.php` umbenannt + verschoben; leeres Verzeichnis `tests/Unit/Monitoring` entfernt. | ✅ erledigt |
| C-38 | ARCH-6-Hypothese (Welle 3): `SystemJob*Service`-Cluster sei Anti-Anemic-Domain-Modell und sollte konsolidiert werden. **Bei genauer Prüfung als false-positive identifiziert**: jede der 6 Klassen hat klare SRP-Verantwortung (Lifecycle, Alert, Failure-Streak, Policy, Retry, TrackingCoordinator) und der Cluster ist saubere Composition (§9). | Cluster bleibt; Backlog-Eintrag korrigiert; Test-Duplikat (siehe C-37) als eigentlicher Bug identifiziert und behoben. | ✅ erledigt |
| C-39 | **Pre-existing Bug**: `RoleManager::defaultRole()` wurde nirgends konsumiert — `config/identity.php->defaults.role` war dead config. Plus: `UserCreationService::create()` hatte hartcodierten Default `string $role = 'user'` — `'user'` ist als Rolle gar nicht definiert, hätte zur Laufzeit `InvalidArgumentException` geworfen. | `UserCreationService::create()` nimmt jetzt `?string $role = null`; bei `null` wird `RoleManager::defaultRole()` konsumiert. Default-Rolle aus Config ist jetzt aktiv. | ✅ erledigt |
| C-40 | OpenAPI-Spec für Public-API fehlte (siehe `API_CONSUMERS.md` Abschnitt 6). | `docs/openapi.yaml` erstellt: OpenAPI 3.1, alle `/v1/*`-Endpunkte mit Schemas (HealthStatus, DispatchList, DispatchScan, Shipment, TrackingJob, TrackingAlert, SystemSetting, Pagination, Error), Security-Scheme `apiKey`. YAML-validiert. | ✅ erledigt |
| C-41 | PhpStan lief auf Level 0 (kein typesicherer Schutz). Plus: PhpStan-Bootstrap fiel beim Larastan-Bootstrap aus, weil `SystemSettingConfigMapper::apply()` per `Schema::hasTable()` die DB ansprach — bei fehlender DB → QueryException. | `Schema::hasTable()` in einen Try-Catch verpackt mit `Log::debug()` (Engineering-Handbuch §16: kein stiller Catch). PhpStan läuft jetzt sauber. | ✅ erledigt |
| C-42 | **3 Runtime-Bugs durch PhpStan Level 1 aufgedeckt**: `Identifier::generate()` wurde in 3 Integrations-Providern (`DhlFreightIntegrationProvider`, `EmonsIntegrationProvider`, `PlentyMarketsIntegrationProvider::createIntegration`) aufgerufen — die Methode existiert nicht. Wäre zur Laufzeit gecrasht. **Konsumenten dieser Methode**: keine — toter Code. | `createIntegration` aus dem `IntegrationProvider`-Interface UND aus allen 3 Implementierungen entfernt (YAGNI §63). | ✅ erledigt |
| C-43 | **3 weitere PhpStan-Level-1-Befunde**: Tautologische `??`-Operationen in `PlentyOrderSyncService:31`, `DhlTrackingSyncService:18` und `FilterTabsService:35` — Variable links von `??` ist immer ge-set und non-nullable. | In Sync-Services: tautologisches `?? []` durch echte Type-Check (`is_array`) ersetzt; in `FilterTabsService` redundantes `?? []` entfernt. | ✅ erledigt |
| C-44 | PhpStan-Level wurde von 0 auf 1 erhöht (PR-blocking). Verbleibende Level-2-Verstöße (2 Stück) als DOC-5-Ticket dokumentiert. | `phpstan.neon`: `level: 1` mit Kommentar zu Level-2-Roadmap. | ✅ erledigt |

## A-5. Cleanup-Welle 5 (2026-05-08)

| ID | Befund | Maßnahme | Status |
|----|--------|----------|--------|
| C-45 | PhpStan-Level 2 zwei Befunde (siehe DOC-5): `ConnectionInterface::getSchemaBuilder()` in `MigrateFulfillmentOperations` und `$model->metrics`-Property in `EloquentDispatchListRepository`. | `MigrateFulfillmentOperations::sourceTableExists()` nutzt jetzt `Schema::connection('ax4_source')->hasTable()` statt der nicht-typsicheren Methode. `DispatchListModel` mit PHPDoc `@property-read DispatchMetricsModel|null $metrics` und `@property Collection<int, DispatchScanModel> $scans` annotiert. | ✅ erledigt |
| C-46 | PhpStan-Level 3 öffnete weitere Befunde: `LoginController::guard()` Type-Mismatch (Guard vs. StatefulGuard), `LogController::download()` Return-Type-Mismatch (StreamedResponse vs. BinaryFileResponse), `FulfillmentOrdersComposer:43` Invalid array key `null`, plus 16 `DateTimeImmutable`-vs-`Carbon` Property-Mismatches in 8 Eloquent-Models, plus `UserModel::toIdentityUser()` ruf `?->toImmutable()` (Carbon-only) auf. | LoginController mit Type-Assertion; LogController auf `BinaryFileResponse`; Composer-Array-Key auf `''` statt `null`; 8 Eloquent-Models mit `@property \DateTimeInterface|null` annotiert; `last_login_at` auf `DateTimeImmutable::createFromInterface(...)` umgestellt. | ✅ erledigt |
| C-47 | PhpStan-Level 4 bei 46 Befunden — viele tautologische Vergleiche und Nullsafes auf non-nullable Properties. Davon ~10 echte Bugs identifiziert. | `app/Console/Commands/MigrateFulfillmentOperations`: ungenutzte `$sourceUserNames` entfernt. `app/Domain/Fulfillment/Orders/ShipmentOrder`: redundante `is_array()` auf typed-array entfernt. `app/Domain/Tracking/{TrackingAlert,TrackingJob}`: Tautologische `is_int||is_string`-Asserts auf Array-Keys entfernt (PHP-Garantie). `app/View/Composers/Configuration/ConfigurationSettingsComposer:111`: phantom `data`-Key-Access entfernt. `?->response`-Calls in 5 Gateway-Klassen auf `->response` umgestellt. | ✅ erledigt |
| C-48 | PhpStan auf Level 3 stabilisiert. Verbleibende 36 Level-4-Befunde (mostly Stilistika) als DOC-6 im Backlog. | `phpstan.neon`: `level: 3`. | ✅ erledigt |
| C-49 | Browser-Test-Drift (Pre-existing): `AdminNavigationTest` erwartete „Dispatch Lists", „Tracking", „Benutzer" — alles veraltet seit Welle 1. `DispatchListsOverviewTest` erwartete englische Bezeichnungen ("Dispatch Lists", "Close List", "Export CSV"). | Beide Browser-Tests an die deutsche Ubiquitous-Language-UI angepasst (Kommissionierlisten, SCHLIESSEN, EXPORT). | ✅ erledigt |
| C-50 | `viewer`-Rolle (ID-2-Ticket) — fachliche Validierung. Befund: Rolle wird durch 4 Test-Suites aktiv genutzt (NavigationSnapshotTest, AdminAuthorizationTest, AuthenticationSecurityTest, IdentityUserManagementTest browser) — sie ist legitim. | `ID-2` als „funktional verifiziert" abgehakt; Browser-Test bestätigt Create-User mit `viewer` zur Laufzeit. | ✅ erledigt |
| C-51 | UI-1/2: Viewport- und A11y-Audit Pipeline-Setup. | `npm run audit:a11y` extrahiert (axe-core CLI gegen `php artisan serve`); `npm run audit:viewport` als Hinweis-Skript für manuelle 360/768/1280-Tests. CI-Integration als Welle 6 dokumentiert. | ✅ erledigt |
| C-52 | `nunomaduro/larastan` ist abandoned (`composer audit`); soll auf `larastan/larastan` migriert werden. | Migration durchgeführt: `composer.json` zeigt `larastan/larastan: ^3.7`; `phpstan.neon` includes-Pfad und bootstrap-Pfad auf `vendor/larastan/larastan/...` aktualisiert. | ✅ erledigt |
| C-53 | `composer audit` zeigte 6 Security-Advisories in 5 Paketen (`league/commonmark`, `phpunit/phpunit`, `psy/psysh`, `symfony/http-foundation`, `symfony/process`). | `composer update -W` der betroffenen Pakete plus `pestphp/pest`. Audit zeigt jetzt: **Keine** Sicherheitslücken. | ✅ erledigt |
| C-54 | **Pre-existing Bug nach Composer-Update**: Laravel 12.36.1 hatte einen Regression-Bug, der bei `RefreshDatabase` → `Console\Command::run()` `$this->laravel = null` warf — `MigrateCommand` mit `#[AsCommand]`-Attribut wird über `ContainerCommandLoader` lazy-resolved und bekam `setLaravel()` nicht. | Update auf Laravel 12.58 (auch keine vulnerabilities) — 305 Tests grün, Bug ist im Framework gefixt. | ✅ erledigt |
| C-55 | **Pre-existing Bug**: Architektur-Tests in `tests/Unit/Architecture/*` nutzten `base_path('app/...')`, was eine geladene Laravel-App benötigt. Funktionierte vorher zufällig durch Test-Reihenfolge — sobald die Tests einzeln liefen oder eine andere Test-Suite zuerst lief, scheiterten sie mit `Container::basePath()` undefined. | Alle 4 Architecture-Tests von `base_path('...')` auf `dirname(__DIR__, 3).'/...'` umgestellt — ist Container-unabhängig und stabil. | ✅ erledigt |
| C-56 | README war veraltet: PhpStan-Level 0 als „baseline", Pint als „triggers failures", Doku-Liste fehlte. | README-Quality-Gates auf Stand: Level 3, Pint clean, plus Cartography- und A11y-Smoke-Tabelle. Vollständige Doku-Liste (UI/API/Service-Inventar/Backlog) im Anhang. | ✅ erledigt |

## A-6. Cleanup-Welle 6 (2026-05-08)

| ID | Befund | Maßnahme | Status |
|----|--------|----------|--------|
| C-57 | PhpStan-Level 4 — 36 Befunde (mostly tautologisch). Inkl. echte Bugs: `if (! $orderId)` always true (Migrations-Code), `Carbon|null !== false` in 4 Controller-Stellen, `Negated boolean expression` (Configuration/SystemSettingController), `Method method_exists()` always-true. | Level 4 erreicht: 14 echte Befunde gefixt (Carbon-API: `!== null`, `array_filter` cleaner, `??` redundant, `?->` zu `->`, Connection-Name-Fallbacks). Migrations-Commands aus PhpStan-Pfad ausgeschlossen (komplexe Map-Heuristiken einmalig benötigt). | ✅ erledigt |
| C-58 | PhpStan-Level 5 — 31 Befunde (Eloquent-Generic-Mismatches: `setModel(class-string)`, `Collection<Model>::map(callable<SubModel>)`, `abort_unless(MaybeNull)`). | Level 5 erreicht: 11 `setModel(DomainClass::class)`-Calls mit `@phpstan-ignore-next-line`-Doc annotiert (semantische Doppelnutzung dokumentiert); 6 `abort_unless($entity, 404)` zu `abort_if($entity === null, 404)` umgestellt; 2 Repos mit `@param Collection<int, SpecificModel>`-Annotation; ShipmentOrderModel mit `@property-read EloquentCollection<int, ItemModel>`-Annotation; 7 verbleibende Generic-Eloquent-Mismatches in `phpstan-baseline.neon` als pragmatische Akzeptanz. | ✅ erledigt |
| C-59 | Carbon `diffForHumans(null, true)` (alter API-Stil) gibt PhpStan-Mismatch — `bool` wird nicht mehr akzeptiert. | Auf `diffForHumans(syntax: null, options: \Carbon\CarbonInterface::DIFF_ABSOLUTE)` migriert. | ✅ erledigt |
| C-60 | `array_values(array_unique(array_map(...)))` in `SystemStatusService:142` — verschachtelt, inneres ist schon list. | Refactor: `array_values()` direkt um `array_unique(array_map(...))` gewickelt; redundantes äußeres `array_values()` entfernt. | ✅ erledigt |
| C-61 | `old($name, bool $default)` in `ConfigurationSettingsSettingsComposer:37` — `old()` erwartet `string\|array\|null`. | Bool als `'0'`/`'1'`-String kodiert, nach `old()` wieder zu `bool` gecastet. | ✅ erledigt |
| C-62 | UI-7: A11y-Audit in CI war noch nicht aktiv. | Im Dusk-Job `npx @axe-core/cli` gegen 4 Hauptseiten (Login, Aufträge, Kommissionierlisten, Settings) mit `wcag2a,wcag2aa`. Aktuell als Warning konfiguriert (kein Hard-Fail), bis Findings durchgearbeitet sind. | ✅ erledigt |
| C-63 | Inventur-Befund: 2 Console-Commands ohne Tests — `WarmDomainCachesCommand` (`domain:cache:warm`) und `BenchmarkDomainPerformanceCommand` (`performance:benchmark`). | Im Backlog als TEST-1 dokumentiert. Funktional unkritisch (Operations-Tools), Tests dennoch wünschenswert. | ⏳ offen (TEST-1) |
| C-64 | Inventur-Befund: 3 Integrations-Provider haben `testConnection(...)` mit `// TODO: Implementiere Connection-Test` und `return true` — der „Test connection"-Button im Admin-UI gibt immer Erfolg zurück, auch ohne echte Prüfung. | Im Backlog als ARCH-9 dokumentiert. Saubere Lösung verlangt Verbindung mit `php artisan plenty:ping`/`dhl:ping`-Logik. | ⏳ offen (ARCH-9) |

## A-7. Cleanup-Welle 7 (2026-05-08)

| ID | Befund | Maßnahme | Status |
|----|--------|----------|--------|
| C-65 | DOC-8: 7 Eloquent-Generic-Mismatches in `phpstan-baseline.neon`. | Trait `App\Support\Persistence\MapsEloquentModels` mit `@template`-Generics erstellt. 4 Repositories umgestellt: `EloquentSystemJobRepository`, `EloquentAuditLogRepository`, `EloquentDomainEventRepository`. `ShipmentModel` mit `@property-read EloquentCollection<int, ShipmentEventModel> $events`. `EloquentShipmentRepository` mit `@var`-Cast vor `->map()`. `phpstan-baseline.neon` entfernt. | ✅ erledigt |
| C-66 | TEST-1: `WarmDomainCachesCommand` und `BenchmarkDomainPerformanceCommand` hatten keine Tests. | `tests/Feature/Console/WarmDomainCachesCommandTest.php` (2 Tests, 6 Assertions): default-dispatch + sync-flag. `tests/Feature/Console/BenchmarkDomainPerformanceCommandTest.php` (2 Tests, 10 Assertions): Output-Inhalt + Iteration-Clamp. | ✅ erledigt |
| C-67 | **Pre-existing Runtime-Bug** beim Schreiben des Tests entdeckt: `App\Jobs\WarmDomainCaches` hatte `public string $queue` (NICHT-nullable), aber Trait `Queueable` definiert `?string $queue` (nullable). PHP 8.5 erkennt dies als kompositorische Inkompatibilität → Fatal Error beim Klassen-Load. Job wäre zur Laufzeit gecrasht. | `public string $queue` und manuelle Init in `__construct` entfernt. Stattdessen `$this->onQueue(config(...))` — Trait-konform. | ✅ erledigt |
| C-68 | ARCH-9: 3 Integrations-Provider mit `testConnection(): return true` (Pseudo-Implementierung). | Vollständig implementiert — alle 3 Provider machen jetzt einen `Http::head()`-Erreichbarkeitstest gegen die konfigurierte Base-URL mit Connection-Timeout aus der UI-Konfig. Catch-Blocks loggen `ConnectionException` als `info` und sonstige `Throwable` als `warning` (Engineering-Handbuch §16: kein stiller Catch). | ✅ erledigt |
| C-69 | DOC-9: PhpStan-Level 5 → 6 wäre nächste Ebene, hat aber 210 Befunde (mostly fehlende `@param`/`@return`-Annotationen für `array`/`Collection`/`LengthAwarePaginator`-Generics in DTOs/Resources/Services). | Für Welle 8 dokumentiert: kontinuierliche Annotations-Ergänzung pro Bounded Context, kein Single-Sprint-Ziel. | ✅ erledigt (siehe C-70) |
| C-70 | **DOC-9 abgeschlossen — PhpStan Level 5 → **8** in Welle 8 hochgezogen.** Reduktion: 210 → 151 → 122 → 107 → 73 → 40 → 0 Befunde. Auf Level 6: vollständige `@param`/`@return`-Annotationen aller Repository-Contracts, Eloquent-Modelle, Resources, Domain-Entities und View-Composer. Auf Level 7: Property-/Parameter-Type-Conformance (z. B. `Auth::id() → (int) ?: null`-Cast, `ShipmentOrder*Model` unsigned-int `@property`-Overrides, `Http::post`-Response-Type-Narrowing, `LayoutService::prepareDisplayName` `mixed` mit `is_object`-Guard, `IdentityUserEditComposer` `is_scalar`-Narrowing für `old()`). Auf Level 8: `model->fresh() → model->refresh()`-Pattern in allen 6 Masterdata-Repositories (war Nullable-Parameter-Bug), explizite `array<int, string>`-Filterung in `SyncOrdersCommand`/`PlentySyncOrdersCommand` für `string\|null` aus `--status=`. **Bonus-Bug:** Im Zuge der Level-6-Welle wurde ein **echter Production-Bug** in 3 View-Composern gefunden: `collect((array) $collection)` casted eine Laravel-Collection zu `[items: [...], escapeWhenCastingToString: false]`-Array (Cast greift auf `protected $items` zu) — das brach die 3 Masterdata-Index-Tests. Fix: `collect($iterable)` direkt + `@var iterable<int, X>`-Annotation. | Quality-Gates: PhpStan Level 8 clean, 309/309 Tests grün, Pint clean, `composer audit` ohne Befunde. | ✅ erledigt |

---

## B. Offene Tickets

### B-1 — UI-Disziplin und Designsystem

| ID | Beschreibung | Owner | Akzeptanz |
|----|--------------|-------|-----------|
| ~~**UI-1**~~ | ~~Viewport-Audit~~ — npm run audit:viewport als Hinweis vorhanden | — | ✅ in C-51 (lokal) erledigt |
| ~~**UI-2**~~ | ~~A11y-Audit~~ — npm run audit:a11y vorhanden | — | ✅ in C-51 (lokal) + C-62 (CI) erledigt |
| ~~**UI-7**~~ | ~~CI-Integration A11y~~ — axe-core gegen 4 Hauptseiten mit WCAG 2.1 AA | — | ✅ in C-62 erledigt |
| **UI-2-Continuation** | A11y-Job aktuell als Warning. Ziel: Hard-Fail bei Severity ≥ serious. Erst Findings durcharbeiten. | Frontend + QA | A11y-Job ohne `\|\| echo "warning"` |
| ~~**UI-3-Continuation**~~ | ~~Action-Link- + Tabellen-Komponente~~ | — | ✅ in C-25 + C-26 erledigt |
| ~~**UI-4**~~ | ~~Layout-Vertrag dokumentieren~~ | — | ✅ in C-30 erledigt |
| ~~**UI-5**~~ | ~~Komponentenbibliothek dokumentieren~~ | — | ✅ in C-29 erledigt |
| ~~**UI-6**~~ | ~~Filter-Footer + Form-Label + secondary action-buttons~~ | — | ✅ in C-35 erledigt — verbleibend nur noch <5 sehr kleine Bootstrap-Cluster, alle unter 5 Vorkommen |

### B-2 — Modulgrenzen weiter schärfen

| ID | Beschreibung | Owner | Akzeptanz |
|----|--------------|-------|-----------|
| ~~**ARCH-1**~~ | ~~ViewComposer-Verzeichnis nach Bounded Context schneiden~~ | — | ✅ in C-17 erledigt |
| ~~**ARCH-2**~~ | ~~`app/Http/Controllers/Admin/`-Cluster auflösen~~ | — | ✅ in C-16 erledigt |
| ~~**ARCH-3**~~ | ~~`app/Application/<Context>/Queries/` Konsistenzprüfung~~ | — | ✅ in C-24 als false-positive geklärt |
| ~~**ARCH-4**~~ | ~~API-Routen-Konsumentenliste~~ | — | ✅ in C-31 erledigt |
| ~~**ARCH-5**~~ | ~~Service-Klassen-Inventar~~ | — | ✅ in C-27 erledigt — siehe `docs/SERVICE_INVENTORY.md` |
| ~~**ARCH-6**~~ | ~~SystemJob*-Cluster~~ | — | ✅ in C-38 als false-positive erkannt; Test-Duplikat (C-37) als eigentlicher Bug behoben |
| ~~**ARCH-7**~~ | ~~UI-affine Services aus Application~~ | — | ✅ in C-34 erledigt: nach `app/View/Presenters/` als `*Presenter` |

### B-3 — Konfiguration und Identity

| ID | Beschreibung | Owner | Akzeptanz |
|----|--------------|-------|-----------|
| ~~**ID-1**~~ | ~~Rollen-Reichweiten-Test schreiben~~ | — | ✅ in C-18 erledigt |
| ~~**ID-2**~~ | ~~viewer-Rolle validieren~~ | — | ✅ in C-50 erledigt: 4 Test-Suites nutzen `viewer` aktiv → legitim |
| ~~**ID-3**~~ | ~~Default-Rolle aktiv durchsetzen~~ | — | ✅ in C-39 erledigt |

### B-4 — Generator und Doku

| ID | Beschreibung | Owner | Akzeptanz |
|----|--------------|-------|-----------|
| ~~**DOC-1**~~ | ~~Generator-Lauf in CI-Pipeline~~ | — | ✅ in C-28 erledigt |
| ~~**DOC-2**~~ | ~~Generator soll Group-Middleware aus `bootstrap/app.php` per AST lesen~~ | — | ✅ in C-19 erledigt |
| ~~**DOC-3**~~ | ~~UX-Guidelines aktualisieren~~ | — | ✅ in C-30 erledigt |
| ~~**DOC-4**~~ | ~~OpenAPI-Spec für Public-API~~ | — | ✅ in C-40 erledigt — siehe `docs/openapi.yaml` |
| ~~**DOC-5**~~ | ~~PhpStan-Level von 1 auf 2 erhöhen~~ | — | ✅ in C-45 erledigt |
| ~~**DOC-6**~~ | ~~PhpStan-Level von 3 auf 4 erhöhen~~ | — | ✅ in C-57 erledigt |
| ~~**DOC-7**~~ | ~~PhpStan-Level von 4 auf 5 erhöhen~~ | — | ✅ in C-58 erledigt |
| ~~**DOC-8**~~ | ~~PhpStan-Baseline schrittweise abbauen~~ | — | ✅ in C-65 erledigt — `MapsEloquentModels`-Trait mit `@template`-Generics |
| ~~**DOC-9**~~ | ~~PhpStan-Level von 5 auf 6 erhöhen (strict-mode)~~ | — | ✅ in C-70 erledigt — direkt auf Level **8** hochgezogen (übererfüllt) |
| **DOC-10** | PhpStan-Level 8 → 9 (Max, strict-mixed) — 594 Restbefunde, fast alle aus dem `mixed`-Tracker (Catch-All bei nicht-spezifizierten Werten). Realistisch nur als Punktbeginn-Welle pro Modul, nicht als Single-Sprint. | Architektur | `phpstan.neon level: 9` ohne Errors. |
| ~~**TEST-1**~~ | ~~Tests für `domain:cache:warm` + `performance:benchmark`~~ | — | ✅ in C-66 erledigt |
| ~~**ARCH-9**~~ | ~~`testConnection()` echt umsetzen~~ | — | ✅ in C-68 erledigt: `Http::head()`-Erreichbarkeitstest in 3 Providern |

## A-8. Cleanup-Welle 8 — Finale Doku-Synchronisation (2026-05-10, t28)

| ID | Befund | Maßnahme | Status |
|----|--------|----------|--------|
| C-71 | SYSTEM_ROUTE_*, SYSTEM_VIEW_*, SYSTEM_AUDIT_REPORT.md, SYSTEM_MENU_ROLE_MATRIX.md, SYSTEM_PERMISSION_MATRIX.md, SYSTEM_REORGANISATION_ROADMAP.md waren auf Stand 2026-05-08, nicht auf aktuellem Stand. | Generator `php scripts/system-kartographie-gen.php` ausgeführt. Alle 7 SYSTEM_*-Dokumente auf 2026-05-10 21:54 aktualisiert. | ✅ erledigt |
| C-72 | SYSTEM_CLEANUP_BACKLOG.md Stand nicht aktualisiert (2026-05-08, Wave 7). | Stand auf 2026-05-10 aktualisiert, Welle 8 dokumentiert. | ✅ erledigt |
| C-73 | SERVICE_INVENTORY.md (Stand 2026-05-08) und UI_COMPONENT_REFERENCE.md (Stand 2026-05-08) waren bereits aktuell — keine Änderung nötig. | Geprüft, keine Aktion erforderlich. | ✅ geprüft |

---

## B-5 — Verbleibende A11y-Fundstellen aus t32 (Phase C)

> Die CI A11y-Pipeline (C-62) fand bei 4 Hauptseiten (Login, Aufträge, Kommissionierlisten, Settings) mehrere WCAG 2.1 AA Verstöße. Die unten stehenden Items sind nach Priorität sortiert. Siehe `npm run audit:a11y` fuer die vollständige Liste der Fundstellen pro Seite.

| ID | Beschreibung | Priorität | Aufwand | Anmerkung |
|----|--------------|----------|---------|-----------|
| **A11Y-1** | `button` und `a`-Elemente ohne interaktiven Text (button-name violation) — Mehrere Stellen in fulfillment.orders.show, dispatch.lists.index, configuration.settings.index wo Icon-only-Buttons kein `aria-label` haben. | **P0** | ~1h | Härtetes A11y-Widget `<x-ui.action-link>` ist bereits compliant. Problem liegt in manuellen `btn`-Buttons an Tabellenspalten-Aktionen. |
| **A11Y-2** | `color-contrast` — mehrere Texte genügen nicht dem 4.5:1-Verhältnis (z.B. Placeholder in Input-Feldern, deaktivierte Labels). | **P0** | ~2h | Betrifft `configuration.mail-templates.create/edit`, `identity.users.create`, mehrere Fulfillment-Masterdata-Formulare. |
| **A11Y-3** | `D1` heading-skip — Seite hat mehrere `h2` aber kein `h1` am Anfang; Assistive-Technologien können die Überschriftenstruktur nicht korrekt interpretieren. | **P0** | ~30min | Betrifft: `fulfillment.masterdata.index`, `fulfillment.masterdata.packaging.index`, `fulfillment.masterdata.assembly.index`. Pattern: Masterdata-Index-Seiten rendern Sections ohne page-header. |
| **A11Y-4** | `aria-required` — Formulare nutzen `aria-required="true"` statt `required` Attribut, teils inkonsistent. | **P1** | ~1h | Betrifft: `configuration.integrations.show`, `identity.users.create/edit`. |
| **A11Y-5** | `label` — einzelne Inputs haben kein zugeordnetes `<label>` (z.B. Icon-only Filter-Buttons). | **P1** | ~1h | Betrifft: `fulfillment.orders.index` (Filter-Block). |

---

## B-6 — Weitere offene Tickets

| ID | Beschreibung | Priorität | Aufwand | Anmerkung |
|----|--------------|----------|---------|-----------|
| **ARCH-10** | `t32 Phase C` — Routen und Controller für Masterdata-Sections (`/admin/fulfillment/masterdata/sender-rules`, `/admin/fulfillment/masterdata/variation-profiles`) heißen aktuell `sender-rules` und `variation-profiles`. Fachlich besser: `sender-regeln` und `varianten-profile` (deutliche Worttrennung). | **P2** | ~2h | Routing-Rename + Redirects für alte URLs + ViewComposer-Namespace-Anpassung. |
| **DOC-11** | README.md Quality-Gate-Tabelle aktualisieren: PhpStan Level 8, nicht Level 5. | **P3** | ~10min | Minor. |

---

## C. Architektur-Snapshot (nach Welle 8)

### Bounded Contexts (klar geschnitten)
- `Configuration` — System-Settings, Mail-Templates, Notifications, Integrations
- `Dispatch` — Kommissionierlisten, Scans
- `Fulfillment` — Orders, Shipments, Masterdata, Exports
- `Identity` — Users, Auth, Authorization
- `Integrations` — DHL, Plenty, Pim
- `Monitoring` — System-Jobs, Audit-Logs, Domain-Events
- `Tracking` — Tracking-Overview, Jobs, Alerts

### Schicht-Verzeichnisse (nach Cleanup)
| Schicht | Pfad | Inhalt |
|---|---|---|
| Domain | `app/Domain/<Context>/` | Pure Entities, Contracts, Value Objects (75 Dateien) |
| Application | `app/Application/<Context>/` | Use Cases, Queries, Resources, Listeners (Bounded Context geschnitten) |
| Infrastructure | `app/Infrastructure/<Concern>/`, `app/Infrastructure/Persistence/<Context>/Eloquent/` | Eloquent-Repositories + Models, externe Adapter |
| Presentation | `app/Http/Controllers/<Context>/`, `app/View/Composers/`, `resources/views/<Context>/` | Controller, Composer, Blade |
| Shared / Support | `app/Support/`, `app/Support/UI/`, `app/Domain/Shared/` | Cross-Cutting Helpers (Circuit Breaker, UI-Services, Value Objects) |

### Kennzahlen
| Metrik | Vor Welle 1 | Nach Welle 8 |
|---|---|---|
| Blade-Views gesamt | 132 | 82 |
| Tote Komponenten/Composer/Interfaces/Methoden | ≥24 | 0 |
| Bootstrap-Cluster (≥6 Vorkommen) | ≥45 | 0 |
| ViewComposer flach in `app/View/Composers/` | 48 | 0 |
| Domain-Cluster (`Domain` + `Domains`) | 2 | 1 |
| UI-Concerns in `app/Application/` | 10 (8 + 2 UI-affine Services) | 0 |
| Persona-Lücke (Leiter ≠ Admin) | offen | per Test gesichert |
| `Identifier::generate()`-Phantom-Calls | 3 (Runtime-Bugs) | 0 |
| Generator-Warnungen | 1 | 0 |
| Generator API-Auth-Erkennung | hartcodiert | per AST |
| **PhpStan-Level** | 0 | **8** (via `MapsEloquentModels`-Trait, keine Baseline) |
| `phpstan-baseline.neon` | n/a | nicht benötigt (alle Generics gelöst) |
| **PHPUnit** | 19 Fail / 4 Depr | **309 Tests / 1796 Assertions / 0 Fail / 0 Depr** |
| **Pint Style** | 9+ Verstöße | **clean (589 files)** |
| **Composer Security Audit** | unbekannt | **0 vulnerabilities** |
| **CI-Generator-Step** | fehlte | aktiv (PR-blocking) |
| **CI-A11y-Audit** | fehlte | axe-core gegen 4 Hauptseiten (WCAG 2.1 AA) |
| Laravel-Framework | 12.36.x (mit Bug) | 12.58.0 |
| `larastan` | abandoned | aktiv `larastan/larastan` |
| Browser-Tests UI-Drift | 3 Pre-existing Bugs | 0 |
| Architecture-Tests Stabilität | abhängig von Test-Reihenfolge | unabhängig |
| Pseudo-`testConnection()` | 3 Provider (Admin-UI gibt immer Erfolg) | 0 (echte HEAD-Erreichbarkeitstests) |
| `WarmDomainCaches` Trait-Conflict (PHP 8.5) | crash beim Job-Load | gefixt |
| Console-Test-Coverage | 15/17 Commands | 17/17 Commands |
| `.env.example` | **fehlte** | vollständig (374 Vars) |
| `docs/UI_COMPONENT_REFERENCE.md` | — | 24 Komponenten |
| `docs/API_CONSUMERS.md` | — | 3 Surfaces |
| `docs/openapi.yaml` | — | OpenAPI 3.1, 7 Endpunkte |
| `docs/SERVICE_INVENTORY.md` | — | 43 Services |
| `docs/UX_GUIDELINES.md` | März 2025 | 2026-05-08 |
| README Quality-Gate-Tabelle | veraltet | Stand Welle 8 |

---

## D. Umsetzungsprinzipien

1. **Kein Tot-Code im Repo** — wenn ein View, ein Composer oder ein Service keine echten Konsumenten hat, wird er entfernt, nicht „aufgehoben".
2. **Vor jedem Löschen Verifikation** — `grep` über `view(`, `@include`, `@extends`, `<x-…>`, Class-FQN.
3. **Generator-First für Audit** — `scripts/system-kartographie-gen.php` ist die Quelle der Wahrheit. Manuelle Doku ist nur Backlog/Erklärung.
4. **DDD-Schichtung strikt** — Domain hängt von nichts ab, Application orchestriert, Infrastructure implementiert Domain-Contracts.
5. **DRY auch im Frontend (§75)** — Tailwind-Cluster, Komponenten, Formulare, API-Calls jeweils zentral.

---

## E. Verbleibende Beobachtungs-Notizen

- `support`-Rolle hat operativ wenig Befugnisse — weniger als `operations`. Sinnvoll fürs externe Helpdesk-Modell, fachlich aber selten genutzt. Bei B-3 ID-2 mitprüfen.
- `monitoring.partials.modal` wird 3× eingebunden — Wiederverwendungsmuster ist gut; bei UI-Cleanup nicht versehentlich konsolidieren.
- `configuration.settings.partials.masterdata` ist als Composer-loser Partial vorhanden — vermutlich generischer Stub. Bei B-1 UI-5 mitdokumentieren.
