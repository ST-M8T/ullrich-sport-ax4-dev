# Test Failures Backlog

**Stand:** 2026-05-13 (Goal GOAL-2026-05-13T091351-prodverify, task t33c)
**Suite-Status:** 973 Tests, 3 Errors, 59 Failures, 1 Risky, 4 Deprecations.

Diese Datei dokumentiert die verbleibenden Test-Failures gruppiert nach
Root-Cause, damit Folge-PRs gezielt abgearbeitet werden koennen. Sie ersetzt
KEINE Feature-Specs in `features/` — sie ist nur Triage-Output.

---

## Cluster A — DHL Catalog Admin-Controller-Routen fehlen (30 Failures)

**Tests:**
- `Tests\Feature\Http\Controllers\Admin\Settings\DhlCatalog\DhlCatalogAuditControllerTest` (8 Tests)
- `Tests\Feature\Http\Controllers\Admin\Settings\DhlCatalog\DhlCatalogIndexControllerTest` (7 Tests)
- `Tests\Feature\Http\Controllers\Admin\Settings\DhlCatalog\DhlCatalogProductControllerTest` (3 Tests)
- `Tests\Feature\Http\Controllers\Admin\Settings\DhlCatalog\DhlCatalogServiceControllerTest` (3 Tests)
- `Tests\Feature\Http\Controllers\Admin\Settings\DhlCatalog\DhlCatalogSyncTriggerControllerTest` (9 Tests, davon 2 Errors)

**Symptom:** Alle Tests erhalten `404` auf URIs wie
`/admin/settings/dhl/katalog`, `/admin/settings/dhl/katalog/audit`,
`/admin/settings/dhl/katalog/products/{code}`,
`/admin/settings/dhl/katalog/services/{code}`,
`/admin/settings/dhl/katalog/sync`.

**Root-Cause:** Die Controller-Klassen unter
`app/Http/Controllers/Admin/Settings/DhlCatalog/` existieren, sind aber in
`routes/web.php` nicht registriert. `grep -n "katalog" routes/web.php` liefert
keine Treffer.

**Empfehlung:** **Eigener PR** "feat(dhl-catalog): admin routes + permissions"
- Routes in `routes/web.php` unter `admin.settings.dhl.katalog.*` registrieren.
- Permissions definieren: `dhl_catalog.view`, `dhl_catalog.audit.read`,
  `dhl_catalog.sync`.
- View-Stubs anlegen (Tests rufen `assertSee('DHL Katalog')` und
  `assertSeeText('Produkte: N')` auf).
- Engineering-Handbuch §22: Controller bleiben thin, Use-Cases via Application
  Services.

**Risiko bei Quick-Fix:** hoch — Permission-Modell, Middleware-Stack und
View-Layer muessen konsistent zur restlichen Admin-Settings-Struktur
(`admin.settings.dhl-freight.*`) sein.

---

## Cluster B — API-Admin-Routen fuer Allowed-DHL-Services fehlen (15 Failures)

**Tests:**
- `Tests\Feature\Http\Controllers\Api\Admin\AllowedDhlServicesControllerTest` (8 Tests)
- `Tests\Feature\Http\Controllers\Api\Admin\AllowedDhlServicesIntersectionTest` (7 Tests)

**Symptom:** Alle Endpunkte liefern `404` statt `401/403/200/422`.

**Root-Cause:** API-Routen unter `/api/admin/...` fuer Allowed-Services-Lookup
(Single-Routing + Bulk-Intersection) sind nicht in `routes/api.php`
registriert. Controller-Klassen muessten zudem geprueft werden — moeglich,
dass diese ebenfalls fehlen.

**Empfehlung:** **Eigener PR** "feat(dhl-catalog): allowed-services API"
- Routes + Controller in `app/Http/Controllers/Api/Admin/` anlegen.
- Cache-Layer fuer Lookup (Tests pruefen Cache-Hit / Cache-Flush nach Sync).
- Permission: `dhl_catalog.allowed_services.read`.
- Validation via FormRequest (country, payer required; intersection ≤100).

**Risiko bei Quick-Fix:** hoch — Cache-Invalidierung muss mit Sync-Job
gekoppelt sein.

---

## Cluster C — FreightProfile + DHL-Product-Code Validierung (9 Failures)

**Tests:**
- `Tests\Feature\Http\Requests\Fulfillment\Masterdata\FreightProfileDhlCatalogValidationTest` (6 Tests)
- `Tests\Feature\Http\Requests\Fulfillment\Masterdata\StoreFreightProfileRequestTest` (3 Tests, davon 1 die DB-Persistenz prueft)
- `Tests\Unit\Application\Fulfillment\Masterdata\FreightProfileServiceTest::test_create_normalises_and_persists_profile` (1 Error)

**Symptom:**
- `StoreFreightProfileRequest::rules()` enthaelt keinen `dhl_product_code`-Key.
- Update-Validation flasht keine `errors`/`warning`-Session-Keys.
- Service-Layer-Test erwartet `dhl_product_code` im `create()`-Argument-Array.

**Root-Cause:** Feature "DHL-Product-Code an FreightProfile binden" ist
teilweise implementiert (Migration + DB-Spalte vorhanden, sonst kaemen andere
Errors), aber `StoreFreightProfileRequest`/`UpdateFreightProfileRequest`
Rules und `FreightProfileService::create()` Argument-Mapping fehlen.

**Empfehlung:** **Eigener PR** "feat(freight-profile): dhl_product_code
validation + persistence"
- `dhl_product_code` in Rules-Array beider FormRequests.
- Validation gegen `DhlCatalogProduct`-Repository (exists / not deprecated).
- Deprecated → `session()->flash('warning', ...)`.
- Service-Layer: Feld in `create()`/`update()` durchreichen.

**Risiko bei Quick-Fix:** mittel — klar abgegrenzt, aber 9 Tests = bewusste
Implementation.

---

## Cluster D — Masterdata-Service Exception-Typen (5 Failures)

**Tests:**
- `Tests\Unit\Application\Fulfillment\Masterdata\PackagingProfileServiceTest::test_delete_throws_when_profile_missing`
- `Tests\Unit\Application\Fulfillment\Masterdata\SenderProfileServiceTest::test_delete_throws_when_profile_missing`
- `Tests\Unit\Application\Fulfillment\Masterdata\VariationProfileServiceTest::test_create_throws_when_packaging_missing`
- `Tests\Unit\Application\Fulfillment\Masterdata\VariationProfileServiceTest::test_create_validates_assembly_option_when_present`
- `Tests\Unit\Application\Fulfillment\Masterdata\VariationProfileServiceTest::test_delete_throws_when_variation_missing`

**Symptom:** Tests erwarten Domain-Exceptions
(`PackagingProfileNotFoundException`, `SenderProfileNotFoundException`,
`VariationProfileNotFoundException`), die Services werfen aber Eloquents
`ModelNotFoundException`.

**Root-Cause:** Engineering-Handbuch §16: Infrastructure-Fehler duerfen nicht
ungefiltert in die Application/Domain. Services nutzen direkt
`Model::findOrFail()` statt Repository + Domain-Exception.

**Empfehlung:** **Eigener PR** "refactor(masterdata): wrap repository
NotFound in domain exceptions"
- `find()` + `if (! $found) throw new XxxNotFoundException(...)` Pattern.
- Pro Aggregate eigene Exception (existiert anscheinend schon, wird nur nicht
  geworfen).

**Risiko bei Quick-Fix:** niedrig — koennte als Cluster-Fix in 30 min gemacht
werden, aber mit Compliance-Aenderung in 4 Services + ggf. Aufruferseite
(Controller). Bewusst nicht in diesem Time-Box-PR angefasst, da
Refactoring-Charakter.

---

## Cluster E — DHL Catalog Bootstrap- und Sync-Commands (3 Failures)

**Tests:**
- `Tests\Feature\Console\DhlCatalogBootstrapCommandTest::test_bootstrap_writes_all_fixture_files`
- `Tests\Feature\Console\DhlCatalogBootstrapCommandTest::test_auth_failure_aborts_with_clear_error`
- `Tests\Feature\Console\SyncDhlCatalogCommandTest::scheduler_registers_sync_command_when_cron_is_set`

**Symptom:** Bootstrap-Command schreibt keine Fixture-Files, Scheduler
registriert Command nicht.

**Root-Cause:** Implementation des Bootstrap-/Sync-Workflows offenbar
unvollstaendig oder Fixture-Pfad-Konfiguration weicht von Test-Erwartung ab.
Genauer Root-Cause muss in eigenem Investigation-PR ermittelt werden
(Test-Output `Failed asserting that 0 is identical to 1` ist nicht
selbsterklaerend).

**Empfehlung:** **Eigener PR / Investigation** "fix(dhl-catalog): bootstrap +
scheduler".

**Risiko bei Quick-Fix:** unklar — Root-Cause nicht eindeutig.

---

## Risky Test (1)

`Tests\Unit\Application\Fulfillment\Masterdata\FreightProfileServiceTest::test_create_normalises_and_persists_profile`:
"Test code or tested code did not remove its own error handlers." → Symptom
des Mockery-Fehlers in Cluster C; verschwindet mit Fix Cluster C.

---

## Deprecations (4)

PHPUnit-Deprecations, nicht test-blockierend. Eigener Chore-PR
"chore(tests): adress phpunit deprecations" sobald Cluster C+D durch sind.

---

## Zusammenfassung der Empfehlung

| Cluster | Failures | Empfehlung | Prioritaet |
|---------|----------|------------|------------|
| A — DHL Catalog Admin-Routes | 30 | Eigener Feature-PR | hoch (groesster Block) |
| B — Allowed-Services API | 15 | Eigener Feature-PR | hoch |
| C — FreightProfile dhl_product_code | 9 + 1 Error | Eigener Feature-PR | hoch (Daten-Integritaet) |
| D — Masterdata Exception-Wrapping | 5 | Refactor-PR | mittel |
| E — Bootstrap/Sync Commands | 3 | Investigation-PR | niedrig |

**Keine einzelne Cluster-Fix-Aktion in einer 30-Minuten-Time-Box moeglich**,
da jeder Cluster echte Feature- oder Refactor-Arbeit erfordert (Routes,
Permissions, Views, Cache, Repository-Pattern). Backlog-First-Approach gewaehlt.
