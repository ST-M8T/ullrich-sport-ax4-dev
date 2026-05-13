# PROJ-2: DHL Catalog — Sync-Job & Alarmierung

## Status: Planned
**Created:** 2026-05-12
**Last Updated:** 2026-05-12

## Dependencies
- **Requires:** [PROJ-1](PROJ-1-dhl-catalog-domain-persistence.md) — Katalog-Domain & Persistence-Tabellen müssen existieren

## Kontext
Sobald das Datenmodell aus PROJ-1 steht, muss es mit echten DHL-Produkten und Additional Services gefüllt und konsistent aktuell gehalten werden. Quelle der Wahrheit ist die DHL-Freight-API (`/products`, `/products/{code}/additionalservices`). Da der Katalog laut Entscheidung vollautomatisch verwaltet wird, ist der Sync-Job das einzige Schreibwerkzeug — manuelles Editing existiert nicht.

Lifecycle: **Bootstrap** (einmaliger Initial-Pull als JSON-Fixture committed) → **Seeder** (Fixture → DB) → **Scheduler** (wöchentlich) → **Manueller Trigger** (Admin-Button bei Bedarf, siehe PROJ-6) → **Alarmierung** bei Fehler.

## User Stories
- Als **DevOps** möchte ich einmalig `php artisan dhl:catalog:bootstrap` ausführen, um die DHL-Sandbox-API gegen alle relevanten Routings abzufragen und das Ergebnis als versionierte JSON-Fixtures im Repo zu committen.
- Als **System** möchte ich wöchentlich (Sonntag 03:00) automatisch die aktuelle DHL-API abfragen und Veränderungen am Katalog persistieren, ohne dass ein Mensch eingreifen muss.
- Als **Admin** möchte ich bei einem Sync-Fehler unverzüglich per Mail, Dashboard-Banner und Sentry/Log benachrichtigt werden, damit ich den Drift zur Produktion einschätzen kann.
- Als **Fulfillment-Verantwortlicher** möchte ich sicher sein, dass bei einem Sync-Fehler der bisherige Stand erhalten bleibt — Buchungen müssen weiter funktionieren.
- Als **Compliance** möchte ich, dass jeder Sync-Lauf einen Audit-Eintrag pro Änderung mit `actor=system:dhl-sync` schreibt.

## Acceptance Criteria

### CLI-Commands
- [ ] **`php artisan dhl:catalog:bootstrap`**: Ruft DHL-API für alle in `config/dhl-catalog.php → default_countries` definierten From×To-Paare und alle Payer-Codes ab. Schreibt jedes API-Response als JSON-Datei nach `database/seeders/data/dhl/`:
  - `products.json`
  - `services.json` (vereinigt aus allen Produkt-Aufrufen, deduped via Code)
  - `assignments.json` (per Produkt × Routing × Payer)
- [ ] **`php artisan dhl:catalog:sync`**: Manueller Trigger, ruft den gleichen Application Service auf wie der Scheduler. Akzeptiert `--dry-run` (zeigt Diff ohne zu schreiben) und `--routing=DE-AT` (Restriktion).
- [ ] **`php artisan db:seed --class=DhlCatalogSeeder`**: Liest die JSON-Fixtures aus PROJ-1, schreibt in die drei Tabellen. Idempotent — bei wiederholtem Aufruf identischer Endzustand.
- [ ] **Bootstrap-Command** legt ein Manifest (`database/seeders/data/dhl/_manifest.json`) mit `dhl_api_version`, `fetched_at`, `from_countries[]`, `to_countries[]`, `count_products`, `count_services` an — committed im Repo zur Nachverfolgung.

### Application Service `SynchroniseDhlCatalogService`
- [ ] Liegt in `app/Application/Fulfillment/Integrations/Dhl/Catalog/`.
- [ ] Public Method `execute(SynchroniseDhlCatalogCommand $command): SynchroniseDhlCatalogResult`.
- [ ] Command-Objekt kapselt `routingFilter`, `dryRun`, `actor` (default `system:dhl-sync`).
- [ ] Result-Objekt enthält: `productsAdded`, `productsUpdated`, `productsDeprecated`, `servicesAdded`, `servicesUpdated`, `servicesDeprecated`, `assignmentsChanged`, `errors[]`, `durationMs`.
- [ ] Alle Schreibvorgänge in **einer DB-Transaktion** pro Entity-Typ (Products, Services, Assignments — drei separate Transaktionen, damit ein Fehler in Assignments den Service-Sync nicht rollbackt).
- [ ] Nutzt ausschließlich Repository-Interfaces aus PROJ-1, **nie** direkten DB-Zugriff.
- [ ] Ruft den bestehenden [DhlProductCatalogService.php](app/Application/Fulfillment/Integrations/Dhl/Services/DhlProductCatalogService.php) (Gateway-Wrapper) — bestehender Service darf erweitert werden, aber **keine** Duplikat-Implementierung der API-Calls.

### Diff-Logik
- [ ] Beim Sync wird pro Eintrag verglichen, ob er **neu** ist (Insert + Audit `created`), **geändert** (Update + Audit `updated` mit Diff), **unverändert** (no-op) oder **verschwunden** (Soft-Deprecate + Audit `deprecated`, `deprecated_at=now()`).
- [ ] Wiedergekehrte Codes (vorher `deprecated_at`, jetzt wieder in API) → `deprecated_at=NULL` + Audit `restored`.
- [ ] Diff-JSON im Audit-Log enthält nur die geänderten Felder (kein vollständiger Snapshot).

### Scheduler
- [ ] Eintrag in `app/Console/Kernel.php` (oder `routes/console.php`): `Schedule::command('dhl:catalog:sync')->weeklyOn(0, '03:00')->withoutOverlapping();` (Sonntag 03:00, Lock 1h).
- [ ] Konfigurierbar via `config/dhl-catalog.php → schedule_cron` (default `'0 3 * * 0'`, leerer String deaktiviert).
- [ ] Scheduled-Run loggt Start + Ende mit Result-Metriken über `Log::channel('dhl-catalog')`.

### Alarmierung bei Sync-Fehler
- [ ] **Mail-Alarm**: Bei Result mit `errors[] != []` ODER ungefangener Exception wird `DhlCatalogSyncFailedMail` an `config('dhl-catalog.alert_recipients')` (Array von E-Mail-Adressen aus `.env`) versendet. Inhalt: Fehlertyp, Stacktrace (gekürzt), letzter erfolgreicher Sync-Zeitstempel, Routing-Filter.
- [ ] **Dashboard-Banner**: Tabelle `dhl_catalog_sync_status` mit Zeile `id=current` (`last_attempt_at`, `last_success_at`, `last_error`, `consecutive_failures`). Admin-Dashboard ([PROJ-6](PROJ-6-dhl-catalog-admin-inspection.md)) liest diese Tabelle und zeigt rotes Banner, wenn `last_success_at < now() - 14 Tage` ODER `consecutive_failures >= 2`.
- [ ] **Sentry/Log**: Exception wird via `report($e)` an Sentry weitergereicht (falls Sentry konfiguriert) und in dedicated Log-Channel `dhl-catalog` mit Level ERROR geschrieben.
- [ ] Alarmierung ist idempotent — kein Mail-Spam: Mail nur beim **ersten** Fehlversuch nach erfolgreichem Sync, danach erst wieder nach Recovery+Neufehler.

### Audit-Log
- [ ] Jede tatsächliche Änderung (kein no-op) erzeugt einen Audit-Eintrag in `dhl_catalog_audit_log` (Tabelle aus PROJ-1) mit `actor='system:dhl-sync'` und `diff` JSON.
- [ ] Bei `--dry-run` werden **keine** Audit-Einträge geschrieben.
- [ ] Sync-Lauf ohne Änderungen erzeugt keinen Audit-Spam, nur einen Log-Eintrag.

## Edge Cases
- **DHL-API down**: HTTP-Timeout oder 5xx → Retry mit 3 Versuchen (1min/5min/15min Backoff). Wenn alle scheitern → Result mit `errors=[apiUnavailable]`, kein DB-Schreibvorgang, Alarm wird ausgelöst.
- **DHL-API-Token abgelaufen**: 401-Response → ohne Retry sofort als `errors=[authFailed]` melden, keinen normalen Retry-Loop. Admin muss Token erneuern.
- **DHL-API-Schema-Drift**: Unbekanntes Feld in Response → toleriert (ignoriert). Pflichtfeld fehlt → Eintrag wird übersprungen und in `errors[]` mit Code+Routing aufgelistet. Andere Einträge laufen normal durch.
- **Partial Failure**: Produkt-Sync erfolgreich, Service-Sync schlägt fehl → Result hat Mischbild, `consecutive_failures++`, Alarm wird ausgelöst. Erfolgreiche Teile sind persistiert.
- **Gleichzeitiger manueller Trigger und Scheduler**: `withoutOverlapping()` schützt Scheduler-Aufruf. Manueller CLI-Aufruf während Scheduler-Lock → CLI bricht mit Hinweis ab.
- **Empty API-Response**: API liefert leeres Array `[]` für ein Routing → ALLE bisher in diesem Routing existierenden Assignments würden deprecated. Schutz: Wenn API für Routing weniger als 10% der bisherigen Einträge zurückliefert → Sicherheits-Stopp mit `errors=[suspiciousShrinkage]`, kein Schreibvorgang, Alarm.
- **Bootstrap ohne Sandbox-Zugang**: Command bricht früh mit klarer Fehlermeldung und Hinweis auf `.env`-Variablen `DHL_API_BASE_URL`, `DHL_API_TOKEN`.
- **JSON-Fixture und API divergieren**: Sync hat Vorrang, Fixture ist nur Initialisierung. Nach erstem erfolgreichen Sync ist `source` der betroffenen Einträge `api` (überschreibt `seed`).
- **Replaced-by-Mapping**: Wird NICHT automatisch durch Sync gesetzt (manuelle Pflege via Migration/CLI, siehe PROJ-6). Sync setzt nur `deprecated_at`.
- **Sehr großer Diff (>1000 Einträge)**: Result wird trotzdem komplett geschrieben, aber Audit-Diff pro Eintrag separat — keine Bulk-Diffs. Log-Eintrag mit Warnung.

## Technical Requirements
- **Schichtung**: Application Service in `Application/`, kein Domain-Code. Gateway-Calls bleiben hinter `DhlFreightGateway`-Interface (existiert bereits).
- **Idempotenz (Engineering-Handbuch §24)**: Sync ist bei wiederholtem Aufruf mit identischer API-Response no-op. Audit-Log dedupliziert nicht — gewollt für Forensik.
- **Sicherheit (§19, §32)**: DHL-API-Credentials ausschließlich aus `.env` über `config/services.php` / vorhandene Konfiguration. Keine Tokens in Logs, kein Token im Audit-Diff.
- **Logging (§30)**: Strukturiertes Logging über Channel `dhl-catalog` mit JSON-Format. Keine PII (Empfänger-Adressen etc.) — der Katalog enthält ohnehin keine.
- **Beobachtbarkeit**: `dhl_catalog_sync_status` ist die einzige Quelle für „ist der Sync gesund". Kein Polling von Logs nötig.
- **Performance**: Voller Sync (alle Routings, ~30 Länder × 50 Produkte × ~20 Services = ~30k Assignments) muss in <10 Minuten durchlaufen. Pro API-Call max. 3s Timeout, Parallelisierung über Routing-Paare möglich (max. 5 gleichzeitig, um DHL-Rate-Limits zu respektieren).
- **Testing**: Application Service mit gemocktem `DhlFreightGateway` testen. Fixture-basierte Integration-Tests gegen echte DB. CLI-Commands über Feature-Tests.
- **Reversibilität**: Sync kann via `--dry-run` simuliert werden. Nach echtem Schreibvorgang gibt es kein Auto-Rollback — aber das Audit-Log erlaubt manuelle Rekonstruktion.

## Out of Scope
- Nutzung des Katalogs in Mapping-Code (PROJ-3)
- UI für Sync-Trigger und Status-Anzeige (PROJ-6)
- Automatisches Setzen von `replaced_by_code` (manuelle Pflege via CLI/Migration — Tooling-Detail in PROJ-6)
- Audit-Log-Retention-Cleanup (separates späteres Housekeeping-Feature)

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

> Diese Sektion beschreibt das **Was**, nicht das **Wie** im Detail. Konkrete Implementierungen entstehen in den Backend-Tasks. Es werden bewusst nur Klassen-Verantwortlichkeiten, Signaturen und Datenflüsse fixiert.

### 1) Application Service `SynchroniseDhlCatalogService`

**Ort:** `app/Application/Fulfillment/Integrations/Dhl/Catalog/SynchroniseDhlCatalogService.php`
(neues Sub-Namespace `Catalog/` neben dem bestehenden `Services/` — Trennung, weil dies ein orchestrierender Use Case ist, kein Gateway-Wrapper.)

#### Command-/Result-DTOs

`SynchroniseDhlCatalogCommand` (immutable, readonly):
- `?string routingFilter` — z. B. `"DE-AT"`, `null` = alle Routings aus `config('dhl-catalog.default_countries')`
- `bool dryRun` — default `false`
- `string actor` — default `"system:dhl-sync"`
- `?array payerCodesOverride` — optional, sonst aus Config

`SynchroniseDhlCatalogResult` (immutable, readonly):
- `int productsAdded`, `int productsUpdated`, `int productsDeprecated`, `int productsRestored`
- `int servicesAdded`, `int servicesUpdated`, `int servicesDeprecated`, `int servicesRestored`
- `int assignmentsAdded`, `int assignmentsUpdated`, `int assignmentsDeprecated`
- `array errors` — Liste typisierter Fehler `{code, routing?, entityCode?, message}` mit Codes `apiUnavailable | authFailed | schemaInvalid | suspiciousShrinkage | partial`
- `int durationMs`
- `bool dryRun`
- `Carbon startedAt`, `Carbon finishedAt`

#### Public API

```
SynchroniseDhlCatalogService::execute(SynchroniseDhlCatalogCommand $cmd): SynchroniseDhlCatalogResult
```

Keine weiteren Public-Methoden. Interne Helper-Methoden sind privat und entlang der drei Sync-Phasen geschnitten (`syncProducts`, `syncServices`, `syncAssignments`).

#### Drei separate Transaktionen

Begründung: Engineering-Handbuch §17 — Application entscheidet über Atomarität. Wir wollen Teil-Erfolge persistieren, damit ein Schema-Fehler in Services nicht den erfolgreichen Produkt-Sync zurückrollt.

Reihenfolge & Grenzen:
1. **TX 1 — Products**: alle API-Responses geladen → Diff berechnet → ein `DB::transaction()` schreibt Inserts/Updates/Deprecates + Audit-Einträge. Bei Exception: Rollback nur dieser TX, Phase 2 + 3 werden übersprungen, `errors[]` markiert.
2. **TX 2 — Services**: nur wenn TX 1 erfolgreich. Gleiches Muster.
3. **TX 3 — Assignments** (pro Routing × Payer): nur wenn TX 1 + TX 2 erfolgreich. Pro Routing-Paar eine eigene Transaktion, damit ein fehlerhaftes Routing andere nicht blockiert.

Jede Transaktion ruft am Ende `dhl_catalog_sync_status`-Update auf (außerhalb der TX, eigene kurze TX).

#### Diff-Algorithmus (Pseudocode)

```
function syncEntity(apiList, repository, entityType):
    existing      = repository.allIncludingDeprecated()              // Map<code, Row>
    apiCodes      = set(apiList.code)
    existingCodes = set(existing.code)

    // Safety Net: §70 Idempotenz + Anti-Massendelete
    if entityType in ('product','service') and existing.activeCount > 0:
        shrinkRatio = 1 - (count(apiCodes ∩ existingActiveCodes) / existingActiveCount)
        if shrinkRatio > 0.10:
            result.errors += { code: 'suspiciousShrinkage', entityType, shrinkRatio }
            ABORT this phase (kein Schreibvorgang, keine TX-Öffnung)

    DB.transaction:
        for code in apiCodes:
            apiItem = apiList[code]
            existingItem = existing[code] ?? null
            if existingItem == null:
                repository.insert(apiItem)
                audit('created', diff=apiItem.toArray())
            elif existingItem.deprecated_at != null:
                repository.restore(code, apiItem)
                audit('restored', diff=fieldDiff(existingItem, apiItem))
            elif fieldDiff(existingItem, apiItem) != []:
                repository.update(code, apiItem)
                audit('updated', diff=fieldDiff(existingItem, apiItem))
            // else: no-op, kein Audit

        for code in (existingCodes - apiCodes) where existing[code].deprecated_at == null:
            repository.softDeprecate(code, now())
            audit('deprecated', diff={ deprecated_at: now() })

if cmd.dryRun:
    // alle Writes laufen im transaction(); am Ende rollBack() statt commit()
    DB.rollBack()
    no audit entries persisted
```

`fieldDiff` vergleicht ausschließlich fachliche Felder (kein `updated_at`, kein `source`-Wechsel von `seed→api` allein). Beim ersten API-Schreibvorgang wird `source` auf `api` gesetzt — das zählt **nicht** als „updated" für das Audit, sondern als Flag-Migration (separater Log-Eintrag, kein Audit-Spam).

#### Suspicious-Shrinkage-Schutz

- Schwellwert konstant `0.10` in `config/dhl-catalog.php → suspicious_shrinkage_threshold` (überschreibbar).
- Greift nur, wenn bereits aktive Einträge existieren (Erstbefüllung über Seeder hat 0 Vergleichsbasis → Schutz inaktiv).
- Bei Auslösung: Phase wird komplett übersprungen, kein partieller Schreibvorgang. Alarm-Pipeline wird getriggert.

---

### 2) Gateway-Erweiterung `DhlFreightGateway`

**Ort Interface:** `app/Domain/Integrations/Contracts/DhlFreightGateway.php` (bestehend)
**Ort Impl:** `app/Infrastructure/Integrations/Dhl/DhlFreightGatewayImpl.php` (bestehend)

Bestehend (relevant): `listProducts(array $filters)`, `listAdditionalServices(string $productId, array $filters)`.

**Ergänzungen** (keine Duplikat-Implementierung — siehe `DhlProductCatalogService` als Wrapper):

```
/**
 * Liefert alle Produkte für ein konkretes Routing inkl. Payer.
 * Aggregiert ggf. Pagination-Seiten und gibt Rohstruktur der API zurück.
 *
 * @return array{products: array<int,array>, meta: array{routing:string,payer:string,fetched_at:string}}
 */
public function listProductsForRouting(
    string $fromCountry,
    string $toCountry,
    string $payerCode,
    array  $extraFilters = []
): array;

/**
 * Liefert alle Additional Services für ein Produkt + Routing kombiniert,
 * inkl. Routing-Metadaten zur Speicherung der Assignment-Verknüpfung.
 *
 * @return array{services: array<int,array>, meta: array{product_code:string,routing:string,payer:string}}
 */
public function listServicesForProductRouting(
    string $productCode,
    string $fromCountry,
    string $toCountry,
    string $payerCode
): array;
```

Beide Methoden delegieren intern an die bestehenden HTTP-Calls. Der bestehende `DhlProductCatalogService` (Application-Wrapper) erhält dafür Convenience-Methoden mit identischen Signaturen → `SynchroniseDhlCatalogService` ruft nur den Wrapper auf, nicht den Gateway direkt.

**Token-/Logging-Hygiene (§30):** Authorization-Header bleiben ausschließlich im Gateway-Impl, werden nicht in Argumenten propagiert und tauchen weder in DTOs noch in Logs auf. Bestehende Auth-Logik (`DhlAuthenticationGateway`) wird wiederverwendet — keine Duplizierung.

---

### 3) CLI-Commands & Seeder

#### `dhl:catalog:bootstrap`
- **Ort:** `app/Console/Commands/Dhl/Catalog/BootstrapDhlCatalogCommand.php`
- **Verantwortung:** ruft Sandbox-API für **alle** Routing×Payer-Kombinationen aus `config('dhl-catalog.default_countries')` ab, deduped, schreibt Fixtures.
- **Zielordner:** `database/seeders/data/dhl/`
- **Optionen:** `--from=DE` `--to=AT,FR,IT` `--payer=SENDER,RECEIVER` (alle optional, default = Config)
- **Schreibt:**
  - `products.json` (deduped via `code`)
  - `services.json` (deduped via `code`)
  - `assignments.json` (Liste von `{product_code, service_code, from_country, to_country, payer_code}`)
  - `_manifest.json`
- **Pflicht-Vorab-Prüfung:** `DHL_API_BASE_URL` + `DHL_API_TOKEN` gesetzt? Sonst Abbruch mit klarer Meldung (Edge Case AC).
- **Idempotent:** überschreibt Fixtures atomar (Temp-File + rename).

#### `dhl:catalog:sync`
- **Ort:** `app/Console/Commands/Dhl/Catalog/SyncDhlCatalogCommand.php`
- **Verantwortung:** dünner Adapter — instanziiert `SynchroniseDhlCatalogCommand`, ruft Service, formatiert Result als Tabelle.
- **Optionen:**
  - `--dry-run` — keine Writes, kein Audit, Tabelle mit „würde passieren"
  - `--routing=DE-AT` — Restriktion auf ein Routing-Paar
  - `--payer=SENDER` — Restriktion auf einen Payer
- **Lock-Check:** prüft `Cache::lock('dhl-catalog-sync', 3600)` — bei aktivem Scheduler-Lock Abbruch mit Hinweis (Edge Case AC).
- **Exit-Code:** 0 bei `errors=[]`, 1 sonst.

#### `DhlCatalogSeeder`
- **Ort:** `database/seeders/DhlCatalogSeeder.php`
- **Liest:** `database/seeders/data/dhl/*.json`
- **Schreibt:** in `dhl_catalog_products`, `dhl_catalog_additional_services`, `dhl_catalog_assignments` (aus PROJ-1)
- **Idempotenz:** `upsert` über fachlichen Schlüssel (`code` bzw. Composite-Key bei Assignments). Setzt `source='seed'` **nur**, wenn der Eintrag noch nicht existiert oder bestehender `source='seed'` ist. Existiert er bereits mit `source='api'`, wird er **nicht** überschrieben (Sync hat Vorrang, siehe Edge Case AC).
- Schreibt **keine** Audit-Einträge (Initial-Befüllung ist kein fachliches Ereignis).

#### Fixture-Format

`products.json`:
```json
[
  {
    "code": "DLG",
    "name": "DHL Logistics Germany",
    "description": "...",
    "incoterms_supported": ["DAP","DDP"],
    "service_levels": ["DOMESTIC","INTERNATIONAL"],
    "raw": { "...": "originaler DHL-Response-Block" }
  }
]
```

`services.json`:
```json
[
  {
    "code": "Z01",
    "name": "Cash on Delivery",
    "category": "PAYMENT",
    "requires_value": true,
    "applicable_payers": ["SENDER","RECEIVER"],
    "raw": { "...": "..." }
  }
]
```

`assignments.json`:
```json
[
  {
    "product_code": "DLG",
    "service_code": "Z01",
    "from_country": "DE",
    "to_country": "AT",
    "payer_code": "SENDER",
    "constraints": { "max_value_eur": 5000 }
  }
]
```

`_manifest.json`:
```json
{
  "dhl_api_version": "v2",
  "dhl_api_base_url_host": "api-sandbox.dhl.com",
  "fetched_at": "2026-05-12T10:24:00Z",
  "from_countries": ["DE"],
  "to_countries": ["AT","FR","IT","NL","BE","PL","CH"],
  "payer_codes": ["SENDER","RECEIVER"],
  "count_products": 18,
  "count_services": 27,
  "count_assignments": 412,
  "generated_by": "dhl:catalog:bootstrap",
  "generator_version": "PROJ-2"
}
```

Keine Tokens, keine PII im Manifest.

---

### 4) Scheduler

**Ort:** `routes/console.php` (Laravel-11-Konvention im Repo prüfen — alternativ `app/Console/Kernel.php` falls noch klassisch).

```
Schedule::command('dhl:catalog:sync')
    ->cron(config('dhl-catalog.schedule_cron', '0 3 * * 0'))
    ->withoutOverlapping(60)            // 60 Min Lock
    ->onOneServer()                      // Multi-Worker-Schutz
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/dhl-catalog-sync.log'))
    ->when(fn () => config('dhl-catalog.schedule_enabled', true));
```

**`config/dhl-catalog.php`** (neu):
```
return [
    'schedule_enabled'              => env('DHL_CATALOG_SCHEDULE_ENABLED', true),
    'schedule_cron'                 => env('DHL_CATALOG_SCHEDULE_CRON', '0 3 * * 0'),
    'default_countries'             => [
        'from' => explode(',', env('DHL_CATALOG_FROM', 'DE')),
        'to'   => explode(',', env('DHL_CATALOG_TO', 'AT,FR,IT,NL,BE,PL,CH,DK')),
    ],
    'payer_codes'                   => ['SENDER','RECEIVER'],
    'suspicious_shrinkage_threshold'=> env('DHL_CATALOG_SHRINK_THRESHOLD', 0.10),
    'alert_recipients'              => array_filter(explode(',', env('DHL_CATALOG_ALERT_RECIPIENTS', ''))),
    'http_timeout_seconds'          => 3,
    'parallel_routings'             => 5,
];
```

---

### 5) Status-Tabelle `dhl_catalog_sync_status`

**Migration:** `database/migrations/YYYY_MM_DD_create_dhl_catalog_sync_status_table.php`

Schema (Single-Row-Strategy via PK `id`):
- `id` STRING PRIMARY KEY — immer Literal `'current'` (nur eine Zeile erlaubt; eine optionale `CHECK (id = 'current')`-Constraint dokumentiert das)
- `last_attempt_at` TIMESTAMP NULL
- `last_success_at` TIMESTAMP NULL
- `last_failure_at` TIMESTAMP NULL
- `last_result_summary` JSON NULL — geserialisiertes `SynchroniseDhlCatalogResult` (ohne `errors[]`-Details die Tokens enthalten könnten)
- `last_error_code` STRING(64) NULL
- `last_error_message` TEXT NULL
- `consecutive_failures` UNSIGNED INTEGER NOT NULL DEFAULT 0
- `last_mail_sent_at` TIMESTAMP NULL — Idempotenz-Anker für Alarm-Mail
- `mail_sent_for_failure_streak` BOOLEAN NOT NULL DEFAULT FALSE
- `updated_at` TIMESTAMP

**Single-Row-Strategy:** Repository (`DhlCatalogSyncStatusRepository`, Interface in `Domain/Fulfillment/Catalog/`, Impl in `Infrastructure/Persistence/`) exponiert nur `get(): Status` und `update(callable $mutator): void`. Kein `create`, kein `delete`. Initialer Row wird vom Seeder oder Migration mit `id='current'` angelegt.

---

### 6) Alarmierung

#### Mail-Klasse `DhlCatalogSyncFailedMail`
- **Ort:** `app/Mail/Fulfillment/DhlCatalogSyncFailedMail.php`
- **Empfänger:** `config('dhl-catalog.alert_recipients')` (Array)
- **Subject:** `[AX4] DHL-Katalog-Sync fehlgeschlagen — {error_code}`
- **Inhalt (Markdown-Mail-View):**
  - Fehler-Code + Klartext
  - Routing-Filter / Payer-Filter des fehlgeschlagenen Laufs
  - `last_success_at` (Wann lief er zuletzt erfolgreich?)
  - `consecutive_failures` Zähler
  - Stacktrace (gekürzt auf 30 Zeilen, **token-bereinigt** via Regex-Scrubber)
  - Link zum Admin-Dashboard (PROJ-6) für Sync-Status
- **Versand:** über bestehende Mail-Queue (`->onQueue('notifications')`).

#### Idempotenz (kein Mail-Spam)
Logik im `SynchroniseDhlCatalogService` nach Fehlschlag:
```
status = statusRepo.get()
if status.mail_sent_for_failure_streak == false:
    Mail::to(recipients).send(new DhlCatalogSyncFailedMail(...))
    statusRepo.update(mail_sent_for_failure_streak = true, last_mail_sent_at = now())
// else: bereits gemailt für diese Streak, kein erneuter Versand

// Bei Erfolg:
statusRepo.update(mail_sent_for_failure_streak = false, consecutive_failures = 0)
```
→ Mail nur beim **ersten** Fehler nach Recovery. Streak-Bruch resettet das Flag.

#### Dashboard-Banner (Verweis auf PROJ-6)
PROJ-6 liest `dhl_catalog_sync_status` per Read-Model und zeigt rotes Banner, wenn:
- `last_success_at IS NULL` (nie erfolgreich), ODER
- `last_success_at < now() - INTERVAL 14 DAY`, ODER
- `consecutive_failures >= 2`

Dieses Feature definiert **nur das Schema und die Schreibseite**. Die Anzeige-Logik gehört zu PROJ-6.

#### Sentry/Log
- **Logging-Channel `dhl-catalog`** in `config/logging.php`:
```
'dhl-catalog' => [
    'driver' => 'daily',
    'path'   => storage_path('logs/dhl-catalog.log'),
    'level'  => env('DHL_CATALOG_LOG_LEVEL', 'info'),
    'days'   => 30,
    'tap'    => [App\Logging\StripDhlTokensTap::class],   // Scrubber für Auth-Header/Tokens
    'formatter' => Monolog\Formatter\JsonFormatter::class,
],
```
- Jeder Fehler: `Log::channel('dhl-catalog')->error(...)` + `report($e)` (Sentry-Pickup, falls Provider registriert).
- Erfolgs-Lauf: `Log::channel('dhl-catalog')->info('sync.completed', $resultSummary)`.

---

### 7) Test-Strategie

#### Unit / Application (gemockter Gateway)
- **Ort:** `tests/Unit/Application/Fulfillment/Integrations/Dhl/Catalog/SynchroniseDhlCatalogServiceTest.php`
- Mock von `DhlFreightGateway` (bzw. `DhlProductCatalogService`-Wrapper).
- Test-Matrix:
  - leere API + leere DB → no-op, kein Audit
  - leere DB + 3 Produkte API → 3 inserts + 3 Audit `created`
  - DB hat 5, API hat 5 mit 1 geändert → 1 Audit `updated` mit `fieldDiff`
  - DB hat 5, API hat 4 (eines fehlt) → 1 Audit `deprecated`
  - DB hat 5 deprecated, API hat es wieder → 1 Audit `restored`
  - DB hat 100 aktiv, API hat 50 → `suspiciousShrinkage`, kein Schreibvorgang
  - Gateway wirft `ApiUnavailableException` → Retry 3x, dann `errors=[apiUnavailable]`
  - 401 → sofort `errors=[authFailed]`, kein Retry
  - Schema-fehlerhafter Eintrag → wird übersprungen, andere durchlaufen
  - `dryRun=true` → keine Audit-Einträge, keine DB-Mutation (TX rollback)
  - Idempotenz: zweimal mit gleichem Mock-Response → zweiter Lauf 0 Änderungen

#### Fixture-Replay (Integration)
- **Ort:** `tests/Feature/Console/DhlCatalogSyncFixtureReplayTest.php`
- Lädt gecommittete `database/seeders/data/dhl/*.json` als gemocktes Gateway-Response.
- Führt `dhl:catalog:sync` aus, prüft DB-Stand gegen bekannte Erwartung.
- Schützt vor Schema-Drift zwischen Fixtures und Persistence.

#### Smoke-Test gegen Sandbox
- **Ort:** `tests/Smoke/DhlCatalogSandboxSmokeTest.php`
- Skipped per default (`@group smoke`), läuft nur mit `DHL_API_TOKEN` gesetzt.
- Ruft live `dhl:catalog:sync --dry-run --routing=DE-AT`.
- Assertion: keine Exception, mind. 1 Produkt im Response, Manifest-Metadaten plausibel.
- Wird **nicht** in CI-Default-Pipeline ausgeführt.

#### CLI-Feature-Tests
- `BootstrapDhlCatalogCommandTest`: stub Gateway, prüft dass alle 4 JSON-Files + `_manifest.json` geschrieben sind und keine Tokens enthalten.
- `SyncDhlCatalogCommandTest`: prüft Exit-Code, Tabellen-Output, Lock-Verhalten.

---

### 8) Bestehende Architektur — Wiederverwendung

| Bestehend | Nutzung in PROJ-2 |
|---|---|
| `DhlFreightGateway` (Interface) | wird um 2 Catalog-Methoden erweitert, **nicht** dupliziert |
| `DhlFreightGatewayImpl` | erhält Implementierung der neuen Methoden |
| `DhlAuthenticationGateway` | unverändert, Token-Beschaffung wiederverwendet |
| `DhlProductCatalogService` (Wrapper) | wird um Routing/Payer-Convenience-Methoden ergänzt; bleibt einziger Konsument des Gateway aus Application-Sicht |
| `config/services.php` (DHL-Block) | unverändert, neue `config/dhl-catalog.php` ergänzt für Sync-spezifische Werte |
| Bestehende Mail-Queue | wiederverwendet für `DhlCatalogSyncFailedMail` |

**Keine neuen Konkurrenz-Services** zu `DhlProductCatalogService`. `SynchroniseDhlCatalogService` ist Konsument, nicht Ersatz.

---

### 9) Schicht-Compliance (Engineering-Handbuch)

- **Domain** (`app/Domain/Fulfillment/Catalog/` aus PROJ-1): Entities, Value Objects, Repository-Interfaces, `DhlCatalogSyncStatus`-Entity. Kein Framework, kein HTTP.
- **Application** (`app/Application/Fulfillment/Integrations/Dhl/Catalog/`): `SynchroniseDhlCatalogService`, Commands, Results. Orchestriert nur.
- **Infrastructure** (`app/Infrastructure/Integrations/Dhl/`, `app/Infrastructure/Persistence/`): Gateway-Impl, Repository-Impls, Mail-Adapter, Log-Tap.
- **Presentation** (`app/Console/Commands/Dhl/Catalog/`, `routes/console.php`): CLI-Adapter, Scheduler-Registrierung. Keine Fachlogik.

Abhängigkeitsrichtung strikt nach innen (§8). Application kennt Domain-Repositories, nie konkrete Eloquent-Modelle.

---

### 10) Dependencies (Packages)

**Keine neuen Composer-Packages erforderlich.** Alles auf bestehenden Bordmitteln (Laravel HTTP-Client für Gateway, Laravel Scheduler, Laravel Mail, Monolog).
Optional, falls nicht vorhanden: `sentry/sentry-laravel` (nur falls Sentry noch nicht installiert — vor Implementierung prüfen).

---

### 11) Handoff-Notizen für Backend-Implementierung (PROJ-2 Task)

Reihenfolge für den Backend-Dev:
1. `config/dhl-catalog.php` + ENV-Variablen-Dokumentation in `.env.example`
2. Migration + Repository für `dhl_catalog_sync_status`
3. Gateway-Interface + Impl erweitern (+ Wrapper-Service-Methoden)
4. `SynchroniseDhlCatalogService` mit Command/Result + Diff-Algorithmus (TDD!)
5. CLI-Commands (`bootstrap`, `sync`) + `DhlCatalogSeeder`
6. Scheduler-Registrierung + Logging-Channel
7. Mail-Klasse + Idempotenz-Logik
8. Smoke-Test gegen Sandbox (lokal, optional in CI)

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
