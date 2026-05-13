# PROJ-6: DHL Catalog — Read-Only Admin-Inspektion

## Status: Planned
**Created:** 2026-05-12
**Last Updated:** 2026-05-12

## Dependencies
- **Requires:** [PROJ-1](PROJ-1-dhl-catalog-domain-persistence.md) — Datenmodell
- **Requires:** [PROJ-2](PROJ-2-dhl-catalog-sync-job.md) — Sync-Job + Status-Tabelle

## Kontext
Da der Katalog vollautomatisch via Sync gepflegt wird (kein manuelles Editing), brauchen Admins ein **Read-Only-Fenster**, um zu sehen:
- Was steht aktuell im Katalog (Produkte, Services, Assignments)?
- Wann lief der letzte Sync und war er erfolgreich?
- Welche Einträge sind deprecated, und welche Nachfolger sind gepflegt?
- Welche Audit-Einträge wurden zuletzt geschrieben?

Außerdem brauchen sie einen **manuellen Sync-Trigger** für Ad-hoc-Aktualisierung und ein **CLI-Tool** zum Pflegen von `replaced_by_code` (die einzige zulässige manuelle Änderung am Katalog).

Dieses Feature kommt in der Build-Order direkt nach PROJ-2, damit der Output des Sync-Jobs sofort sichtbar geprüft werden kann — bevor PROJ-3/4/5 darauf aufbauen.

## User Stories
- Als **Admin** möchte ich auf `/admin/settings/dhl/katalog` eine Übersicht aller im System hinterlegten DHL-Produkte sehen — mit Filter nach Routing, Status (aktiv/deprecated) und Quelle (seed/api/manual).
- Als **Admin** möchte ich pro Produkt sehen, welche Additional Services für welches Routing+Payer erlaubt/pflicht/verboten sind, um Versandkonfigurationen zu prüfen.
- Als **Admin** möchte ich auf der Übersichtsseite den letzten Sync-Status (Zeitpunkt, Erfolg/Fehler, Diff-Zähler) sehen und bei Bedarf einen manuellen Sync per Button auslösen.
- Als **Admin** möchte ich die letzten 100 Audit-Log-Einträge filterbar nach Entity-Typ und Zeitraum einsehen können, um nachzuvollziehen, was sich am Katalog geändert hat.
- Als **Compliance** möchte ich, dass diese Seite **read-only** ist — keine Edit-/Delete-Buttons, ausgenommen der Sync-Trigger und CLI-only-Befehle für Nachfolger-Mapping.
- Als **DevOps** möchte ich via CLI `php artisan dhl:catalog:set-successor <oldCode> <newCode>` den Nachfolger eines deprecated Produkts setzen, ohne SQL schreiben zu müssen.

## Acceptance Criteria

### Route & Navigation
- [ ] Neue Route `GET /admin/settings/dhl/katalog` (Name: `admin.settings.dhl.catalog.index`).
- [ ] Detail-Routen:
  - `GET /admin/settings/dhl/katalog/produkte/{code}` → Produkt-Detail mit allen Assignments
  - `GET /admin/settings/dhl/katalog/services/{code}` → Service-Detail mit Parameter-Schema-Vorschau
  - `GET /admin/settings/dhl/katalog/audit` → Audit-Log-Liste
- [ ] Trigger-Route: `POST /admin/settings/dhl/katalog/sync` (CSRF-geschützt, ruft `SynchroniseDhlCatalogService` async via Queue auf, zeigt Flash-Hinweis „Sync gestartet").
- [ ] Eintrag in der bestehenden Admin-Sidebar unter „Versand → DHL Freight → Produktkatalog" (Wiederverwendung der bestehenden [BreadcrumbBuilder.php](app/Support/UI/BreadcrumbBuilder.php)).

### Berechtigungen
- [ ] Zugang nur für User mit Rolle `admin` oder Permission `dhl-catalog.view`.
- [ ] Sync-Trigger erfordert zusätzlich Permission `dhl-catalog.sync`.
- [ ] Audit-Log-Ansicht erfordert Permission `dhl-catalog.audit.read`.
- [ ] Permissions werden im bestehenden Permission-System angelegt (siehe [SYSTEM_PERMISSION_MATRIX.md](docs/SYSTEM_PERMISSION_MATRIX.md)) und in der Permission-Migration registriert.

### Produktkatalog-Übersicht
- [ ] Tabelle mit Spalten: Code, Name, Routings (gekürzt mit Tooltip), Status (Badge: aktiv/deprecated), Quelle, Letzter Sync, # Services.
- [ ] **Filter**: Routing (Multi-Select aus vorhandenen `from_country`/`to_country`), Status, Quelle, Volltext-Suche über Code+Name.
- [ ] **Paginierung**: Standard 25/Seite, nutzt bestehende [PaginatedResult.php](app/Domain/Shared/ValueObjects/Pagination/PaginatedResult.php).
- [ ] Klick auf Code öffnet Detail-Seite.
- [ ] Banner oben zeigt aktuellen Sync-Status (grün/gelb/rot, siehe PROJ-2 `dhl_catalog_sync_status`).
- [ ] Button „Sync jetzt starten" sichtbar bei Permission `dhl-catalog.sync`, mit Confirmation-Dialog.

### Produkt-Detail
- [ ] Header zeigt: Code, Name, Beschreibung, Validität (von/bis), Status, deprecated_at, replaced_by_code (mit Link auf Nachfolger), Limits (Gewicht/Maße).
- [ ] Tabs:
  - **Routings**: From-/To-Country-Matrix, welche Routings sind unterstützt.
  - **Services**: Liste aller `DhlProductServiceAssignment` zu diesem Produkt, gruppiert nach Service-Kategorie. Pro Eintrag: Service-Code+Name, Routing-Filter, Payer, Requirement-Badge (allowed/required/forbidden), Default-Parameter.
  - **Audit**: Letzte 50 Audit-Einträge nur für diesen Produkt-Code.

### Service-Detail
- [ ] Code, Name, Beschreibung, Kategorie, Status.
- [ ] **Parameter-Schema-Preview**: JSON-Schema wird als kompakte Tabelle gerendert (Feld, Typ, Required, Default, Constraint).
- [ ] Liste der Produkte, die diesen Service erlauben/erfordern/verbieten.

### Audit-Log-Ansicht
- [ ] Tabelle mit Spalten: Zeitstempel, Entity-Typ, Entity-Key, Action, Actor, Diff (Modal-Button).
- [ ] **Filter**: Zeitraum (von-bis), Entity-Typ, Action, Actor.
- [ ] **Paginierung**: 50/Seite.
- [ ] Diff-Modal zeigt JSON pretty-printed, before/after side-by-side.

### CLI für Nachfolger-Mapping
- [ ] **`php artisan dhl:catalog:set-successor {oldCode} {newCode}`**: Validiert dass `oldCode` deprecated ist und `newCode` aktiv. Setzt `replaced_by_code`. Schreibt Audit-Eintrag mit `actor=user:<email>` (aus `--actor=email@example.com` Pflicht-Option oder CLI-Login). Fehlt der Param → Command bricht ab.
- [ ] **`php artisan dhl:catalog:unset-successor {oldCode}`**: Entfernt `replaced_by_code`. Audit-Eintrag.
- [ ] **`php artisan dhl:catalog:list-deprecated`**: Tabelle aller deprecated Produkte mit/ohne Nachfolger.

### Visuelles
- [ ] Konsistentes Design mit bestehenden Admin-Settings-Seiten (siehe [DhlFreightSettingsController.php](app/Http/Controllers/Admin/Settings/DhlFreightSettingsController.php)). Wiederverwendung bestehender Komponenten (Tabellen, Filter, Badges, Banner).
- [ ] Keine eigenen CSS-Hacks — nur bestehende Tailwind-Tokens/Komponenten.
- [ ] Responsive ab 768px (Admin-Seite, mobile First nicht zwingend, aber Tablet muss bedienbar sein).
- [ ] Status-Badges einheitlich: aktiv = grün, deprecated = gelb, fehler = rot.

## Edge Cases
- **Sync läuft gerade**: Beim Aufruf der Übersicht zeigt das Banner „Sync läuft seit XX Sekunden", Button ist disabled. Polling alle 5s über kleinen JSON-Endpoint `/admin/settings/dhl/katalog/sync/status`.
- **Leerer Katalog**: Vor erstem erfolgreichen Sync zeigt die Seite Empty-State mit Hinweis „Bitte ersten Sync ausführen" und prominentem Sync-Button.
- **Sync-Fehler im Banner**: Rotes Banner mit Fehlertext aus `dhl_catalog_sync_status.last_error` (gekürzt auf 200 Zeichen, Modal für Volltext).
- **Sehr großer Katalog (>5000 Assignments)**: Übersicht paginiert, Produkt-Detail-Tab „Services" lazy-loaded.
- **Set-successor mit zirkulärer Kette**: `A→B→C→A` erkennen → CLI bricht mit Hinweis ab.
- **Set-successor auf nicht-deprecated Code**: CLI bricht ab — Nachfolger nur für deprecated Produkte erlaubt.
- **Audit-Log leer**: Empty-State, kein Fehler.
- **Permission `dhl-catalog.view` ohne `dhl-catalog.audit.read`**: Audit-Tab/Route gibt 403.
- **Sync-Trigger durch zwei Admins kurz hintereinander**: Zweiter Aufruf erhält Flash-Hinweis „Sync läuft bereits" (durch `withoutOverlapping`-Lock aus PROJ-2).

## Technical Requirements
- **Schichtung**: Controller (Presentation) rufen Application-Services und Repositories aus PROJ-1/PROJ-2 auf, **keine** direkten DB-Queries oder Fachlogik im Controller. Wiederverwendung bestehender [PaginatorLinkGeneratorInterface.php](app/Domain/Shared/ValueObjects/Pagination/PaginatorLinkGeneratorInterface.php).
- **Sicherheit (§19, §20, §56)**: Permission-Checks im Controller UND als zweite Verteidigungslinie in der View. Kein Service-Key-Zugriff client-seitig. Sync-Trigger ist POST mit CSRF.
- **Idempotenz**: Sync-Trigger ist idempotent durch `withoutOverlapping`-Lock.
- **Performance**: Übersichts-Query mit Index auf `(deprecated_at, code)`. Audit-Log-Query nutzt Index aus PROJ-1.
- **Logging**: Sync-Trigger via UI loggt `actor=user:<id>` im Audit-Log (im Gegensatz zum Scheduler mit `system:dhl-sync`).
- **Testing**: Feature-Tests pro Route (Auth, Permission, Filter, Pagination). CLI-Commands per Console-Test. Edge Cases (leerer Katalog, Sync läuft) per Browser-Test (z.B. Pest/Dusk) für mind. den Glücksfall.
- **Accessibility (§51)**: Tabellen mit `<caption>`, Filter-Labels, Tastatur-Navigation, ausreichende Kontraste für Status-Badges (nicht nur Farbe — auch Icon/Text).
- **i18n**: Strings über bestehende Übersetzungsmechanik (de.json), keine hardgecodeten deutschen Strings in Views.

## Out of Scope
- Bearbeiten von Katalog-Einträgen (bewusst nicht — vollautomatisch, siehe Entscheidung Runde 1)
- Manuelles Hinzufügen von Produkten/Services (out — kommt nur via API)
- Export des Katalogs als CSV/Excel (späteres Reporting-Feature)
- Notification an Slack-Webhook (in PROJ-2 als „Mail + Dashboard + Sentry" entschieden; Slack nicht enthalten)

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### Leitprinzipien
- **§7 Presentation:** Controller validieren Input, rufen Repositories (PROJ-1) bzw. den `SynchroniseDhlCatalogService` (PROJ-2) auf, mappen das Ergebnis in ein ViewModel-Array — **keine** Fachlogik, **keine** DB-Queries, **keine** Eloquent-Aufrufe im Controller.
- **§20 Auth + Permission:** Jede Route hat einen Middleware-Stack `web` → `auth` → `can:<permission>`. Permission-Check zusätzlich in der View als zweite Verteidigungslinie (`@can`).
- **§51 Accessibility:** Tabellen mit `<caption>` + `<th scope>`, Filter-Form-Felder mit `<label for>`, Status-Badges immer mit Icon + Text (nicht nur Farbe), Tastatur-Navigation für Modals, sichtbarer Fokus-Ring, ausreichende Kontraste.
- **§75 Frontend-DRY:** Wiederverwendung bestehender Blade-Komponenten (`x-ui.section-header`, `x-forms.*`), zentraler `BreadcrumbBuilder`, keine inline-Scripts/Styles.

---

### 1. Routes-Layout (`routes/web.php`)

Eingefügt **innerhalb** der bestehenden Admin-Group, **neben** dem `settings/dhl-freight`-Block (Zeile ~394) — analoge Struktur, separater Permission-Scope. Konvention: Permission-Namen mit Punkt (wie `settings.dhl_freight.manage`), URL-Namespace `katalog` (deutsch, Ubiquitous Language).

```text
Route::prefix('settings/dhl/katalog')
    ->name('admin.settings.dhl.catalog.')
    ->middleware(['auth', 'can:dhl-catalog.view'])
    ->group(function () {
        // Übersicht
        Route::get('/',          [DhlCatalogIndexController::class,   'index'])->name('index');

        // Produkt-Detail (Code = 1..50 Zeichen, alphanumerisch + Trennzeichen)
        Route::get('/produkte/{code}', [DhlCatalogProductController::class, 'show'])
            ->where('code', '[A-Za-z0-9_\-]+')
            ->name('products.show');

        // Service-Detail
        Route::get('/services/{code}', [DhlCatalogServiceController::class, 'show'])
            ->where('code', '[A-Za-z0-9_\-]+')
            ->name('services.show');

        // Audit-Log: eigene Permission (engere Sichtbarkeit)
        Route::get('/audit', [DhlCatalogAuditController::class, 'index'])
            ->middleware('can:dhl-catalog.audit.read')
            ->name('audit.index');

        // Sync-Trigger: eigene Permission
        Route::middleware('can:dhl-catalog.sync')->group(function () {
            Route::post('/sync',        [DhlCatalogSyncTriggerController::class, 'trigger'])->name('sync.trigger');
            Route::get('/sync/status',  [DhlCatalogSyncTriggerController::class, 'status'])->name('sync.status');
        });
    });
```

**Hinweise:**
- POST `/sync` wird durch Laravels CSRF-Middleware (`web`) automatisch geschützt.
- `/sync/status` ist ein JSON-Endpoint für Frontend-Polling (5 s Intervall, Edge Case "Sync läuft").
- Alle Routes liegen unterhalb von `admin.settings.dhl.catalog.*` und tauchen damit konsistent im `BreadcrumbBuilder` auf.

---

### 2. Controllers (`app/Http/Controllers/Admin/Settings/DhlCatalog/`)

Eigenes Sub-Namespace, weil 5 Controller zusammengehören und das `Admin/Settings/`-Verzeichnis nicht überfrachtet werden soll. Alle Controller `final`, alle Dependencies via Konstruktor-Injection, kein Service-Locator.

**Schicht-Mapping (§3–§8):**
- Controller (Presentation) → Repository-Interfaces (Domain, PROJ-1) + Application-Service (PROJ-2).
- Repository-Implementierungen leben in `app/Infrastructure/Persistence/Dhl/Catalog/` (PROJ-1).
- View-Daten werden über private `present*()`-Methoden in primitive Arrays gemappt (Pattern wie in `DhlFreightSettingsController::presentConfiguration`).

#### 2.1 `DhlCatalogIndexController`
**Verantwortung:** Übersichtsseite — Produktliste mit Filter/Pagination + Sync-Status-Banner.

```text
final class DhlCatalogIndexController
{
    public function __construct(
        private readonly DhlCatalogProductRepository $productRepository,   // PROJ-1, Domain-Interface
        private readonly DhlCatalogSyncStatusRepository $syncStatusRepository, // PROJ-2
        private readonly PaginatorLinkGeneratorInterface $linkGenerator,   // bestehend
    ) {}

    public function index(DhlCatalogIndexFilterRequest $request): View;
}
```

- `DhlCatalogIndexFilterRequest` (Form-Request) validiert: `routing[]` (Country-Code-Paare), `status` in `['active','deprecated']`, `source` in `['seed','api','manual']`, `q` (Volltext ≤ 64 Zeichen), `page` (int ≥ 1).
- Methode delegiert an `$productRepository->paginate(DhlCatalogProductFilter, perPage: 25)` → `PaginatedResult`.
- `$syncStatusRepository->currentStatus()` liefert ein `DhlCatalogSyncStatus`-VO (PROJ-2).
- View bekommt: `products` (PaginatedResult), `filterValues`, `syncStatus`.

#### 2.2 `DhlCatalogProductController`
**Verantwortung:** Produkt-Detailseite mit Tabs Routings / Services / Audit.

```text
final class DhlCatalogProductController
{
    public function __construct(
        private readonly DhlCatalogProductRepository $productRepository,
        private readonly DhlProductServiceAssignmentRepository $assignmentRepository, // PROJ-1
        private readonly DhlCatalogAuditRepository $auditRepository,                  // PROJ-1
    ) {}

    public function show(string $code): View; // throws 404 wenn nicht gefunden
}
```

- Lazy-Loading-Tab "Services": Initial wird nur die Service-Anzahl gerendert; bei großen Produkten (>200 Assignments) lädt der Tab seine Daten via separater Route-/Fragment-Antwort (KISS: Erst-Implementierung lädt synchron — Lazy-Load erst, wenn das Performance-Akzeptanzkriterium verletzt wird; siehe YAGNI §63).

#### 2.3 `DhlCatalogServiceController`
```text
final class DhlCatalogServiceController
{
    public function __construct(
        private readonly DhlAdditionalServiceRepository $serviceRepository,           // PROJ-1
        private readonly DhlProductServiceAssignmentRepository $assignmentRepository,
    ) {}

    public function show(string $code): View;
}
```
- View enthält Parameter-Schema-Tabelle (Feld / Typ / Required / Default / Constraint) — Mapping JSON-Schema → flache Liste übernimmt eine **Domain-Funktion** (PROJ-1: `DhlServiceParameterSchema::flatten()`), **nicht** der Controller.

#### 2.4 `DhlCatalogAuditController`
```text
final class DhlCatalogAuditController
{
    public function __construct(
        private readonly DhlCatalogAuditRepository $auditRepository,
        private readonly PaginatorLinkGeneratorInterface $linkGenerator,
    ) {}

    public function index(DhlCatalogAuditFilterRequest $request): View;
}
```
- Filter: `from`, `to` (ISO-Datum), `entity_type`, `action`, `actor` (Substring).
- Pagination 50/Seite via `PaginatedResult` + Index aus PROJ-1.

#### 2.5 `DhlCatalogSyncTriggerController`
```text
final class DhlCatalogSyncTriggerController
{
    public function __construct(
        private readonly DispatchDhlCatalogSyncCommand $dispatchCommand, // PROJ-2 Application Service
        private readonly DhlCatalogSyncStatusRepository $syncStatusRepository,
        private readonly Redirector $redirector,
    ) {}

    public function trigger(Request $request): RedirectResponse;  // POST
    public function status(): JsonResponse;                       // GET, Polling
}
```
- `trigger()`: ruft `$dispatchCommand->dispatchManual(actor: 'user:' . $request->user()->id)` auf — die Lock-/Overlap-Prüfung (`withoutOverlapping` aus PROJ-2) entscheidet, ob ein Sync angestoßen oder „läuft bereits" gemeldet wird. Controller mappt nur Rückgabe auf Flash-Message (`success` vs. `info`).
- `status()` gibt `{ state, started_at, last_finished_at, last_error, diff_counters }` — alles aus dem VO, keine eigene Logik.

---

### 3. Views (Blade — konsistent mit `admin/settings/dhl_freight/index.blade.php`)

Top-level Layout `@extends('layouts.admin', [...])`. Alle Strings über Translation-Keys (siehe §6).

```text
resources/views/admin/settings/dhl_catalog/
├── index.blade.php                  ← Übersicht
│   ├── x-ui.section-header
│   ├── x-dhl-catalog.sync-status-banner  (neue dedizierte Komponente)
│   ├── x-dhl-catalog.filter-bar          (neue Komponente; Form mit Routing/Status/Quelle/Suche)
│   ├── <table>  Produkte (caption, scope=col, Status-Badge mit Icon+Text)
│   └── x-ui.pagination
│
├── products/show.blade.php          ← Produkt-Detail
│   ├── x-ui.section-header (Code + Name + Status-Badge)
│   ├── Header-Karte (Beschreibung, Validität, deprecated_at, replaced_by-Link, Limits)
│   └── x-ui.tabs (3 Tabs)
│       ├── Tab "Routings"   → From-/To-Country-Matrix
│       ├── Tab "Services"   → gruppierte Assignment-Tabelle
│       └── Tab "Audit"      → Tabelle (50 Einträge), Diff-Modal
│
├── services/show.blade.php          ← Service-Detail
│   ├── Header-Karte
│   ├── Parameter-Schema-Tabelle
│   └── Tabelle "Verwendet in Produkten"
│
├── audit/index.blade.php            ← Audit-Log
│   ├── x-dhl-catalog.audit-filter-bar
│   ├── <table>
│   └── x-dhl-catalog.diff-modal     (zeigt before/after side-by-side, JSON pretty-printed)
│
└── components/                      ← Blade-Component-Klassen (anonyme x-Tags reichen, KISS)
    ├── sync-status-banner.blade.php
    ├── filter-bar.blade.php
    ├── audit-filter-bar.blade.php
    └── diff-modal.blade.php
```

**Diff-Modal:** Frontend nutzt vorhandenes Modal-Pattern (siehe `x-ui.tabs` bzw. `<dialog>` falls bereits etabliert). Modal-Trigger ist ein Button mit `aria-haspopup="dialog"` und `aria-controls`; Modal selbst ist `<dialog>`-Element mit Fokus-Trap (KISS: natives `<dialog>`, **kein** JS-Modal-Framework).

**Polling-JS:** Mini-Modul in `resources/js/admin/dhl-catalog/sync-poll.js` — fetch auf `route('admin.settings.dhl.catalog.sync.status')` alle 5 s; aktualisiert nur das Banner-DOM. Wird nur eingebunden, wenn `@can('dhl-catalog.view')` UND aktiver Sync. Keine Inline-Scripts (Wiederverwendung Convention aus `DhlFreightSettings`).

---

### 4. Permissions

**Neue Permissions:**
| Permission | Beschreibung |
|---|---|
| `dhl-catalog.view` | Lesezugriff auf Übersicht + Produkt-/Service-Detail |
| `dhl-catalog.sync` | Manueller Sync-Trigger |
| `dhl-catalog.audit.read` | Zugriff auf Audit-Log-Ansicht |

**Registrierung:**
- **Migration** (eigene, idempotente Migration, kein `seed:run`-Zwang): `database/migrations/2026_05_XX_add_dhl_catalog_permissions.php` legt die drei Permissions via Spatie-Permission-Repository an (`Permission::findOrCreate`). Konsistent mit bestehender Vorgehensweise.
- **Rollen-Default:** Migration weist alle drei Permissions der Rolle `admin` zu. Rolle `fulfillment-operator` erhält initial nur `dhl-catalog.view` (kein Sync, kein Audit). Andere Rollen bleiben unverändert.
- **Dokumentation:** Eintrag in `docs/SYSTEM_PERMISSION_MATRIX.md` mit Tabellen-Zeile pro Permission + Rollenzuordnung.

---

### 5. CLI-Commands (`app/Console/Commands/Fulfillment/DhlCatalog/`)

Drei dünne Console-Commands. Sie validieren Eingabe und delegieren an einen **Application-Service** `DhlCatalogSuccessorMappingService` (lebt in `app/Application/Fulfillment/Integrations/Dhl/Catalog/` — wird in PROJ-1 oder hier neu eingeführt). Der Service kapselt die Invarianten — Commands enthalten **keine** Fachlogik (§7, §26).

**Application-Service-Methoden:**
```text
DhlCatalogSuccessorMappingService::setSuccessor(string $oldCode, string $newCode, Actor $actor): void
    throws DhlCatalogProductNotFoundException
    throws DhlCatalogProductNotDeprecatedException     (oldCode muss deprecated sein)
    throws DhlCatalogSuccessorNotActiveException       (newCode muss aktiv sein)
    throws DhlCatalogCircularSuccessorChainException   (zyklus-Erkennung A→B→C→A)

DhlCatalogSuccessorMappingService::unsetSuccessor(string $oldCode, Actor $actor): void

DhlCatalogSuccessorMappingService::listDeprecated(): iterable<DhlCatalogProductSummary>
```

**Zyklus-Erkennung:** Service traversiert `replaced_by_code`-Kette ausgehend von `newCode`. Trifft sie auf `oldCode` → Exception. Max-Tiefe 100 als Safety-Net.

**Commands:**

#### 5.1 `php artisan dhl:catalog:set-successor`
```text
Signature: dhl:catalog:set-successor {oldCode} {newCode} {--actor=}
```
- `--actor` ist Pflicht (E-Mail oder `system:<name>`); fehlt sie → Command bricht mit Exit-Code 2 ab und Hilfetext.
- Bei Validation-Exception: ausführliche Fehlermeldung, Exit-Code 1, **kein** Stacktrace im Standard-Output.
- Erfolgsfall: schreibt Audit-Eintrag via Service (Audit-Logik liegt im Repository aus PROJ-1) und gibt Tabelle (oldCode, newCode, old.deprecated_at, new.valid_from) aus.

#### 5.2 `php artisan dhl:catalog:unset-successor`
```text
Signature: dhl:catalog:unset-successor {oldCode} {--actor=}
```

#### 5.3 `php artisan dhl:catalog:list-deprecated`
```text
Signature: dhl:catalog:list-deprecated {--with-successor} {--without-successor} {--format=table : table|json}
```
- Spalten: code, name, deprecated_at, replaced_by_code, replaced_by_name.

---

### 6. i18n (`lang/de.json` — neu anzulegen)

Aktuell existiert kein zentrales Lang-Verzeichnis; die bestehende `DhlFreight`-View nutzt deutsche Inline-Strings. Mit diesem Feature wird **`lang/de.json`** als JSON-Translation-File angelegt (Laravel-Standard, einfachster Weg, KISS) und über `__('key')` referenziert.

**Key-Struktur (flache JSON-Keys mit Punkt-Notation, deutsche Werte als Default):**

```text
lang/de.json
{
  "dhl_catalog.title": "DHL Produktkatalog",
  "dhl_catalog.subtitle": "Read-Only-Ansicht des synchronisierten DHL-Freight-Katalogs.",

  "dhl_catalog.sync.banner.idle": "Letzter Sync :time — erfolgreich.",
  "dhl_catalog.sync.banner.running": "Sync läuft seit :seconds Sekunden …",
  "dhl_catalog.sync.banner.failed": "Letzter Sync :time fehlgeschlagen: :error",
  "dhl_catalog.sync.banner.empty": "Katalog ist noch leer. Bitte ersten Sync ausführen.",
  "dhl_catalog.sync.trigger.button": "Sync jetzt starten",
  "dhl_catalog.sync.trigger.confirm": "Sync wirklich manuell starten?",
  "dhl_catalog.sync.trigger.dispatched": "Sync wurde gestartet.",
  "dhl_catalog.sync.trigger.already_running": "Sync läuft bereits.",

  "dhl_catalog.filter.routing": "Routing",
  "dhl_catalog.filter.status": "Status",
  "dhl_catalog.filter.source": "Quelle",
  "dhl_catalog.filter.search": "Suche (Code oder Name)",

  "dhl_catalog.status.active": "Aktiv",
  "dhl_catalog.status.deprecated": "Veraltet",

  "dhl_catalog.source.seed": "Seed",
  "dhl_catalog.source.api": "API",
  "dhl_catalog.source.manual": "Manuell",

  "dhl_catalog.products.column.code": "Code",
  "dhl_catalog.products.column.name": "Name",
  "dhl_catalog.products.column.routings": "Routings",
  "dhl_catalog.products.column.services_count": "Services",
  "dhl_catalog.products.column.last_synced_at": "Letzter Sync",

  "dhl_catalog.product.header.validity": "Gültig",
  "dhl_catalog.product.header.deprecated_at": "Deprecated am",
  "dhl_catalog.product.header.replaced_by": "Ersetzt durch",
  "dhl_catalog.product.tabs.routings": "Routings",
  "dhl_catalog.product.tabs.services": "Services",
  "dhl_catalog.product.tabs.audit": "Audit",

  "dhl_catalog.service.parameter.field": "Feld",
  "dhl_catalog.service.parameter.type": "Typ",
  "dhl_catalog.service.parameter.required": "Pflicht",
  "dhl_catalog.service.parameter.default": "Default",
  "dhl_catalog.service.parameter.constraint": "Constraint",

  "dhl_catalog.audit.column.timestamp": "Zeitpunkt",
  "dhl_catalog.audit.column.entity_type": "Entity-Typ",
  "dhl_catalog.audit.column.entity_key": "Entity-Key",
  "dhl_catalog.audit.column.action": "Aktion",
  "dhl_catalog.audit.column.actor": "Akteur",
  "dhl_catalog.audit.diff.show": "Diff anzeigen",
  "dhl_catalog.audit.diff.before": "Vorher",
  "dhl_catalog.audit.diff.after": "Nachher",
  "dhl_catalog.audit.empty": "Keine Audit-Einträge im gewählten Zeitraum.",

  "dhl_catalog.requirement.allowed": "erlaubt",
  "dhl_catalog.requirement.required": "Pflicht",
  "dhl_catalog.requirement.forbidden": "verboten"
}
```

**Erweiterung:**
- `lang/en.json` als optionale Folgemigration — out of scope dieses Features (YAGNI), wird angelegt sobald englische UI gefordert.
- Breadcrumb-Labels über denselben Key-Pool, via `BreadcrumbBuilder::label(__('dhl_catalog.title'))`.

---

### 7. Test-Strategie

Gemäß §68 (Tests folgen der Architektur) und §58 (Frontend-Tests). Tests liegen unter `tests/Feature/Http/Admin/DhlCatalog/`, `tests/Feature/Console/DhlCatalog/` und `tests/Browser/DhlCatalog/` (Pest-basiert, konsistent mit bestehender Test-Suite).

**Feature-Tests pro Route:**
| Test | Prüft |
|---|---|
| `DhlCatalogIndexControllerTest::guests_are_redirected` | Auth-Middleware |
| `DhlCatalogIndexControllerTest::user_without_view_permission_gets_403` | `can:dhl-catalog.view` |
| `DhlCatalogIndexControllerTest::renders_paginated_products` | Repository wird mit korrektem Filter aufgerufen, View bekommt PaginatedResult |
| `DhlCatalogIndexControllerTest::applies_routing_status_source_filters` | Filter-Mapping |
| `DhlCatalogIndexControllerTest::shows_empty_state_when_no_products` | Edge Case "leerer Katalog" |
| `DhlCatalogProductControllerTest::returns_404_for_unknown_code` | Repo wirft, 404 |
| `DhlCatalogProductControllerTest::renders_assignments_grouped_by_category` | Tab "Services" |
| `DhlCatalogServiceControllerTest::renders_parameter_schema_table` | Schema-Flattening korrekt aufgerufen |
| `DhlCatalogAuditControllerTest::requires_audit_permission` | 403 ohne `dhl-catalog.audit.read` |
| `DhlCatalogAuditControllerTest::filters_by_entity_and_timerange` | Filter-Validation + Mapping |
| `DhlCatalogSyncTriggerControllerTest::requires_sync_permission` | 403 |
| `DhlCatalogSyncTriggerControllerTest::dispatches_when_no_sync_running` | Application-Command wird mit Actor aufgerufen |
| `DhlCatalogSyncTriggerControllerTest::reports_already_running` | Lock-Konflikt-Pfad |
| `DhlCatalogSyncTriggerControllerTest::status_endpoint_returns_state_json` | JSON-Struktur |
| `DhlCatalogSyncTriggerControllerTest::trigger_requires_csrf` | POST ohne Token → 419 |

**Console-Tests** (`Tests\TestCase` + `artisan()`-Helper):
| Test | Prüft |
|---|---|
| `SetSuccessorCommandTest::fails_without_actor` | Exit-Code 2 |
| `SetSuccessorCommandTest::fails_when_old_code_not_deprecated` | Service-Exception → exit 1, Fehlertext |
| `SetSuccessorCommandTest::fails_when_new_code_not_active` | dito |
| `SetSuccessorCommandTest::fails_on_circular_chain` | A→B→C→A |
| `SetSuccessorCommandTest::sets_successor_on_happy_path` | Repo-Save + Audit-Eintrag geschrieben |
| `UnsetSuccessorCommandTest::clears_replaced_by` | |
| `ListDeprecatedCommandTest::lists_with_and_without_successor_flags` | Filter-Optionen |
| `ListDeprecatedCommandTest::supports_json_format` | |

**Browser-/Pest-Tests (Glücksfall, kritischer UI-Flow):**
- `dhl_catalog_admin_browses_catalog_and_triggers_sync`:
  - Login als Admin → öffnet `/admin/settings/dhl/katalog`
  - Banner zeigt grünen Sync-Status
  - Klick auf ersten Produkt-Code → Detail-Seite
  - Tab "Services" zeigt Assignments
  - Zurück zur Übersicht, "Sync jetzt starten" → Confirmation → Banner wechselt auf "läuft"
  - Polling-Endpoint liefert `state=running`
- `dhl_catalog_empty_state_shows_sync_button`:
  - Vor erstem Sync: Empty-State + prominenter Button

**Domain-/Application-Tests** (für den Successor-Service — gehört strenggenommen zu PROJ-1, wird hier mitgeliefert falls in PROJ-1 nicht enthalten):
- `DhlCatalogSuccessorMappingServiceTest`: Invarianten (deprecated, active, Zyklus), Audit-Schreibung, Idempotenz.

**Coverage-Anspruch:** Kritische Pfade (Permission, Filter, Trigger, Zyklus-Erkennung) 100 % — keine Snapshot-only-Tests.

---

### 8. Offene Punkte / Annahmen (zur User-Verifikation)

1. **Spatie-Permission** wird als Permission-System angenommen (basierend auf `can:`-Middleware-Pattern in `routes/web.php`). Falls anders: Permission-Migration entsprechend anpassen.
2. **Lang-Datei**: erstes Feature, das `lang/de.json` einführt. Falls die Konvention im Projekt anders ist (z. B. `lang/de/dhl_catalog.php` PHP-Array-Files), entsprechend umstellen — Key-Struktur bleibt.
3. **Lazy-Load Tab "Services"**: bewusst zunächst synchron implementiert (KISS/YAGNI). Trigger für Async-Refactor: Produkt mit > 200 Assignments + Render-Zeit > 500 ms.
4. **Diff-Modal**: nutzt natives `<dialog>` — falls das Projekt einen festen Modal-Wrapper (`x-ui.modal`) hat, diesen verwenden statt neu zu bauen (DRY-Check vor Implementierung pflicht).


---

```yaml
architect_handoff:
  task_id: t3
  goal_id: GOAL-2026-05-12T124024-dhlcat
  feature: PROJ-6
  status: design-complete
  date: 2026-05-12
  depends_on: [PROJ-1, PROJ-2]
  layers_touched:
    - presentation
    - application
    - infrastructure
  new_artifacts:
    routes:
      - admin.settings.dhl.catalog.index
      - admin.settings.dhl.catalog.products.show
      - admin.settings.dhl.catalog.services.show
      - admin.settings.dhl.catalog.audit.index
      - admin.settings.dhl.catalog.sync.trigger
      - admin.settings.dhl.catalog.sync.status
    controllers:
      - App\Http\Controllers\Admin\Settings\DhlCatalog\DhlCatalogIndexController
      - App\Http\Controllers\Admin\Settings\DhlCatalog\DhlCatalogProductController
      - App\Http\Controllers\Admin\Settings\DhlCatalog\DhlCatalogServiceController
      - App\Http\Controllers\Admin\Settings\DhlCatalog\DhlCatalogAuditController
      - App\Http\Controllers\Admin\Settings\DhlCatalog\DhlCatalogSyncTriggerController
    form_requests:
      - DhlCatalogIndexFilterRequest
      - DhlCatalogAuditFilterRequest
    application_services:
      - App\Application\Fulfillment\Integrations\Dhl\Catalog\DhlCatalogSuccessorMappingService
    console_commands:
      - dhl:catalog:set-successor
      - dhl:catalog:unset-successor
      - dhl:catalog:list-deprecated
    views:
      - resources/views/admin/settings/dhl_catalog/index.blade.php
      - resources/views/admin/settings/dhl_catalog/products/show.blade.php
      - resources/views/admin/settings/dhl_catalog/services/show.blade.php
      - resources/views/admin/settings/dhl_catalog/audit/index.blade.php
      - resources/views/admin/settings/dhl_catalog/components/sync-status-banner.blade.php
      - resources/views/admin/settings/dhl_catalog/components/filter-bar.blade.php
      - resources/views/admin/settings/dhl_catalog/components/audit-filter-bar.blade.php
      - resources/views/admin/settings/dhl_catalog/components/diff-modal.blade.php
    permissions:
      - dhl-catalog.view
      - dhl-catalog.sync
      - dhl-catalog.audit.read
    i18n:
      - lang/de.json (NEU, dhl_catalog.* Namespace)
    js:
      - resources/js/admin/dhl-catalog/sync-poll.js
  reuses_existing:
    - App\Support\UI\BreadcrumbBuilder
    - App\Domain\Shared\ValueObjects\Pagination\PaginatedResult
    - App\Domain\Shared\ValueObjects\Pagination\PaginatorLinkGeneratorInterface
    - resources/views/layouts/admin.blade.php
    - x-ui.section-header, x-forms.*
  open_questions:
    - permission-package-confirmation (Spatie vs. custom)
    - lang-file-convention (json vs. php-array)
    - existing-modal-wrapper-availability
  next_skill: backend
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
