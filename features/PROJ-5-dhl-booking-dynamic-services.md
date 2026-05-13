# PROJ-5: DHL-Buchungsformular — Dynamische Service-UI

## Status: Planned
**Created:** 2026-05-12
**Last Updated:** 2026-05-12

## Dependencies
- **Requires:** [PROJ-1](PROJ-1-dhl-catalog-domain-persistence.md) — Domain-Modell + Repositories
- **Requires:** [PROJ-3](PROJ-3-dhl-additional-service-mapper.md) — Server-seitige Validierung gegen Katalog
- **Requires:** [PROJ-4](PROJ-4-freight-profile-catalog-fk.md) — Versandprofil hat FK auf Produkt + dynamische Form-Komponente existiert

## Kontext
Das DHL-Buchungsformular (Single + Bulk) liefert heute Service-Codes als statisches Set oder Checkbox-Reihe, ohne Verständnis dafür, **welche** Services für die konkrete Buchung (Produkt × Routing × Payer) tatsächlich erlaubt/pflicht/verboten sind. Konsequenz: Pfleger kann Optionen aktivieren, die DHL beim Buchen ablehnt — Fehler kommt erst nach API-Roundtrip.

Dieses Feature macht die Service-Auswahl im Buchungsformular **dynamisch** und **katalog-gesteuert**: Sobald Produkt+Routing+Payer im Form gewählt sind, lädt das UI die erlaubten Services und rendert sie als Akkordeon, gruppiert nach Service-Kategorie (Pickup/Delivery/Notification/Dangerous Goods/Special). Pflicht-Services sind vorausgewählt und nicht abwählbar. Parameter-Felder werden aus dem JSON-Schema generiert.

## User Stories
- Als **Buchungs-Pfleger** möchte ich nach Auswahl des Versandprofils sofort sehen, welche Zusatzleistungen für meine konkrete Buchung verfügbar sind — gruppiert nach Kategorie, nicht in einer endlosen flachen Liste.
- Als **Buchungs-Pfleger** möchte ich bei Pflicht-Services klar sehen, dass diese vorausgewählt sind und warum (z.B. „IMPORT_DECLARATION ist Pflicht für DE→CH").
- Als **Buchungs-Pfleger** möchte ich bei Services mit Parametern (z.B. COD-Betrag, Avisierungs-Telefon, Termin-Datum) ein passend typisiertes Eingabefeld bekommen — kein Freitext-JSON, sondern echtes Date-Picker/Number/Text mit Inline-Validierung.
- Als **Buchungs-Pfleger** möchte ich bei Validierungsfehlern (fehlender Pflichtparameter, Schema-Verletzung) klare Feldfehler an genau der richtigen Stelle sehen — nicht erst nach Submit auf einer Fehlerseite.
- Als **Fulfillment-Manager** möchte ich, dass das Buchungsformular bei deprecated Produkten einen klar gelben Banner zeigt und auf den Nachfolger hinweist — Buchung bleibt möglich, aber bewusst.

## Acceptance Criteria

### Form-Aufbau (Single-Buchung)
- [ ] Bestehende DHL-Buchungs-View ([ShipmentOrderController.php](app/Http/Controllers/Fulfillment/ShipmentOrderController.php)-zugeordnete Blade/Inertia/Vue-View) wird erweitert.
- [ ] Form-Sektion „Zusatzleistungen" erscheint **erst**, wenn Versandprofil (und damit Produkt+ggf. Routing/Payer) gewählt ist.
- [ ] Layout: Akkordeon mit aufklappbaren Blöcken pro Kategorie (Pickup, Delivery, Notification, Dangerous Goods, Special).
- [ ] Kategorie-Header zeigt Anzahl aktiver Services (z.B. „Notification (2/5)").
- [ ] Innerhalb einer Kategorie: Liste der Services mit Checkbox + Code + Name + Description.
- [ ] Pflicht-Services: Checkbox vorausgewählt, disabled, Hinweis „Pflicht für [Routing]".
- [ ] Verbotene Services werden NICHT angezeigt (Filter auf `requirement != forbidden`).
- [ ] Default-Services aus Versandprofil sind vorausgewählt (aus PROJ-4 `dhl_default_service_parameters`).

### Parameter-Felder
- [ ] Bei aktivierter Checkbox: dynamische Parameter-Felder erscheinen einrückbar darunter.
- [ ] Feld-Typ aus JSON-Schema:
  - `type:string` + `format:date` → Date-Picker
  - `type:string` + `format:email` → E-Mail-Input
  - `type:string` + `format:phone` → Telefon-Input mit Pattern
  - `type:string` (sonst) → Text-Input
  - `type:number`/`integer` → Number-Input mit Min/Max aus Schema
  - `type:boolean` → Toggle/Checkbox
  - `enum` → Select-Dropdown
  - `type:object` (verschachtelt) → eingerücktes Sub-Form
- [ ] Pflichtfelder mit `*` und Required-Constraint.
- [ ] Defaults aus Schema `default` oder Assignment `default_parameters`.
- [ ] Inline-Validierung (clientseitig) zusätzlich zur Server-Validierung (§15: Server bleibt SoT).

### Server-Endpoint für dynamische Services
- [ ] Neuer Endpoint `GET /api/admin/dhl/catalog/allowed-services` mit Query-Params `product_code`, `from_country`, `to_country`, `payer_code`.
- [ ] Liefert JSON: `{ "services": [{"code","name","description","category","requirement","parameter_schema","default_parameters"}, ...] }`.
- [ ] Auth + Permission (`dhl-booking.create` o.ä.) erforderlich.
- [ ] Cached pro Parameter-Kombination für 5 Minuten (Cache-Invalidierung beim Sync — Sync-Service tagged Cache).
- [ ] Response-Schema dokumentiert + Snapshot-getestet.

### Validierung beim Submit
- [ ] Form-Request validiert über Mapper-Pfad aus PROJ-3 — Mapper wirft Exception bei Verstoß, Controller übersetzt in 422 mit Feldfehlern.
- [ ] Fehlermeldungen sind feldgenau: `additional_services.NOT.parameters.phone: Pflichtfeld fehlt`.
- [ ] Bei Mapping-Erfolg läuft die Buchung wie heute über [DhlShipmentBookingService.php](app/Application/Fulfillment/Integrations/Dhl/Services/DhlShipmentBookingService.php) (intern aktualisiert in PROJ-3).

### Bulk-Buchung
- [ ] In [ProcessDhlBulkBookingJob.php](app/Jobs/ProcessDhlBulkBookingJob.php) bzw. dem Bulk-Vorbereitungs-UI: gleiche dynamische Services-Sektion, gilt für alle Sendungen im Batch.
- [ ] Bei Mix-Routings im Batch werden nur Services angeboten, die in ALLEN Routings erlaubt sind (Schnittmenge). Hinweis „X Services nicht für alle Routings verfügbar — siehe Detail" mit Aufklapp-Liste.
- [ ] Pflicht-Services pro Routing werden pro Sendung automatisch ergänzt (vom Mapper).

### Visuelles & UX
- [ ] Akkordeon-Komponente: shadcn/ui (siehe globale Frontend-Rules) wenn vorhanden, sonst bestehende interne Komponente. **Keine** eigene Implementierung.
- [ ] Tailwind-Tokens für Spacings/Farben, keine Magic Hex/PX.
- [ ] Responsive: Tablet ≥ 768px voll bedienbar, Mobile-Workaround (vertikales Stack) zulässig.
- [ ] Accessibility (§51): Akkordeon mit `aria-expanded`, Tab-Navigation, Screenreader-Labels, kein Fokus-Verlust beim Aufklappen.
- [ ] Loading-State während `/allowed-services`-Call (Skeleton-Loader im Akkordeon-Bereich).
- [ ] Empty-State wenn Produkt keine Services hat: „Für dieses Produkt sind keine Zusatzleistungen verfügbar".
- [ ] Error-State wenn Endpoint fehlschlägt: „Services konnten nicht geladen werden, [Retry-Button]".

### Deprecated-Handling
- [ ] Wenn Versandprofil ein deprecated Produkt referenziert: Gelber Banner oben am Form „Dieses Versandprofil nutzt ein veraltetes DHL-Produkt. Buchung weiterhin möglich, bitte zeitnah anpassen."
- [ ] Wenn `replaced_by_code` gesetzt: zusätzlich „Empfohlener Nachfolger: [Code+Name]".
- [ ] Deprecated Services in der Service-Liste: gelber Pfeil-Icon + Tooltip „Diese Zusatzleistung läuft aus".

## Edge Cases
- **Profil ohne FK** (unmatched aus PROJ-4): Service-Sektion zeigt Banner „Versandprofil muss konfiguriert werden". Buchung nicht möglich (Disable Submit).
- **Routing-Felder werden nach Service-Auswahl geändert**: Service-Liste wird neu geladen. Vorher gewählte Services bleiben erhalten, wenn sie im neuen Routing auch erlaubt sind; sonst entfernt mit Hinweis-Toast „Service X wurde entfernt (nicht für [neues Routing] verfügbar)".
- **Service mit komplexem verschachtelten Schema** (`type:object` mit eigenen `properties`): UI rendert bis 2 Verschachtelungsebenen. Tiefer → Fallback-JSON-Textarea mit Schema-Hinweis und Server-Validierung. (Vor PROJ-5-Architektur prüfen, ob DHL solche Schemas überhaupt liefert.)
- **Sehr viele Services pro Produkt (>30)**: Akkordeon paginiert nicht — innerhalb Kategorie Liste scrollbar.
- **Endpoint-Cache veraltet nach Sync**: Sync-Service (PROJ-2) ruft `Cache::tags(['dhl-catalog'])->flush()` nach erfolgreichem Sync auf. Buchungs-UI lädt nächstes Mal frisch.
- **User wechselt Versandprofil mit aktiven Service-Auswahlen**: Confirm-Dialog „Service-Auswahl wird zurückgesetzt — fortfahren?".
- **Submit mit JS deaktiviert**: Form fällt auf serverseitige Validierung zurück, Fehler werden via Standard-422-Flow angezeigt. Funktionalität gegeben, UX reduziert.
- **Race: Pfleger A bucht, Sync invalidiert Service zwischen Form-Load und Submit**: Server-Mapping wirft `UnknownDhlServiceException` mit 422 + Hinweis „Bitte Form neu laden — Service nicht mehr verfügbar".
- **Bulk mit 1000 Sendungen, gemischte Routings**: Schnittmengen-Berechnung server-seitig (eigener Endpoint), nicht im Browser. Max. 100 Routings unterschiedliche pro Bulk akzeptiert (sonst 422 mit Aufforderung zur Aufteilung).

## Technical Requirements
- **Schichtung**: Controller validiert + ruft Application-Service. Service-Endpoint ist eigener Read-Only-Controller (`AllowedDhlServicesController`), nutzt PROJ-1 Repository.
- **DRY (§75)**: Dynamische Service-Form-Komponente wird als wiederverwendbare Vue/Blade-Komponente gebaut und in **Versandprofil-UI (PROJ-4) UND Buchungs-UI (PROJ-5) identisch genutzt**. Keine Duplikate.
- **Sicherheit**: Endpoint braucht Auth + Permission. Caching darf keine Berechtigungs-Lücke öffnen (Cache-Key beinhaltet keine User-Daten, da Katalog global ist).
- **Performance**: Service-Lade-Latenz <300ms p95. JSON-Schema-Render im Browser keine Reflows >50ms.
- **Frontend-Layer (§35–§39)**: Form-Komponente in `src/contexts/fulfillment/ui/` (oder analoge Blade-Struktur). API-Call über zentralen Service-Hook, nicht direkter `fetch` aus der Komponente.
- **Accessibility**: WCAG 2.2 AA. Screenreader testet Aufklappen + Pflicht-Hinweise.
- **Testing**: Component-Tests für Akkordeon + Schema-Renderer. Feature-Tests für Endpoint (Auth, Cache, Schnittmengen). End-to-End-Test mit Pest/Dusk: Buchung mit COD-Parameter durchlaufen.
- **i18n**: Service-Namen/Descriptions kommen aus Katalog (DHL liefert sie ggf. nur EN — Übersetzungs-Layer als optionales Override via `config/dhl-catalog.php → service_translations` als Map).

## Out of Scope
- Stornierung/Re-Buchung (bleibt im bestehenden [DhlCancellationController.php](app/Http/Controllers/Api/Admin/DhlCancellationController.php))
- Tarif/Preisberechnung in Echtzeit beim Service-Wechsel (Preis-Quote nutzt jetzt zwar denselben Mapper, aber Live-Preview im Formular ist separates Feature)
- Übersetzung der DHL-Service-Bezeichnungen (Override via Config, eigene UI-Strings sind i18n-fähig)
- Auto-Vorbelegen der Routing-Felder aus Empfänger-Adresse — bleibt wie heute

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### 1. Bestandsanalyse — Aktuelle Buchungs-UI

Die DHL-Buchung läuft heute über einen **Blade-basierten Stack** (kein Vue/Inertia, kein SPA):

- **Controller-Eintrieg Single-Buchung:** `app/Http/Controllers/Fulfillment/ShipmentOrderController.php`, Routen `POST /fulfillment/orders/{order}/dhl/book` (web.php Z. 138) und API-Variante `POST /api/admin/dhl/booking` (api.php Z. 76, Permission `fulfillment.orders.manage`).
- **Hauptview:** `resources/views/fulfillment/orders/show.blade.php` zieht den Buchungs-Block via `@include('fulfillment.orders._dhl-package-editor', [...])`.
- **Form-Partial:** `resources/views/fulfillment/orders/_dhl-package-editor.blade.php` enthält das eigentliche Buchungs-Formular inkl. Service-Auswahl. Heute steuert es Zusatzleistungen über `data-dhl-services-container`, `data-dhl-services-list` und einen statischen GET-Aufruf gegen `/api/admin/dhl/services` (alle Services flach, ungefiltert nach Produkt/Routing/Payer) — siehe `_dhl-package-editor.blade.php` Z. 135, Z. 280–296.
- **Bestehende JS-Bausteine (Vanilla, ESM):** `resources/js/domains/fulfillment/dhl-booking-form.js` (Produkt-Selector mit `idle/loading/success/empty/error`-States), `dhl-product-catalog.js`, `dhl-price-quote.js`, `dhl-package-editor.js`. Gemeinsame HTTP-Helper in `resources/js/core/http.js` (`fetchJson`). Keine Vue/React-Schicht im Projekt.
- **Bulk:** `resources/views/components/dhl/bulk-booking-modal.blade.php` + `app/Jobs/ProcessDhlBulkBookingJob.php`. Form-Felder werden als Modal in der Listenansicht angereichert; Service-Auswahl gilt für den ganzen Batch.
- **Permission-Modell:** Es existiert kein `dhl-booking.create`. Die etablierten Gates sind `fulfillment.orders.manage` (Schreiben) und `fulfillment.orders.view` (Lesen). Wir folgen diesem Muster (KISS, §72).

**Konsequenz für PROJ-5:** Die dynamische Service-Sektion wird als **Blade-Partial mit Vanilla-JS-Controller im bestehenden `resources/js/domains/fulfillment/`-Stil** umgesetzt — nicht als Vue/React-Komponente. Frontend-DRY (§75) wird über eine **einzige** Blade-Component + **einen** JS-Controller realisiert, die sowohl PROJ-4 (Versandprofil-Edit) als auch PROJ-5 (Buchung) nutzen.

---

### 2. Server-Endpoint `AllowedDhlServicesController`

**Route** (in `routes/api.php`, Gruppe `admin` mit `auth.admin`):
```
GET  /api/admin/dhl/catalog/allowed-services
       ?product_code=...&from_country=DE&to_country=CH&payer_code=SENDER
       middleware: can:fulfillment.orders.manage
       name:       api.dhl.catalog.allowed-services
```
Zusätzlich für Bulk (siehe §6):
```
POST /api/admin/dhl/catalog/allowed-services/intersection
       middleware: can:fulfillment.orders.manage
       name:       api.dhl.catalog.allowed-services.intersection
```

**Controller** `app/Http/Controllers/Api/Admin/AllowedDhlServicesController.php` (read-only, dünn, ruft Application-Service):
- `__invoke(AllowedDhlServicesRequest $request): JsonResponse` — Form Request validiert `product_code|required|string`, Country-Codes `regex:/^[A-Z]{2}$/`, `payer_code|in:SENDER,RECIPIENT,THIRD_PARTY`.
- Delegiert an `App\Application\Fulfillment\Integrations\Dhl\Catalog\Queries\GetAllowedDhlServices` (Application-Service, nutzt PROJ-1 Repository `DhlAdditionalServiceRepositoryInterface`).
- Mappt Domain → Response-DTO via dedicated `AllowedDhlServiceResource`.

**Response-Schema:**
```json
{
  "context": {
    "product_code": "DHL_EUROPLUS",
    "from_country": "DE",
    "to_country": "CH",
    "payer_code": "SENDER",
    "deprecated": false,
    "replaced_by_code": null
  },
  "services": [
    {
      "code": "IMPORT_DECLARATION",
      "name": "Importerklärung",
      "description": "Pflicht für Drittland-Sendungen",
      "category": "special",
      "requirement": "mandatory",
      "deprecated": false,
      "parameter_schema": { "type": "object", "properties": {...}, "required": [...] },
      "default_parameters": { "...": "..." }
    }
  ]
}
```
Reihenfolge: `mandatory` zuerst, dann alphabetisch innerhalb Kategorie. `forbidden` wird **nicht** ausgegeben (Filter im Application-Service).

**Cache** (`config/cache.php` mit Redis/`tagged`-fähigem Store vorausgesetzt — vom Projekt bereits genutzt):
- Key: `dhl-catalog:allowed-services:{sha1(product_code|from|to|payer)}`
- TTL: `300` Sekunden (5 Min)
- Tag: `dhl-catalog` (gemeinsam mit PROJ-2)
- Cache enthält **keine** User-Daten → keine Berechtigungs-Leak möglich (§19, §56). Auth bleibt vor dem Cache-Lookup im Middleware-Stack.

```php
// Pseudostruktur im Application-Service – Code-Details Sache des Backend-Devs
Cache::tags(['dhl-catalog'])->remember($key, 300, fn () => $repo->findAllowed(...));
```

---

### 3. Cache-Invalidierung beim Sync (PROJ-2)

Aufruf an **genau einer** Stelle: `App\Application\Fulfillment\Integrations\Dhl\Catalog\Services\SynchroniseDhlCatalogService` (aus PROJ-2), am **Ende** des erfolgreichen Sync-Laufs, **nach** Commit der Transaktion:

```php
// SynchroniseDhlCatalogService::execute() — letzte Zeile vor return
Cache::tags(['dhl-catalog'])->flush();
```

Begründung: Vor Commit könnte ein paralleler Read den Cache mit stale Daten neu warm-up'en. Nach Commit ist der DB-Stand verbindlich. Bei Sync-Fehler (Exception, Rollback) **kein** Flush — alter Cache bleibt gültig.

---

### 4. Akkordeon-Komponente — Zentrale, wiederverwendbare Frontend-Einheit

**Pfad (Blade-Komponente, DRY für PROJ-4 + PROJ-5):**
- View: `resources/views/components/dhl/allowed-services-accordion.blade.php`
- Klasse: `app/View/Components/Dhl/AllowedServicesAccordion.php` (Blade-Component)
- JS-Controller: `resources/js/domains/fulfillment/dhl-allowed-services-accordion.js`
- CSS: keine eigenen Styles — Bootstrap-5-Accordion (im Projekt etabliert, vgl. `bootstrap-modal.js`) + Tailwind-/SCSS-Tokens des Designs

**Props (PHPDoc, Blade-Component-Konstruktor):**
```
@property string  $contextSource   'shipment_order' | 'freight_profile'
@property ?string $productCode     null bei Profil-Edit ohne Produktbezug
@property ?string $fromCountry
@property ?string $toCountry
@property ?string $payerCode
@property string  $inputName       Form-Feldname (z.B. 'additional_services')
@property array   $preselected     ['CODE' => ['parameters' => [...]], ...]
@property string  $endpointUrl     Route('api.dhl.catalog.allowed-services')
@property bool    $readOnly        true für Detail-/Preview-Ansichten
```

**Single Source of Truth (§44):** Die Komponente besitzt **keinen** dauerhaften eigenen State außerhalb des Formulars. Auswahl + Parameter werden direkt in `<input type="hidden" name="additional_services[CODE][parameters][...]">`-Felder im umgebenden `<form>` geschrieben. Loading/Error/Schema-Cache sind reiner UI-State, lokal in der JS-Controller-Instanz.

**API-Zugriff (§45):** Die Komponente ruft **nicht** direkt `fetch`. Stattdessen geht der Call durch einen kleinen Service-Layer:
- Neu: `resources/js/domains/fulfillment/services/dhl-allowed-services-service.js` mit `fetchAllowed({productCode, fromCountry, toCountry, payerCode})` und `fetchIntersection({routings})`. Nutzt zentrales `core/http.js → fetchJson`.

**Lifecycle:**
```
idle      → noch kein Produkt/Routing gewählt → Hinweis-Banner
loading   → Skeleton (3 Kategorie-Header mit pulsierenden Balken), aria-busy="true"
success   → Akkordeon gerendert (siehe Layout-Tree unten)
empty     → "Für dieses Produkt sind keine Zusatzleistungen verfügbar."
error     → Inline-Error + [Erneut versuchen]-Button
```

**Layout-Tree (Akkordeon):**
```
AllowedServicesAccordion
├── Banner-Bereich
│   ├── Deprecated-Warning (gelb, conditional)
│   └── Empty-/Error-/Loading-State
├── <div class="accordion" role="region" aria-label="Zusatzleistungen">
│   ├── Kategorie "Pickup"        (Header: aria-expanded, Badge "1/3")
│   │   └── Service-Items
│   ├── Kategorie "Delivery"
│   ├── Kategorie "Notification"
│   ├── Kategorie "Dangerous Goods"
│   └── Kategorie "Special"
└── Hidden-Input-Sammlung (form-state)
```

**Service-Item Struktur:**
```
Service-Item
├── Checkbox (disabled, wenn requirement=mandatory)
├── Label: Code + Name
├── Tooltip: Description + Requirement-Begründung
├── Deprecated-Icon (conditional)
└── Parameter-Slot
    └── <DhlServiceParameterForm schema={...}> (siehe §5)
```

---

### 5. JSON-Schema-Renderer (Sub-Komponente)

**Pfad:**
- Partial: `resources/views/components/dhl/_service-parameter-form.blade.php` (server-side Initial-Render aus `default_parameters`)
- JS-Renderer: `resources/js/domains/fulfillment/dhl-service-parameter-renderer.js`

**Mapping `JSON-Schema → Input-Komponente`** (alle Inputs nutzen bestehende Bootstrap-5-Formular-Klassen des Projekts — keine neuen UI-Primitives, keine Magic Values, vgl. §47/§49):

| Schema-Match                             | Render                                                | Inline-Validierung                          |
|------------------------------------------|-------------------------------------------------------|---------------------------------------------|
| `type:string, format:date`               | `<input type="date">`                                 | Required, Min/Max aus `minDate`/`maxDate`   |
| `type:string, format:date-time`          | `<input type="datetime-local">`                       | Required                                    |
| `type:string, format:email`              | `<input type="email">`                                | HTML5 + regex                               |
| `type:string, format:phone` o. `tel`     | `<input type="tel" pattern="...">`                    | Pattern aus Schema oder E.164-Default       |
| `type:string` + `enum`                   | `<select>`                                            | Required                                    |
| `type:string` (sonst)                    | `<input type="text">`                                 | `minLength`/`maxLength`/`pattern`           |
| `type:string` + `maxLength > 200`        | `<textarea>`                                          | Length                                      |
| `type:number` / `type:integer`           | `<input type="number" step min max>`                  | Min/Max/MultipleOf                          |
| `type:boolean`                           | Bootstrap-Switch (`form-check form-switch`)           | —                                           |
| `type:array` von Primitives              | Tag-Input (Add/Remove)                                | `minItems`/`maxItems`                       |
| `type:object` (Tiefe 1–2)                | Eingerücktes Sub-Form, rekursiver Renderer            | Wie Felder                                  |
| `type:object` (Tiefe > 2) **oder** `oneOf`/`anyOf` | `<textarea>` mit JSON + Schema-Hinweis      | JSON-Parse + Schema-Hash an Server          |

**Default-Werte:** Reihenfolge Profil-`default_parameters` → Schema-`default` → leer. Pflichtfelder erhalten `*` + `required`-Attribut + `aria-required="true"`.

**Inline-Validierung:** Auf `blur` und `submit`. Fehler-Container pro Feld mit `aria-describedby`. Server bleibt Single Source of Truth (§15) — clientseitige Checks sind UX-Hilfe, **nicht** Sicherheit.

**Verschachtelung:** Rekursionstiefe 2 hart begrenzt im Renderer; Tiefer → Fallback-Textarea. Begründung: KISS (§62) — komplexere Schemas sind im aktuellen DHL-Katalog nicht beobachtet (siehe Edge Case Z. 85). Bei Auftreten Fallback dokumentieren und PROJ-X für Erweiterung anlegen.

---

### 6. Buchungs-Form-Integration

**Single-Buchung (`resources/views/fulfillment/orders/_dhl-package-editor.blade.php`):**
- Bestehender Service-Block (Z. 280–296) wird durch `<x-dhl.allowed-services-accordion ... />` ersetzt.
- Produkt-/Routing-/Payer-Felder dispatchen Custom-DOM-Event `dhl:context-changed` mit Detail-Payload. Der Accordion-JS-Controller hört darauf, ruft die Service-API neu, mergt selektierte Codes (siehe Edge Case "Routing geändert").
- Versandprofil-Wechsel triggert Confirm-Dialog (`window.confirm` reicht — KISS), Reset bei Bestätigung.

**Bulk (`resources/views/components/dhl/bulk-booking-modal.blade.php`):**
- Komponente erhält Liste aller Routings (`array<{from,to,product,payer}>`).
- Statt `GET /allowed-services` ruft sie `POST /allowed-services/intersection` mit den Routings; Server berechnet Schnittmenge (Domain-Service `ComputeAllowedServicesIntersection`).
- Banner zeigt entfernte Codes mit Aufklapp-Liste „Nicht für alle Routings verfügbar".
- Max 100 Routings pro Bulk (422 sonst, siehe Edge Case).

**Versandprofil-UI (PROJ-4) — selbe Komponente:**
- Pfad: `resources/views/admin/masterdata/freight-profiles/_form.blade.php` (oder analog) bindet `<x-dhl.allowed-services-accordion :product-code="$profile->dhlProductCode()" :input-name="'dhl_default_service_parameters'" ... />`.
- Im Profil-Kontext nutzt sie `requirement != forbidden` analog, Pflicht-Services werden nur **angezeigt**, nicht editierbar — exakt dieselbe Komponente, dieselben Inputs.

---

### 7. Race-Conditions & Error-Handling

| Szenario                                            | Behandlung                                                                                     |
|-----------------------------------------------------|------------------------------------------------------------------------------------------------|
| Sync zwischen Form-Load und Submit                  | Server-Mapper (PROJ-3) wirft `UnknownDhlServiceException` → Controller fängt → 422 mit Feldfehlern + Hinweis-Toast „Bitte Form neu laden". Frontend zeigt globalen Banner + Auto-Reload-Button. |
| Endpoint-Timeout / 5xx                              | Akkordeon-State `error`, Retry-Button. Buchungs-Submit bleibt **disabled**, bis Services erfolgreich geladen wurden (§67 Fail Fast).    |
| User aktiviert Service mit gerade gelöschtem Code   | Submit → 422 → Feldfehler auf `additional_services.CODE` → Akkordeon scrolt zum Fehler.        |
| Cache stale durch parallelen Sync                   | Cache wird durch `flush()` am Sync-Ende invalidiert (§3). Nächster Request lädt frisch.       |
| Permission-Verlust mitten in Session                | API antwortet 403 → Komponente zeigt „Keine Berechtigung", Submit disabled.                    |

---

### 8. Accessibility (WCAG 2.2 AA, §51)

- **Akkordeon:** Jedes Kategorie-Header-`<button>` mit `aria-expanded`, `aria-controls`, `id`-Verlinkung. Content-Panel mit `role="region"` + `aria-labelledby`.
- **Fokus-Management:** Beim Aufklappen bleibt Fokus auf Header (nicht auto-springen). Beim Auswählen einer Pflicht-Service-Disabled-Checkbox: Tooltip via `aria-describedby` mit Begründung.
- **Tastatur:** Tab-Reihenfolge logisch (Header → Header → … → Content beim Expand). Enter/Space toggelt Header.
- **Pflichtfelder:** `aria-required="true"`, `*`-Marker im Label.
- **Fehlermeldungen:** `aria-live="polite"` für Feld-Fehler, `aria-live="assertive"` für globale Banner-Errors.
- **Loading:** `aria-busy="true"` am Container während Lade-Phase.
- **Screenreader-Test-Plan:** NVDA + Firefox (Windows), VoiceOver + Safari (macOS). Pflicht-Szenarien: (a) Akkordeon-Navigation per Tastatur, (b) Pflicht-Service-Hinweis wird vorgelesen, (c) Parameter-Feld-Fehler werden angesagt, (d) Deprecated-Banner wird beim Page-Load wahrgenommen.

---

### 9. Test-Strategie

**Component-Tests (Jest/Vitest gegen Vanilla-JS-Controller):**
- `dhl-allowed-services-accordion.test.js`: Lifecycle (idle/loading/success/empty/error), Routing-Change → Re-Fetch + Merge, Pflicht-Service nicht abwählbar, Deprecated-Banner-Render.
- `dhl-service-parameter-renderer.test.js`: Schema-Mapping pro Typ, Default-Werte, Inline-Validierung, Fallback bei Tiefe > 2.
- Snapshot-Test des gerenderten Akkordeon-DOM für eine Beispiel-Service-Liste (klein, fokussiert — kein Pixel-Snapshot).

**Feature-Tests (Pest, `tests/Feature/Api/Admin/AllowedDhlServicesControllerTest.php`):**
- 401 ohne Auth, 403 ohne `fulfillment.orders.manage`.
- 200 mit korrekten Params, Response-Schema-Snapshot via `assertJsonStructure` + `MatchesSnapshots`.
- Cache-Hit (zweiter Request kein Repository-Call — Repository-Mock).
- Cache-Invalidierung: nach `Cache::tags(['dhl-catalog'])->flush()` ist Repository-Call wieder messbar.
- Bulk-Intersection: Schnittmenge bei drei Routings korrekt; > 100 Routings → 422.
- Validation-Fehler bei fehlenden/falschen Query-Params → 422.

**E2E (Pest 4 Browser / Dusk, `tests/Browser/DhlBookingDynamicServicesTest.php`):**
- Pfleger meldet sich an, wählt Order, Versandprofil, sieht Akkordeon erscheinen.
- Service mit COD-Parameter aktivieren, Betrag eingeben, Submit → erfolgreiche Buchung.
- Routing ändern → Service entfernt, Toast erscheint.
- Submit ohne Pflicht-Parameter → Feldfehler an genau dem Feld.

**Snapshot-Update-Regel:** Snapshots werden bei Schema-Erweiterung explizit erneuert (PR-Review-Trigger), nicht blind.

---

### 10. Komponenten-Übersicht (neu vs. wiederverwendet)

| Artefakt                                                                                            | Status      |
|-----------------------------------------------------------------------------------------------------|-------------|
| `app/Http/Controllers/Api/Admin/AllowedDhlServicesController.php`                                   | **NEU**     |
| `app/Http/Requests/Api/Admin/AllowedDhlServicesRequest.php`                                         | **NEU**     |
| `app/Application/Fulfillment/Integrations/Dhl/Catalog/Queries/GetAllowedDhlServices.php`            | **NEU**     |
| `app/Application/Fulfillment/Integrations/Dhl/Catalog/Services/ComputeAllowedServicesIntersection.php` | **NEU**  |
| `app/Http/Resources/Api/Admin/AllowedDhlServiceResource.php`                                        | **NEU**     |
| `app/View/Components/Dhl/AllowedServicesAccordion.php`                                              | **NEU**     |
| `resources/views/components/dhl/allowed-services-accordion.blade.php`                               | **NEU**     |
| `resources/views/components/dhl/_service-parameter-form.blade.php`                                  | **NEU**     |
| `resources/js/domains/fulfillment/dhl-allowed-services-accordion.js`                                | **NEU**     |
| `resources/js/domains/fulfillment/dhl-service-parameter-renderer.js`                                | **NEU**     |
| `resources/js/domains/fulfillment/services/dhl-allowed-services-service.js`                         | **NEU**     |
| `resources/views/fulfillment/orders/_dhl-package-editor.blade.php`                                  | **GEÄNDERT** (Service-Block-Ersatz) |
| `resources/views/components/dhl/bulk-booking-modal.blade.php`                                       | **GEÄNDERT** |
| `SynchroniseDhlCatalogService` (PROJ-2)                                                             | **GEÄNDERT** (Cache-Flush am Ende) |
| `routes/api.php`                                                                                    | **GEÄNDERT** (2 neue Routen) |
| `resources/js/core/http.js`                                                                         | **wiederverwendet** |
| Bootstrap-5-Accordion + Form-Klassen                                                                | **wiederverwendet** |

---

```yaml
agent: solution-architect
goal: GOAL-2026-05-12T124024-dhlcat
task: t6
feature: PROJ-5
status: design-complete
next: PROJ-5 → backend (Controller + Application-Service + Cache + Intersection) → frontend (Blade-Component + JS-Controller + Renderer) → qa
last_updated: 2026-05-12
```

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
