# PROJ-4: Versandprofil — Migration auf Katalog-FK

## Status: Planned
**Created:** 2026-05-12
**Last Updated:** 2026-05-12

## Dependencies
- **Requires:** [PROJ-1](PROJ-1-dhl-catalog-domain-persistence.md) — Tabelle `dhl_products`
- **Requires:** [PROJ-2](PROJ-2-dhl-catalog-sync-job.md) — Tabelle gefüllt
- **Requires:** [PROJ-3](PROJ-3-dhl-additional-service-mapper.md) — Mapper validiert gegen Katalog

## Kontext
Heute ist `fulfillment_freight_profiles.dhl_product_id` ein **Freitext** varchar(32). Pfleger müssen den DHL-Code in der UI per Hand eintippen (z.B. `V2PK`), siehe Settings-Screenshot aus der Initialfrage. Folge: Tippfehler, ungültige Codes, keine Validierung, keine sinnvolle Service-Auswahl möglich. Außerdem ist `dhl_default_service_codes` ein loses JSON-Array ohne Schema, das die zugehörigen Parameter nicht trägt.

Dieses Feature ersetzt die Freitext-Spalte durch eine **Foreign-Key-Spalte** auf `dhl_products.code` und das schwache Array durch ein **typisiertes JSON-Feld**, dessen Inhalt gegen das Katalog-Schema validiert ist. Zusätzlich wird das UI aus Freitext-Input zu einem Produkt-Select mit Live-Filter umgebaut.

Bestehende Profile werden per Migration auto-gematcht; unmatched Profile bekommen NULL + Banner im UI.

## User Stories
- Als **Versandpfleger** möchte ich beim Anlegen/Editieren eines Versandprofils ein DHL-Produkt aus einer durchsuchbaren Liste wählen statt einen Code eintippen — mit Anzeige von Name+Beschreibung+Routings.
- Als **Versandpfleger** möchte ich beim gewählten Produkt sehen, welche Additional Services standardmäßig aktiv sein sollen, mit dynamisch generierten Parameter-Feldern aus dem JSON-Schema.
- Als **System** möchte ich beim Speichern eines Profils die Default-Service-Parameter gegen das Katalog-Schema validieren, sodass nur konsistente Profile in DB landen.
- Als **DevOps** möchte ich bei der Migration alle bestehenden Profile auto-matchen lassen — und einen Report aller Profile bekommen, die nicht automatisch gematcht werden konnten.
- Als **Admin** möchte ich für Profile mit ungültigem Produkt-Code im UI einen klaren Warnhinweis sehen („Bitte Produkt neu wählen") und kann Buchungen damit verhindern, bis korrigiert.

## Acceptance Criteria

### Datenbankmigration
- [ ] Neue Spalte `fulfillment_freight_profiles.dhl_product_code` (varchar(8), nullable, FK auf `dhl_products.code` ON DELETE SET NULL).
- [ ] Datenmigration:
  1. Liest alle bestehenden Zeilen mit `dhl_product_id != NULL/''`.
  2. Normalisiert (uppercase, trim, ohne Whitespace).
  3. Match-Versuch gegen `dhl_products.code` (case-insensitive).
  4. Match → schreibt in `dhl_product_code`.
  5. Kein Match → loggt in `dhl_freight_profile_migration_report` (neue Tabelle: `id`, `profile_id`, `original_value`, `normalized_value`, `migration_status` enum [`matched|unmatched|already_null`], `migrated_at`).
- [ ] Alte Spalte `dhl_product_id` wird **noch nicht gelöscht** — bleibt für 1 Release als Backup. Löschung in Folge-Migration nach Verifikation.
- [ ] Neue Spalte `dhl_default_service_parameters` (JSON, nullable) ersetzt funktional `dhl_default_service_codes`:
  - Struktur `[{"code":"NOT","parameters":{"phone":"..."}},...]`
  - Bei Migration: bestehender Inhalt von `dhl_default_service_codes` (reines String-Array) wird in `[{"code":"X","parameters":null}, ...]` umgeschrieben.
  - Validierung gegen Katalog-Schema **nicht** in der Migration — alte Profile bleiben tolerant, müssen aber bei nächster UI-Bearbeitung korrigiert werden.
- [ ] Alte Spalte `dhl_default_service_codes` ebenfalls noch nicht gelöscht.

### Migration-Report-Sichtbarkeit
- [ ] Migration-Report-Tabelle wird unter [PROJ-6](PROJ-6-dhl-catalog-admin-inspection.md) als zusätzlicher Tab „Migration-Report" sichtbar gemacht (oder als eigener Menüpunkt unter Versand-Settings, je nach Architektur-Entscheidung).
- [ ] Banner auf der Versandprofil-Liste: „X Profile haben kein gültiges DHL-Produkt und müssen korrigiert werden" (Zähler), Klick führt zur gefilterten Liste.

### UI im Versandprofil-Formular
- [ ] Bestehende [DhlFreightSettingsController.php](app/Http/Controllers/Admin/Settings/DhlFreightSettingsController.php) liefert die DHL-Settings-Seite — Formular-Komponente wird angepasst:
  - Feld „DHL-Produkt" ist Select (kein Freitext mehr) mit:
    - Suchfeld über Code+Name+Beschreibung
    - Optionen sind aktive (non-deprecated) Produkte
    - Deprecated-Produkte werden nur angezeigt, wenn das Profil bereits eines referenziert (mit Badge „deprecated")
    - Bei deprecated mit `replaced_by_code` → Hinweis „Bitte auf [Nachfolger] wechseln"
  - Unter Produkt-Auswahl erscheint dynamische Section „Standard-Zusatzleistungen":
    - Liste aller `allowed` und `required` Services für das gewählte Produkt (Routing+Payer-Kontext kommt aus Profil-Feldern, falls vorhanden, sonst global)
    - `required` Services sind vorausgewählt und nicht abwählbar
    - Pro aktiviertem Service: dynamisch generierte Parameter-Felder aus JSON-Schema (Input-Typ basierend auf `type`: text/number/checkbox/date/select-aus-enum)
    - Default-Werte aus `default_parameters` des Assignments
- [ ] Beim Speichern: Validierung im Controller via FormRequest, der die Schema-Validierung des Katalogs (PROJ-3 Mapper) nutzt.
- [ ] Bei Validierungsfehler: klare Feldfehler pro Service+Parameter.

### API/Form-Request-Validierung
- [ ] `UpdateDhlFreightProfileRequest` validiert:
  - `dhl_product_code` ist im Katalog vorhanden (auch deprecated erlaubt, mit Soft-Warnung)
  - Default-Services nur aus erlaubten Codes für Produkt+Routing+Payer
  - Required-Services sind enthalten
  - Parameter validieren gegen Service-Schema
- [ ] Server-Validierung NIE durch Client-Validierung ersetzt (§15).

### Rollout
- [ ] Feature-Flag `config('dhl-catalog.strict_validation')` aus PROJ-3 wird in dieser Migration aktiviert: nach erfolgreicher Datenmigration **wird der Default in Prod auf `true` gesetzt** (Engineering-Handbuch verbietet Übergangsflags ohne Rückbau).
- [ ] Profile mit `dhl_product_code=NULL` (unmatched) verhindern keine Anlage neuer Profile — alte Profile bleiben funktionsfähig solange `dhl_product_id` (Freitext) gefüllt ist UND `strict_validation=false`. Sobald `true`, schlägt jede Buchung mit unmatchedem Profil fehl (klare Fehlermeldung).
- [ ] Cleanup-Migration in Folge-Sprint löscht `dhl_product_id` und `dhl_default_service_codes` Spalten.

### Routes/Controller
- [ ] Bestehender [DhlFreightSettingsController.php](app/Http/Controllers/Admin/Settings/DhlFreightSettingsController.php) wird angepasst, keine neuen Routes.
- [ ] Bestehender [SenderRuleController.php](app/Http/Controllers/Fulfillment/Masterdata/SenderRuleController.php) bleibt unverändert (verwendet keine Produkt-Codes).

## Edge Cases
- **Bestehendes Profil mit Tippfehler-Code** (z.B. `V2PKK` statt `V2PK`): Match schlägt fehl → unmatched-Report. Admin korrigiert via UI.
- **Bestehendes Profil mit gültigem aber jetzt deprecated Code**: Match erfolgreich, Profil bekommt FK, UI zeigt Warnbanner mit Hinweis auf Nachfolger.
- **Profil mit leerem `dhl_product_id`**: Migration setzt `migration_status=already_null`, kein Action erforderlich.
- **Migration läuft auf Prod ohne befüllten Katalog**: Migration bricht mit klarem Fehler ab — explizite Voraussetzung ist erfolgreicher Sync (PROJ-2). Kein Auto-Sync in Migration (zu riskant).
- **Default-Services mit Parameter-Werten, die nicht zum neuen Schema passen**: Migration behält die alten Werte 1:1, UI zeigt sie als invalide → Pfleger muss aktiv speichern, um zu korrigieren. Buchung schlägt nur fehl bei `strict_validation=true`.
- **Versandprofil ohne DHL** (nutzt andere Spediteur): `dhl_product_code` bleibt NULL — kein Problem, Feld ist nullable.
- **Zwei Profile mit identischem `dhl_product_code`**: Erlaubt — gleicher Code kann mehrfach referenziert werden.
- **DHL-Produkt wird zwischen UI-Laden und Speichern deprecated**: Race-Condition akzeptiert — Speichern erlaubt (Soft-Deprecate), UI zeigt nach Reload neuen Status.
- **Profil hat `dhl_default_service_codes=['X']` aber X ist nicht für das Produkt erlaubt**: Migration übernimmt 1:1, UI zeigt rote Warnung pro Service. Buchung schlägt bei `strict_validation=true` fehl.
- **Routing kontext-frei in Profil**: Wenn Profil keinen festen From/To-Country hat, wird Service-Liste mit `from_country=null, to_country=null` (global) abgefragt. Spezifische Routings werden erst zur Buchungszeit gezogen.

## Technical Requirements
- **Migration-Sicherheit**: Migration ist idempotent (Re-Run ändert nichts). Bei großen Datenmengen (>10k Profile) chunked (Batches à 1000).
- **Schichtung**: Datenmigration ist eine Laravel-Migration im Infrastructure-Layer. UI-Validierung im Application/Presentation-Layer.
- **Sicherheit**: Form-Requests validieren CSRF + Auth wie heute. Permission-Check für Bearbeiten bleibt wie heute.
- **DRY**: Service-Validierung läuft durch den Mapper aus PROJ-3 — keine duplizierte Validierungslogik im Controller.
- **Frontend**: Dynamische Form-Generierung folgt PROJ-5-Spezifikation (Akkordeon nach Kategorie). PROJ-4 nutzt **dieselbe** Komponente.
- **Reversibilität**: Rollback-Migration vorhanden. Alte Spalten bleiben für 1 Release Backup. Bei `down()` wird `dhl_product_code` zurück in `dhl_product_id` geschrieben (best-effort).
- **Testing**: Feature-Tests für Migration mit Fixture-Daten (Mix aus matched/unmatched/deprecated). Browser-Test für neue Form-UI. Re-Run-Idempotenz-Test.
- **Performance**: Migration für 10k Profile in <30s.

## Out of Scope
- Anpassung der UI im Buchungsformular (das ist PROJ-5 — separates Feature, da andere Route/Controller)
- Löschung der alten Spalten (kommt als Cleanup-Folge-Migration nach Verifikation in Prod)
- Migration historischer Buchungs-Records (`shipment_orders.dhl_product_id` wird NICHT angefasst — out-of-scope, da historisch und nicht mehr editierbar)

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### 1. Bestandsanalyse

**Existierende Tabelle `fulfillment_freight_profiles`** (Stand nach Migration `2025_05_11_000001`):

| Spalte | Typ | Zweck heute |
|---|---|---|
| `dhl_product_id` | varchar(32), nullable | **Freitext-Produktcode**, vom Pfleger eingetippt (z.B. `V2PK`). Keine FK, keine Validierung. |
| `dhl_default_service_codes` | JSON, nullable | Loses String-Array (`["NOT","PIN"]`) ohne Parameter, ohne Schema. |
| `shipping_method_mapping` | JSON, nullable | Routing-Mapping, **bleibt unverändert**. |
| `account_number` | varchar(32), nullable | DHL-Kundennummer, **bleibt unverändert**. |

Zusätzlich existieren die in der Initial-Migration angelegten Spalten (`id`, `label`, `is_active`, etc.) — werden nicht angefasst.

**Existierender Controller** `DhlFreightSettingsController` verwaltet aktuell **nur die Connection-Settings** (Auth-URL, API-Keys, Tracking-Settings, Timeout). Er pflegt **kein** Profil-Editing — das passiert in einem separaten Profil-Controller, der noch nicht konsolidiert ist. Für PROJ-4 relevant: Die Profil-Pflege-Maske ist eine **eigene** View/Component. Die Controller-Anpassung in PROJ-4 betrifft daher den (noch zu identifizierenden) Profil-Editor — der DhlFreightSettingsController bleibt unangefasst.

> **Open Question:** Vor Implementierung muss Frontend-Dev `git ls-files | grep -i 'freightprofile\|freight_profile'` ausführen, um den konkreten Editor-Controller/View zu finden. Falls nicht vorhanden, wird er als Teil von PROJ-4 neu angelegt — Layer-konform: `Http/Controllers/Admin/Settings/FreightProfileController.php` (Presentation) → Application Service → Domain.

### 2. Migrations-Stufen (drei separate Migrations)

**Migration A — Schema-Erweiterung (additiv, reversibel):**
- `ADD COLUMN dhl_product_code VARCHAR(8) NULL` mit FK `references('code') on('dhl_products') onDelete('set null')`.
- `ADD COLUMN dhl_default_service_parameters JSON NULL`.
- Alte Spalten (`dhl_product_id`, `dhl_default_service_codes`) **bleiben**.
- Index auf `dhl_product_code` für UI-Filter.
- `down()` droppt nur die neuen Spalten.

**Migration B — Datenmigration (chunked, idempotent):**
- Vorbedingung (Fail-Fast §67): `DB::table('dhl_products')->count() > 0` — sonst Abbruch mit klarem Fehler.
- Iteriert `fulfillment_freight_profiles` in Chunks à 1000 via `cursor()` + `chunkById()`.
- Pro Zeile:
  1. Wenn `dhl_product_code IS NOT NULL` → Skip (Idempotenz).
  2. Wenn `dhl_product_id IS NULL || ''` → Report `already_null`, nichts zu tun.
  3. Normalisierung: `strtoupper(trim(preg_replace('/\s+/', '', $value)))`.
  4. Lookup `dhl_products WHERE UPPER(code) = ?`. Match → `UPDATE dhl_product_code`. Report `matched`.
  5. Kein Match → Report `unmatched`. `dhl_product_code` bleibt NULL.
- Service-Codes: `dhl_default_service_codes` (z.B. `["NOT","PIN"]`) → `dhl_default_service_parameters` als `[{"code":"NOT","parameters":null},{"code":"PIN","parameters":null}]`. **Keine** Schema-Validierung in der Migration (§27 — robust, tolerant).
- Performance-Ziel: 10k Profile <30s, dafür Bulk-Update statt Row-by-Row wo möglich.
- `down()`: Best-Effort-Restore — kopiert `dhl_product_code` zurück in `dhl_product_id`, leert neue Spalten.

**Migration C — Cleanup (NUR Spec, Folge-Sprint):**
- `DROP COLUMN dhl_product_id`.
- `DROP COLUMN dhl_default_service_codes`.
- Vorbedingung-Check: Keine Buchung der letzten 30 Tage nutzt diese Spalten (aus Buchungs-History prüfbar).
- **Nicht Teil dieses Features** — separates Ticket nach Prod-Verifikation.

### 3. Report-Tabelle `dhl_freight_profile_migration_report`

| Spalte | Typ | Zweck |
|---|---|---|
| `id` | bigint, PK, auto-increment | |
| `profile_id` | bigint, FK → `fulfillment_freight_profiles.id`, ON DELETE CASCADE | |
| `original_value` | varchar(64), nullable | Roh-Wert aus `dhl_product_id` vor Normalisierung |
| `normalized_value` | varchar(64), nullable | Wert nach uppercase+trim |
| `migration_status` | enum(`matched`, `unmatched`, `already_null`) | |
| `matched_code` | varchar(8), nullable | Bei `matched`: tatsächlicher FK-Wert |
| `migrated_at` | timestamp | |
| Index | `(migration_status, migrated_at)` | für Report-Queries |

Unique-Constraint auf `profile_id` — Re-Run der Migration nutzt `upsert()` für Idempotenz.

### 4. FormRequest `UpdateDhlFreightProfileRequest`

Layer: Presentation (technische Eingabevalidierung §15). Pseudo-Implementierung:

```text
authorize() → Auth + Permission "manage freight profiles"

rules():
  - dhl_product_code: nullable | string | size:≤8 | exists:dhl_products,code
  - dhl_default_services: nullable | array
  - dhl_default_services.*.code: required_with:dhl_default_services | string
  - dhl_default_services.*.parameters: nullable | array
  - shipping_method_mapping: nullable | array
  - account_number: nullable | string | max:32
  - label: required | string | max:255

withValidator($v):  // Fachliche Invarianten — delegiert an Mapper aus PROJ-3
  $v->after(function ($v) {
    if ($code = $this->input('dhl_product_code')) {
      $services = $this->input('dhl_default_services', []);
      // Mapper-Aufruf — keine duplizierte Logik (§61 DRY):
      $errors = $this->dhlServiceMapper->validateProfileServices(
        productCode: $code,
        services: $services,
        routingContext: $this->resolveRoutingContext(),
      );
      foreach ($errors as $field => $message) {
        $v->errors()->add($field, $message);
      }
    }
  });
```

Trennung: Technische Validierung in `rules()`, fachliche Validierung (erlaubte/required Services, Parameter-Schema) im Mapper aus PROJ-3 — **eine Stelle für die fachliche Wahrheit**.

### 5. Controller-Anpassung

Der existierende `DhlFreightSettingsController` bleibt **unverändert** (nur Connection-Settings).

Der **Freight-Profile-Editor-Controller** (separat) bekommt:
- Injection des Mappers aus PROJ-3 (oder über Service-Container).
- `update()`-Action akzeptiert den neuen `UpdateDhlFreightProfileRequest`.
- Read-Modify-Write-Pattern analog zu `DhlFreightSettingsController` (validated → Aggregate laden → setter → repository->save).
- `index()`/Edit-View bekommt zusätzliche View-Daten:
  - `availableProducts`: Liste aktive + (falls Profil referenziert) deprecated Produkte aus Katalog-Repository.
  - `currentProductMeta`: Bei vorhandenem FK → Name, Beschreibung, deprecated-Flag, replaced_by_code.
  - `allowedServices`: Über Mapper berechnet — `allowed`/`required` Services für Produkt + Routing.
  - `unmatchedBannerCount`: `SELECT COUNT(*) WHERE dhl_product_id IS NOT NULL AND dhl_product_code IS NULL`.

Layer §7: Controller orchestriert nur — Mapping-/Validierungs-Logik liegt im Application-Service/Mapper.

### 6. UI-Komponente

**Wiederverwendbare Komponenten (DRY §75):**

a) **`<DhlProductSelect>`** — searchable Select:
   - Suchfeld filtert über `code`, `name`, `description`.
   - Optionen: aktive Produkte; deprecated-Produkte nur, wenn aktuell referenziert (mit `<Badge variant="warning">deprecated</Badge>`).
   - Bei deprecated mit Nachfolger: Hinweis-Block „Bitte auf {replaced_by_code} wechseln" inkl. Quick-Switch-Button.
   - Wiederverwendbar in PROJ-5 (Buchungsmaske).

b) **`<DhlAdditionalServicesAccordion>`** — dynamische Service-Sektion:
   - **Vorgreifend auf PROJ-5** als wiederverwendbare Komponente angelegt: Akkordeon nach Service-Kategorie gruppiert.
   - Pro Service: Checkbox (required → disabled+checked), dynamisch generierte Parameter-Felder aus JSON-Schema (`type` → `text`/`number`/`checkbox`/`date`/`select`).
   - Default-Werte aus `default_parameters` des Assignments.
   - Validation-Fehler werden pro Feld inline gerendert.
   - Eine Komponente — zwei Konsumenten (PROJ-4 Profil-Form + PROJ-5 Buchungs-Form). §75.1, §75.5 erfüllt.

c) **`<UnmatchedProfileBanner>`** — globaler Banner auf Profil-Listenseite:
   - Zeigt Zähler unmatched Profile, Klick → gefilterte Listen-View.

d) **`<DeprecatedProductWarning>`** — Inline-Banner über Profil-Form, wenn referenziertes Produkt deprecated.

**Layer-Trennung:**
- `domain/dhl-catalog/` (TS-Types) — bleibt aus PROJ-1/2.
- `application/freight-profile/useFreightProfileForm.ts` — Form-Hook (React-Hook-Form + Zod).
- `infrastructure/freight-profile/freightProfileApi.ts` — API-Client.
- `ui/freight-profile/` — die o.g. Komponenten. Keine fetch-Calls direkt.

### 7. Rollout-Plan

1. **Deploy Migration A** (additiv, risikofrei).
2. **Deploy Migration B** auf Stage → Report inspizieren.
3. **Deploy Migration B** auf Prod.
4. **Aktivierung** `config('dhl-catalog.strict_validation')`: **Default in `config/dhl-catalog.php` auf `true`** als Teil dieses Features ausgerollt. Übergangs-Toleranz nur, solange `.env` explizit `DHL_CATALOG_STRICT_VALIDATION=false` setzt — Notausgang für Rollback, kein dauerhafter Übergangsflag (§63 YAGNI: kein permanentes Flag).
5. **Banner-Phase** (1 Sprint): Pfleger korrigieren unmatched Profile via UI.
6. **Folge-Sprint:** Migration C (Cleanup) — alte Spalten droppen.

### 8. Test-Strategie

**Migration-Tests** (`tests/Feature/Migrations/FreightProfileCatalogFkMigrationTest.php`):
- Fixture-Setup: 4 Profile — (a) `dhl_product_id='V2PK'` matched, (b) `dhl_product_id='V2PKK'` typo→unmatched, (c) `dhl_product_id='V53'` deprecated→matched mit Banner, (d) `dhl_product_id=NULL`→already_null.
- Assertions: Korrekte FK gesetzt, Report-Einträge korrekt, alte Spalten unverändert.
- **Re-Run-Idempotenz:** Migration zweimal ausführen — keine doppelten Report-Einträge, keine Änderungen am Zustand.
- Service-Codes-Transformation: `["NOT","PIN"]` → korrektes neues JSON-Schema.

**FormRequest-Tests** (`tests/Feature/Http/Requests/UpdateDhlFreightProfileRequestTest.php`):
- Ungültiger Code → 422 mit Feldfehler.
- Required Service fehlt → 422.
- Parameter nicht im Service-Schema → 422 mit präzisem Feldfehler.
- Valider Request → 200/302.

**Mapper-Integration-Test:**
- FormRequest ruft Mapper korrekt mit Routing-Kontext auf.

**Browser-Walkthrough (manuell + Dusk optional):**
- Produkt-Suche, deprecated-Badge, dynamische Parameter-Felder, Speichern mit/ohne Validation-Fehler, Banner-Logik.

**Performance-Test:**
- Migration auf Seed mit 10k Profilen < 30s.

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_

---
```yaml
task_id: t5
goal_id: GOAL-2026-05-12T124024-dhlcat
agent: solution-architect
completed_at: 2026-05-12
deliverable: Tech Design Kapitel für PROJ-4 (Versandprofil → Katalog-FK)
sections_added:
  - Bestandsanalyse (existierende Spalten + Controller)
  - Drei-stufige Migrations-Strategie (A additiv / B Datenmigration / C Cleanup Spec)
  - Report-Tabellen-Schema dhl_freight_profile_migration_report
  - UpdateDhlFreightProfileRequest Pseudo-Implementierung (Mapper-Integration aus PROJ-3)
  - Controller-Anpassungs-Skizze (Freight-Profile-Editor, nicht DhlFreightSettingsController)
  - UI-Komponenten inkl. wiederverwendbare Akkordeon-Komponente für PROJ-5
  - Rollout-Plan mit strict_validation Default-true Aktivierung
  - Test-Strategie (Fixtures matched/unmatched/deprecated/null + Idempotenz)
constraints_respected:
  - "§27 Import: Migration chunked + idempotent + Fail-Fast bei leerem Katalog"
  - "§15 Validierung getrennt: technisch in FormRequest, fachlich via Mapper aus PROJ-3"
  - "§75 Frontend-DRY: Akkordeon-Komponente einmalig für PROJ-4 + PROJ-5"
  - "§63 YAGNI: strict_validation kein permanenter Flag, Default true, .env-Notausgang"
open_questions:
  - "Identifikation des konkreten Freight-Profile-Editor-Controllers vor Implementierung (git ls-files)"
next_skill: frontend (PROJ-4 Implementierung) nach User-Approval
```
