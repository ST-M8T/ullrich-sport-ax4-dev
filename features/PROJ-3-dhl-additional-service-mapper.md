# PROJ-3: DHL Additional Service — Mapper-Zentralisierung

## Status: Planned
**Created:** 2026-05-12
**Last Updated:** 2026-05-12

## Dependencies
- **Requires:** [PROJ-1](PROJ-1-dhl-catalog-domain-persistence.md) — Domain-Modell für Validierung gegen Katalog
- **Requires:** [PROJ-2](PROJ-2-dhl-catalog-sync-job.md) — Katalog muss mit echten Daten gefüllt sein

## Kontext
Aktuell ist das Mapping zwischen interner Service-Repräsentation und dem DHL-API-Payload **dupliziert** über drei Stellen:
- [DhlShipmentBookingService.php](app/Application/Fulfillment/Integrations/Dhl/Services/DhlShipmentBookingService.php)
- [DhlBulkBookingService.php](app/Application/Fulfillment/Integrations/Dhl/Services/DhlBulkBookingService.php)
- [DhlPriceQuoteService.php](app/Application/Fulfillment/Integrations/Dhl/Services/DhlPriceQuoteService.php)

Das verstößt gegen DRY (Engineering-Handbuch §61, §75) und führt zu schleichenden Inkonsistenzen — z.B. Preis-Quote berechnet anderen Effekt als die tatsächliche Buchung. Dieses Feature zentralisiert das Mapping in genau eine Klasse, validiert gegen den Katalog (PROJ-1/2) und stellt sicher, dass alle drei Services den gleichen Pfad nutzen.

Wichtig: Dieses Feature **refactoriert** das Bestehende, ohne sichtbares Verhalten zu ändern (außer dass jetzt erstmals gegen den Katalog validiert wird).

## User Stories
- Als **Backend-Entwickler** möchte ich genau eine Stelle haben, an der Service-Optionen (`DhlServiceOptionCollection`) in DHL-API-Payload übersetzt werden, sodass Änderungen an der API-Struktur nur an einer Stelle nötig sind.
- Als **Tester** möchte ich, dass Buchung, Bulk-Buchung und Preis-Anfrage identisch validieren — wenn ein Service-Code für ein Produkt+Routing verboten ist, muss ALLE drei Services ihn ablehnen, nicht nur einer.
- Als **Fulfillment-Verantwortlicher** möchte ich, dass die Anwendung **vor** dem API-Call gegen den Katalog validiert (Service erlaubt? Pflichtparameter gesetzt? Parameter-Werte gegen Schema?), sodass DHL-Validierungsfehler nicht erst beim API-Roundtrip auftauchen.
- Als **Admin** möchte ich ausschließlich aus dem Katalog stammende Service-Codes in Buchungen sehen — kein Buchungspfad darf einen Code an DHL senden, den der Katalog nicht kennt.

## Acceptance Criteria

### Neuer Mapper `DhlAdditionalServiceMapper`
- [ ] Liegt in `app/Application/Fulfillment/Integrations/Dhl/Mappers/DhlAdditionalServiceMapper.php`.
- [ ] Public Methode `toApiPayload(DhlProductCode $productCode, RoutingContext $routing, DhlServiceOptionCollection $options): array` liefert das DHL-API-konforme Array.
- [ ] `RoutingContext` ist ein neuer Value Object in `Domain/Fulfillment/Shipping/Dhl/ValueObjects/` mit `fromCountry`, `toCountry`, `payerCode`.
- [ ] Vor Mapping wird gegen `DhlProductServiceAssignmentRepository::findAllowedServicesFor(...)` validiert:
  - Service-Code ist im Katalog vorhanden → sonst `UnknownDhlServiceException`
  - Service ist für dieses Produkt+Routing nicht `forbidden` → sonst `ForbiddenDhlServiceException`
  - Alle `required`-Services sind enthalten → sonst `MissingRequiredDhlServiceException`
  - Parameter validieren gegen Service-`parameterSchema` → sonst `InvalidDhlServiceParameterException`
- [ ] Exceptions sind Domain-/Application-Exceptions, **keine** HTTP-Exceptions (Mapping in HTTP-Layer geschieht in der Presentation).
- [ ] Falls Service deprecated ist: Warnung im strukturierten Log, aber Mapping bleibt erlaubt (Soft-Deprecate, Engineering-Handbuch + Runde 1 Entscheidung).

### Refactoring der drei Services
- [ ] [DhlShipmentBookingService.php](app/Application/Fulfillment/Integrations/Dhl/Services/DhlShipmentBookingService.php) ruft den neuen Mapper auf, eigene Service-Mapping-Logik entfernt.
- [ ] [DhlBulkBookingService.php](app/Application/Fulfillment/Integrations/Dhl/Services/DhlBulkBookingService.php) ebenso. Bulk-Validierung sammelt Mapping-Fehler pro Sendung statt direkt zu werfen.
- [ ] [DhlPriceQuoteService.php](app/Application/Fulfillment/Integrations/Dhl/Services/DhlPriceQuoteService.php) ebenso. Preis-Quote-Fehler propagieren wie heute (DHL-API-Fehler bleiben unverändert).
- [ ] Existierende Tests dieser drei Services laufen weiter grün — Verhalten bleibt extern identisch, nur die interne Quelle der Wahrheit ändert sich.
- [ ] Bestehender [DhlReferenceMapper.php](app/Application/Fulfillment/Integrations/Dhl/Mappers/DhlReferenceMapper.php) bleibt unverändert (kümmert sich um Referenzen, nicht um Services).

### Tests
- [ ] Unit-Tests für `DhlAdditionalServiceMapper`:
  - Erfolgreiches Mapping mit allen erlaubten Services
  - Ablehnung unbekannter Service-Code
  - Ablehnung forbidden Service
  - Ablehnung wenn required Service fehlt
  - Ablehnung wenn Parameter Schema-Verletzung
  - Akzeptanz mit deprecated Service + Log-Warnung
  - Routing-spezifische vs. globale Assignment-Auflösung
- [ ] Integration-Tests verifizieren, dass die drei aufrufenden Services identisches Verhalten zeigen (gleicher Input → gleiches Result/Exception).
- [ ] Snapshot-Tests für generierten DHL-Payload (Vergleich gegen committed JSON-Fixture).

### Validierungsschicht
- [ ] Validierung ist **Application-Layer** (nicht Domain — der Katalog wird via Repository geladen).
- [ ] Mapper holt Assignments einmalig pro Aufruf (kein N+1).
- [ ] Bei leerem Katalog (PROJ-2 noch nicht gelaufen) → Fallback-Modus mit Feature-Flag `config('dhl-catalog.strict_validation')`:
  - `true`: Mapper wirft `DhlCatalogNotPopulatedException` (Default in Prod nach PROJ-2)
  - `false`: Mapper überspringt Katalog-Validierung, mappt nur strukturell (Übergangsmodus während Rollout — siehe PROJ-4)

## Edge Cases
- **Service mit Parametern (z.B. COD)**: `DhlServiceOption` enthält `code='COD'` und `parameters=['amount'=>50.0, 'currency'=>'EUR']`. Mapper validiert beide gegen Schema und übersetzt in API-Payload `{"code":"COD","amount":50.0,"currency":"EUR"}`.
- **Service ohne Parameter**: `DhlServiceOption` mit `parameters=null` und Schema erlaubt es (kein `required`) → Payload `{"code":"NOT"}`.
- **Routing-spezifische Pflicht-Services**: Z.B. für `DE→AT` ist Service `IMPORT_DECLARATION` `required`. Buchung ohne diesen Service → Exception MIT Hinweis, welcher Code fehlt.
- **Globale vs. spezifische Assignment-Kollision**: Wenn Service `COD` global `allowed` ist aber für `DE→CH` `forbidden`, gewinnt die spezifische Zuordnung — Mapper lehnt für CH-Routing ab.
- **Deprecated Produkt**: Mapping erlaubt, aber Log-Warnung. Aufrufer (Buchungs-Service) entscheidet, ob Buchung trotzdem stattfindet (Soft-Deprecate).
- **Unbekannter Payer-Code**: `RoutingContext` validiert Payer-Code beim Konstruieren (nutzt bestehenden `DhlPayerCode`-VO).
- **Bulk-Buchung mit gemischten Fehlern**: 100 Sendungen, 3 mit invaliden Service-Codes → Bulk-Result enthält 97 OK + 3 Fehler-Einträge mit konkretem Mapper-Exception-Typ pro Sendung. Kein Abbruch der gesamten Bulk-Operation.
- **Sehr alter Buchungs-Re-Request**: Buchung wurde gespeichert mit Service-Code, der zwischenzeitlich aus DHL-Katalog verschwunden ist → Mapper wirft `UnknownDhlServiceException`. Aufrufer muss entscheiden (Stornierung/Re-Buchung). Bestehende Buchungen bleiben in DB, werden nicht migriert.
- **Concurrent Sync während Buchungs-Mapping**: Sync läuft in Transaktion, Mapper-Lesequery sieht entweder vollständig alten oder neuen Stand — Inkonsistenz ausgeschlossen.
- **Feature-Flag `strict_validation=false` in Prod**: Übergangsmodus. Tests verifizieren, dass beim Wechsel auf `true` keine Buchung kaputt geht (Gold-Standard-Test gegen real-life Buchungsdaten).

## Technical Requirements
- **Schichtung**: Mapper im Application-Layer, Repository-Interfaces aus Domain (PROJ-1), Exceptions in `Application/Fulfillment/Integrations/Dhl/Exceptions/`.
- **DRY (§61, §75)**: Genau **eine** Implementierung des Service-Mappings. Codereview muss prüfen, dass nach dem Refactor keine `// build service payload`-Codeblöcke mehr in den drei betroffenen Services existieren.
- **DIP (§8, §64)**: Mapper hängt nicht von Eloquent oder HTTP-Layer ab, nur von Repository-Interfaces und Domain-VOs.
- **OCP**: Erweiterung um neue Service-Kategorien geschieht im Katalog, nicht im Mapper-Code.
- **Performance**: Pro Mapping-Aufruf max. 1 DB-Query für Assignment-Lookup. Bei Bulk-Buchung mit gleichem Produkt+Routing wird Assignment-Liste pro Bulk-Lauf gecached (request-scope, kein persistenter Cache).
- **Sicherheit (§22)**: Mapper validiert technische Eingabe, Domain-Invarianten bleiben in Domain. Keine SQL-Injection-Vektoren (alle Inputs sind VOs).
- **Testing**: ≥95% Coverage auf Mapper. Mutation-Testing für die Validierungs-Branches gewünscht.
- **Logging**: Strukturiert via Channel `dhl-catalog` (gleicher Channel wie PROJ-2).
- **Reversibilität**: Refactoring darf keine User-sichtbare Verhaltensänderung erzeugen, solange `strict_validation=false`. Verhaltensänderung mit `strict_validation=true` ist die explizite, gewollte Härtung.

## Out of Scope
- Mapping von Adressen, Packstücken, Referenzen — bleibt wie heute (separate Mapper, [DhlReferenceMapper.php](app/Application/Fulfillment/Integrations/Dhl/Mappers/DhlReferenceMapper.php) etc.)
- UI-Änderungen (PROJ-5)
- Versandprofil-FK-Migration (PROJ-4)

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### 1. Vor-/Nach-Vergleich der Doppellogik

#### Heutiger Zustand (DRY-Verstoß)

Die "Service-Optionen → DHL-API-Payload"-Übersetzung passiert heute an **drei voneinander unabhängigen Stellen** ohne gemeinsame Validierung:

1. **`DhlPayloadAssembler::buildBookingPayload()`**
   ([app/Application/Fulfillment/Integrations/Dhl/Mappers/DhlPayloadAssembler.php:90-93](app/Application/Fulfillment/Integrations/Dhl/Mappers/DhlPayloadAssembler.php))
   ```
   $services = $options->serviceOptions();
   if ($services->isEmpty() === false) {
       $payload['additionalServices'] = $services->toArray();
   }
   ```
   Aufgerufen aus `DhlShipmentBookingService::bookShipment()` ([Services/DhlShipmentBookingService.php:56](app/Application/Fulfillment/Integrations/Dhl/Services/DhlShipmentBookingService.php)).
   **Keine** Katalog-Validierung — `toArray()` schreibt jeden String roh ins Payload.

2. **`DhlPayloadAssembler::buildPriceQuotePayload()`**
   ([app/Application/Fulfillment/Integrations/Dhl/Mappers/DhlPayloadAssembler.php:141-144](app/Application/Fulfillment/Integrations/Dhl/Mappers/DhlPayloadAssembler.php))
   ```
   $services = $options->serviceOptions();
   if ($services->isEmpty() === false) {
       $payload['additionalServices'] = $services->toArray();
   }
   ```
   Aufgerufen aus `DhlPriceQuoteService::quote()` ([Services/DhlPriceQuoteService.php:49](app/Application/Fulfillment/Integrations/Dhl/Services/DhlPriceQuoteService.php)).
   **Identischer Codeblock** wie 1 — Copy-Paste, kein gemeinsamer Pfad, keine Validierung. Verletzt §61/§75.

3. **`DhlBulkBookingService::bookBulk()`**
   ([app/Application/Fulfillment/Integrations/Dhl/Services/DhlBulkBookingService.php:34-57](app/Application/Fulfillment/Integrations/Dhl/Services/DhlBulkBookingService.php))
   Nimmt `array<int,string> $additionalServices` als **rohe String-Liste** entgegen, baut daraus `DhlBookingOptions::fromArray([...,'additional_services'=>$additionalServices,...])` und delegiert an `DhlShipmentBookingService`. Damit wandert die Liste über einen dritten, eigenen Konstruktionspfad ins Payload — wieder ohne Katalog-Validierung.

Konsequenzen heute:
- Drei Pfade, keine gemeinsame Wahrheit. Eine Schema-Änderung (z.B. neuer Parameter-Block) muss an drei Stellen synchron geändert werden.
- Preis-Quote kann technisch andere Services akzeptieren als Buchung — schleichende Inkonsistenz.
- Kein Pfad validiert gegen den Katalog (PROJ-1/2). DHL meldet erst beim API-Roundtrip, dass ein Service für ein Produkt/Routing verboten ist.
- Bulk-Pfad bekommt nicht-typisierte Strings und umgeht die `DhlServiceOption`-VO-Disziplin.

#### Zielzustand (nach Refactor)

- **Genau eine** Klasse `DhlAdditionalServiceMapper` ist verantwortlich für die Übersetzung `DhlServiceOptionCollection → array` und die Katalog-Validierung.
- Die beiden `if ($services->isEmpty() === false) { $payload['additionalServices'] = $services->toArray(); }`-Blöcke im `DhlPayloadAssembler` werden ersetzt durch **einen Aufruf** `$mapper->toApiPayload($productCode, $routing, $services)`.
- `DhlBulkBookingService` baut weiterhin `DhlBookingOptions`, aber die String-Liste wird vorab durch eine kleine VO-Konstruktion in `DhlServiceOptionCollection` überführt (kein paralleler Mapping-Pfad).
- Nach dem Refactor enthält **keiner** der drei Services und auch der `DhlPayloadAssembler` keinen direkten `$services->toArray()`-Aufruf mehr — Codereview-Regel.

---

### 2. Neuer Mapper `DhlAdditionalServiceMapper`

**Ort:** `app/Application/Fulfillment/Integrations/Dhl/Mappers/DhlAdditionalServiceMapper.php`

**Dependencies (Konstruktor — alles Interfaces aus PROJ-1 Domain, §8 DIP):**
- `DhlProductServiceAssignmentRepository $assignmentRepository`
- `DhlServiceCatalogRepository $serviceRepository` (für Parameter-Schema-Lookup)
- `LoggerInterface $logger` (Channel `dhl-catalog`)
- `ConfigRepository $config` (für Feature-Flag-Lookup)

**Public API:**
```
public function toApiPayload(
    DhlProductCode $productCode,
    RoutingContext $routing,
    DhlServiceOptionCollection $options,
): array
```
Liefert das DHL-API-konforme Array (Form: `[['code'=>'COD','amount'=>50.0,'currency'=>'EUR'], ['code'=>'NOT']]`) oder wirft eine der Application-Exceptions (siehe §4).

**Interne Validierungs-Sequenz (in dieser Reihenfolge — Fail-Fast §67):**

1. **Feature-Flag prüfen** — wenn `dhl-catalog.strict_validation === false` und Katalog leer: Logge Warning, fahre direkt mit Strukturmapping fort, überspringe Schritte 2–5.
2. **Katalog-Verfügbarkeit prüfen** — `assignmentRepository->existsAny()`. Wenn leer + `strict_validation === true` → `DhlCatalogNotPopulatedException`.
3. **Assignments einmalig laden** — `assignmentRepository->findAllowedServicesFor($productCode, $routing)` liefert `DhlProductServiceAssignmentCollection`. Genau **eine** DB-Query pro Aufruf (Performance-Vorgabe).
4. **Service-Code-Existenz** — für jeden `DhlServiceOption` in `$options`: Code muss im Assignment-Set vorhanden sein. Sonst → `UnknownDhlServiceException` (Code + Produkt + Routing im Exception-Payload).
5. **Forbidden-Check** — kein gelieferter Service darf in der Routing-spezifischen Auflösung `forbidden` sein (spezifisches Routing schlägt globales, siehe Edge-Case). Sonst → `ForbiddenDhlServiceException`.
6. **Required-Check** — alle Assignments mit `required=true` müssen in `$options` vorhanden sein. Sonst → `MissingRequiredDhlServiceException` mit Liste der fehlenden Codes.
7. **Parameter-Schema-Validierung** — pro Option mit Parametern: `serviceRepository->findByCode($code)->parameterSchema()->validate($option->parameters())`. Sonst → `InvalidDhlServiceParameterException` mit Pfad+Wert+Schema-Erwartung.
8. **Deprecated-Warnung** — wenn Assignment `deprecated=true`: `logger->warning('dhl.service.deprecated', […])`. Mapping läuft trotzdem durch (Soft-Deprecate, siehe AC).
9. **Strukturmapping** — `$payload[] = ['code'=>…, …Parameter…]` pro Option. Reihenfolge stabil (sortiert nach Code für Snapshot-Determinismus).

Mapper ist **stateless** außer für den Request-Scope-Cache (siehe Performance-Anforderung): kleine private Map `productCode|routing → AssignmentCollection`, lebt nur über die Lebenszeit der Instanz; im Container als `scoped`/Request-Singleton gebunden.

---

### 3. RoutingContext-VO

**Ort:** `app/Domain/Fulfillment/Shipping/Dhl/ValueObjects/RoutingContext.php`

**Felder (alle `readonly`):**
- `CountryCode $fromCountry`
- `CountryCode $toCountry`
- `DhlPayerCode $payerCode`

**Konstruktor-Validierung (Fail-Fast §67):**
- `CountryCode` (bestehender VO) validiert ISO-3166-Alpha-2 selbst → keine zusätzliche Logik.
- `DhlPayerCode` (bestehender VO, siehe Edge-Case "Unbekannter Payer-Code") validiert sich selbst → keine zusätzliche Logik.
- Statische Factory `fromShipmentOrder(ShipmentOrder $order, DhlPayerCode $payer): self` für die Aufrufer (vermeidet Boilerplate in den drei Services).

VO ist `equals()`-fähig (Wertgleichheit über alle drei Felder) — wichtig für den Request-Scope-Cache-Key.

**Layer-Disziplin (§8):** Liegt in Domain (kein Eloquent, kein HTTP, keine Repository-Referenzen). Wird vom Application-Layer-Mapper konsumiert.

---

### 4. Application-Exceptions

**Ort:** `app/Application/Fulfillment/Integrations/Dhl/Exceptions/`

Alle erben von einer gemeinsamen Basis `DhlAdditionalServiceMappingException extends RuntimeException` (für selektives `catch` im Bulk-Pfad). Keine HTTP-Exceptions (§16). Mapping nach HTTP geschieht im Presentation-Layer.

| Exception | Trigger | Payload |
|---|---|---|
| `UnknownDhlServiceException` | Service-Code nicht im Katalog | `code`, `productCode`, `routing` |
| `ForbiddenDhlServiceException` | Service für Produkt+Routing verboten | `code`, `productCode`, `routing` |
| `MissingRequiredDhlServiceException` | Pflicht-Service fehlt | `missingCodes[]`, `productCode`, `routing` |
| `InvalidDhlServiceParameterException` | Parameter verletzt Schema | `code`, `parameterPath`, `expected`, `actual` |
| `DhlCatalogNotPopulatedException` | Katalog leer + `strict_validation=true` | (keine fachlichen Felder; rein technisch) |

Jede Exception hat eine statische Factory (`::for(...)`) und einen menschenlesbaren Default-Messages (deutsch, Ubiquitous Language: "Service", "Produkt", "Routing", "Pflicht-Service", "Parameter").

---

### 5. Refactor-Strategie pro Service (atomar, je PR-fähig)

**Reihenfolge ist bindend** — jeder Schritt ist eigenständig grün, jeder hat eigene Tests, jeder ist einzeln revertierbar.

**Schritt A — Mapper + VO + Exceptions anlegen (neuer Code, kein Eingriff in Services):**
- Tests: Unit-Tests für `DhlAdditionalServiceMapper` (alle AC-Branches), Tests für `RoutingContext` Konstruktor-Validierung, Tests für jede Exception (Message + Payload).
- Akzeptanz: Code ist da, wird aber noch nirgends aufgerufen → keine Verhaltensänderung.

**Schritt B — `DhlPayloadAssembler::buildBookingPayload` umverdrahten:**
- `DhlPayloadAssembler` wird von statischen Methoden auf Instanz-Methoden mit DI umgebaut **oder** bekommt eine optionale Mapper-Injection (kleinste Korrektur; vermutlich neue Instanz-Methode `assembleBooking(...)` und Deprecation der statischen Variante).
- Die Zeilen 90–93 entfernen, durch `$payload['additionalServices'] = $mapper->toApiPayload($productCode, $routing, $services)` ersetzen (nur wenn Result nicht leer).
- `DhlShipmentBookingService` baut `RoutingContext::fromShipmentOrder($order, $payerCode)` und reicht durch.
- Tests: Bestehende Snapshot-Tests des Booking-Payloads laufen grün (Strukturidentität mit `strict_validation=false`). Neuer Test: mit `strict_validation=true` + leerer Katalog → `DhlCatalogNotPopulatedException`.

**Schritt C — `DhlPayloadAssembler::buildPriceQuotePayload` umverdrahten:**
- Analog Schritt B, Zeilen 141–144.
- Tests: Snapshot-Tests Price-Quote-Payload grün; Verhaltens-Identität zur Buchung wird im selben Integration-Test verifiziert (gleicher Input → gleicher `additionalServices`-Block in beiden Payloads).

**Schritt D — `DhlBulkBookingService` umverdrahten:**
- Bulk nimmt weiterhin `array<int,string>` als Input (öffentliche API-Stabilität).
- Vor dem Aufruf von `DhlBookingOptions::fromArray` wird die String-Liste in `DhlServiceOptionCollection::fromArray(...)` überführt (bereits heute der interne Pfad in `DhlBookingOptions::fromArray`, siehe [DTOs/DhlBookingOptions.php:88-90](app/Application/Fulfillment/Integrations/Dhl/DTOs/DhlBookingOptions.php)) — kein neuer Pfad, nur Aufruf-Reihenfolge geklärt.
- Bulk-Pfad fängt jede `DhlAdditionalServiceMappingException` pro Sendung und packt sie als Fehler-Eintrag ins Result (Edge-Case "Bulk-Buchung mit gemischten Fehlern"). Kein Bulk-Abbruch.
- Tests: Bulk mit 5 OK + 2 invaliden Codes → 5 success, 2 failed mit konkretem Exception-Typ pro Eintrag.

**Schritt E — Cleanup + Verifikation:**
- `git grep -n "additionalServices.*toArray\|services->toArray"` muss in den drei Services + `DhlPayloadAssembler` **leer** sein.
- Static-Analysis-Regel (PHPStan/Custom-Rule): Direkter Aufruf `$x->toArray()` auf `DhlServiceOptionCollection` außerhalb des Mappers ist verboten.
- Integration-Test "Verhaltens-Identität": Gleicher Input durch alle drei Services produziert identischen `additionalServices`-Block.

---

### 6. Feature-Flag `dhl-catalog.strict_validation`

**Config-Ort:** `config/dhl-catalog.php` (gleicher Channel wie PROJ-1/2).

**Verhalten:**

| Wert | Katalog leer | Katalog gefüllt |
|---|---|---|
| `false` (Übergangsmodus, Default während Rollout) | Mapper überspringt Validierung, mappt nur strukturell, Log-Warning `dhl.catalog.empty_skip`. | Mapper validiert vollständig, Verstöße werfen Exceptions wie spezifiziert. |
| `true` (Ziel-Default in Prod nach PROJ-2) | Mapper wirft `DhlCatalogNotPopulatedException`. | Wie `false`-Variante mit gefülltem Katalog. |

Begründung: Erlaubt risikoarmes Deployment (PROJ-3 vor PROJ-2 produktiv → `false`), Härtung passiert per Flag-Flip ohne Deploy.

**Pflicht-Tests:**
- Beide Werte × (leerer Katalog, gefüllter Katalog) = 4 Branches, je ein Test.
- "Gold-Standard"-Test: Realbuchungs-Fixtures (50–100 Snapshots aus Produktion, sanitisiert) durchlaufen mit `strict_validation=true` ohne neue Exceptions. Verifiziert, dass der Flip-Tag keine bestehende Buchung kaputtmacht.

---

### 7. Test-Strategie

**Coverage-Ziel:** Mapper ≥95% Line + Branch (mutation-getestet auf den 5 Validierungsbranches).

**Test-Pyramide:**

1. **Unit-Tests `DhlAdditionalServiceMapper`** (alle AC-Branches)
   - Erfolgreiches Mapping (allowed + optional)
   - `UnknownDhlServiceException` (Code nicht im Katalog)
   - `ForbiddenDhlServiceException` (Code forbidden)
   - `MissingRequiredDhlServiceException` (required fehlt)
   - `InvalidDhlServiceParameterException` (Schema-Verletzung pro Schema-Typ: number, enum, required-key, type-mismatch)
   - Deprecated-Service: Mapping läuft + Logger-Warning verifiziert
   - Routing-Specific schlägt Global (Edge-Case Kollision)
   - Feature-Flag-Matrix (4 Branches)
   - Cache-Verhalten: Bei 50 Aufrufen mit gleichem Produkt+Routing genau **eine** Repository-Call (Spy).

2. **Unit-Tests `RoutingContext`**
   - Erfolgreicher Konstruktor
   - Invalider `CountryCode` → propagierte VO-Exception
   - Invalider `DhlPayerCode` → propagierte VO-Exception
   - `equals()` Wertgleichheit
   - `fromShipmentOrder` Factory

3. **Unit-Tests Exceptions**
   - Factory `::for(...)` Payload korrekt
   - Default-Message verständlich (Sprach-Smoke-Test)

4. **Snapshot-Tests Payload** (verhindert ungewollte strukturelle Drift)
   - Buchung mit 0/1/3 Services, mit/ohne Parameter → JSON-Fixture in `tests/__snapshots__/dhl-additional-services/`
   - Price-Quote analog
   - Bulk-Buchung (einzelner Eintrag identisch zur Einzelbuchung)
   - Fixture-Diffs sind committed und manuell reviewbar.

5. **Integration-Verifikation Verhaltens-Identität der 3 Services**
   - Test-Helper baut einen `ShipmentOrder` + `DhlBookingOptions` mit definierter Service-Liste.
   - Ruft sequenziell: `DhlPriceQuoteService::quote()` (Gateway-Stub), `DhlShipmentBookingService::bookShipment()` (Gateway-Stub), `DhlBulkBookingService::bookBulk([orderId], …)` (Gateway-Stub).
   - Assertion: Die in den Gateway-Stub eingehenden `additionalServices`-Blöcke sind **structurally equal** über alle drei Pfade.
   - Wird für jeden AC-relevanten Service-Mix wiederholt (matrix-Test).

6. **Mutation-Testing-Hinweis**
   - `infection` (oder vergleichbar) auf den Mapper-Klassen — Pflicht-Mindestquote 80% Mutation Score auf der `validate*`-Familie.

**Negativ-Tests im Bulk-Pfad (Edge-Case "gemischte Fehler"):**
- 100 Sendungen, 3 mit `UnknownDhlServiceException`, 2 mit `MissingRequiredDhlServiceException` → Result: 95 success, 5 failed mit konkretem Exception-Klassennamen pro Fehlereintrag.

---

### 8. Schichten-Compliance (Check gegen §8 / §64)

- **Domain:** Nur `RoutingContext` und (bereits aus PROJ-1) Service-/Assignment-VOs + Repository-**Interfaces**. Kein Framework-Import.
- **Application:** `DhlAdditionalServiceMapper` + Exceptions. Hängt **nur** an Domain-Interfaces, `LoggerInterface`, `ConfigRepository`. Keine Eloquent-, keine HTTP-, keine Gateway-Bezüge.
- **Infrastructure:** Repository-Implementierungen (aus PROJ-1) — unverändert.
- **Presentation:** Keine Änderungen. HTTP-Mapping der Exceptions geschieht im bestehenden Exception-Handler-Layer.

**Codereview-Checkliste (Pflicht vor Merge):**
1. Kein direkter `$services->toArray()` mehr in den drei Services oder im `DhlPayloadAssembler`.
2. Mapper importiert **keine** Klasse aus `Illuminate\Database`, `Illuminate\Http`, `App\Infrastructure`.
3. Alle Validierungs-Exceptions erben von `DhlAdditionalServiceMappingException`.
4. Snapshot-Fixtures wurden manuell reviewt.
5. Mutation-Score Mapper ≥80% auf den Validierungsbranches.

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_

---
```yaml
task: t4
goal: GOAL-2026-05-12T124024-dhlcat
agent: solution-architect
status: completed
artifact: features/PROJ-3-dhl-additional-service-mapper.md
section: "Tech Design (Solution Architect)"
date: 2026-05-12
```
