# PROJ-1: DHL Catalog — Domain & Persistence

## Status: Planned
**Created:** 2026-05-12
**Last Updated:** 2026-05-12

## Dependencies
- None (Fundament der Initiative)

## Kontext
Heute existiert kein zentrales Datenmodell für DHL-Freight-Produkte und Additional Services. Der String `dhl_product_id` wird als Freitext in `fulfillment_freight_profiles` gepflegt, alle erlaubten Additional Services sind in keiner Tabelle hinterlegt. Folge: Tippfehler in Produktcodes, keine Validierung welche Services für welches Produkt+Routing+Payer erlaubt sind, keine fachliche Wahrheit über Service-Parameter (COD-Betrag, Avisierung etc.).

Dieses Feature legt das Fundament: Domain-Modell, Repository-Interfaces, Datenbank-Tabellen, Audit-Logging — ohne dass irgendein Aufrufer (Sync-Job, Mapper, UI) das Modell schon nutzt. Damit ist es isoliert testbar und deploybar.

## User Stories
- Als **Architekt** möchte ich ein klar geschnittenes Domain-Modell für DHL-Produkte und Additional Services haben, damit Mapping- und Validierungslogik konsistent an einer Stelle leben kann.
- Als **Backend-Entwickler** möchte ich Produkte, Services und Produkt-Service-Zuordnungen über typsichere Repository-Interfaces laden können, ohne mit Raw-Queries in Application/Domain-Code zu hantieren.
- Als **Compliance/Revision** möchte ich nachvollziehen können, welche Katalog-Einträge wann durch wen (System-Sync oder Migration) verändert wurden — für mindestens 12 Monate revisionssicher.
- Als **Tester** möchte ich Domain-Invarianten (z.B. „Service-Parameter müssen gegen JSON-Schema validierbar sein") ohne Datenbank oder Framework testen können.

## Acceptance Criteria

### Domain-Schicht (`app/Domain/Fulfillment/Shipping/Dhl/Catalog/`)
- [ ] **Entity `DhlProduct`** (Aggregate Root) existiert mit Feldern: `code` (Value Object `DhlProductCode`, bereits vorhanden), `name`, `description`, `marketAvailability` (Enum B2B/B2C), `fromCountries` (Liste ISO-3166-1-alpha-2), `toCountries` (Liste), `allowedPackageTypes` (Liste `DhlPackageType`), `weightLimits` (min/max kg), `dimensionLimits` (max L/B/H cm), `validFrom`, `validUntil`, `deprecatedAt` (nullable), `replacedByCode` (nullable `DhlProductCode`), `source` (Enum: `seed|api|manual`), `syncedAt` (nullable).
- [ ] **Entity `DhlAdditionalService`** existiert mit Feldern: `code`, `name`, `description`, `category` (Enum: `pickup|delivery|notification|dangerous_goods|special`), `parameterSchema` (Value Object `JsonSchema`), `deprecatedAt`, `source`, `syncedAt`.
- [ ] **Entity `DhlProductServiceAssignment`** modelliert die N:M-Beziehung Produkt↔Service inkl. Routing-Kontext mit Feldern: `productCode`, `serviceCode`, `fromCountry` (nullable = global), `toCountry` (nullable = global), `payerCode` (nullable = global, sonst `DhlPayerCode`), `requirement` (Enum: `allowed|required|forbidden`), `defaultParameters` (JSON, validiert gegen Service-Schema), `source`, `syncedAt`.
- [ ] **Value Object `JsonSchema`** kapselt ein valides JSON-Schema (Draft 2020-12 Subset: `type`, `properties`, `required`, `enum`, `minimum`, `maximum`, `format`) und kann beliebige Parameter-Arrays gegen sich selbst validieren. Wirft `InvalidParameterException` bei Verstoß.
- [ ] **Repository-Interfaces** in `Domain/`: `DhlProductRepository`, `DhlAdditionalServiceRepository`, `DhlProductServiceAssignmentRepository` mit fachlich benannten Methoden (`findByCode`, `findActiveByRouting`, `findAllowedServicesFor(productCode, fromCountry, toCountry, payerCode)`, `save`, `softDeprecate`, `findDeprecatedSince`).
- [ ] Domain-Schicht hat **keine** Eloquent-/Laravel-/Supabase-Imports — verifiziert per statischer Prüfung im Test.
- [ ] Domain-Invarianten als PHPUnit-Tests ohne DB: `validUntil > validFrom`, `replacedByCode` darf nicht auf sich selbst zeigen, `defaultParameters` muss gegen `parameterSchema` validieren, `deprecatedAt` setzt `replacedByCode` nicht zwingend voraus.

### Persistence-Schicht (`app/Infrastructure/Persistence/Dhl/Catalog/`)
- [ ] **Migration** erzeugt drei Tabellen:
  - `dhl_products` (PK `code` varchar(8), Felder gemäß Entity, JSON für Listen, `source` enum, `synced_at` timestamp nullable, `deprecated_at` timestamp nullable, `replaced_by_code` nullable FK auf `dhl_products.code` ON DELETE SET NULL)
  - `dhl_additional_services` (PK `code` varchar(8), `parameter_schema` JSON, `category` enum, sonst analog)
  - `dhl_product_service_assignments` (composite unique key (`product_code`, `service_code`, `from_country`, `to_country`, `payer_code`), `requirement` enum, `default_parameters` JSON nullable, FKs auf `dhl_products.code` und `dhl_additional_services.code` ON DELETE CASCADE)
- [ ] **Eloquent-Modelle** im Infrastructure-Layer mit explizitem `$casts` (JSON-Spalten → typisierte VOs/Arrays), **keine** fachliche Logik in den Modellen.
- [ ] **Mapper** (`DhlCatalogPersistenceMapper`) übersetzt explizit zwischen Eloquent-Modell und Domain-Entity. Kein automatisches Spread/Hydrate.
- [ ] **Repository-Implementierungen** erfüllen alle Interface-Methoden, geben Domain-Entities (nicht Eloquent-Modelle) zurück.
- [ ] **Indexes**: `dhl_products(deprecated_at)`, `dhl_product_service_assignments(product_code, from_country, to_country, payer_code)`.

### Audit-Log (`dhl_catalog_audit_log`)
- [ ] Eigene Tabelle `dhl_catalog_audit_log` mit Feldern: `id`, `entity_type` (Enum: `product|service|assignment`), `entity_key` (composite-string für assignment), `action` (Enum: `created|updated|deprecated|restored`), `actor` (string, z.B. `system:dhl-sync` oder `user:<id>`), `diff` (JSON: before/after), `created_at`.
- [ ] **Index** auf `(entity_type, entity_key, created_at desc)` für effizientes Lesen pro Eintrag.
- [ ] **Retention**: Einträge älter als 12 Monate dürfen gelöscht werden — Cleanup-Befehl ist NICHT Teil dieses Features (Out-of-Scope, kommt in PROJ-2 oder später).
- [ ] **Application Service** `DhlCatalogAuditLogger` (nicht Domain!) schreibt Audit-Einträge transaktional zusammen mit der eigentlichen Persistierung. Repository-Methoden `save`/`softDeprecate` akzeptieren einen `actor`-Kontext.

### Konfiguration & Seeds (leere Hülle)
- [ ] `config/dhl-catalog.php` enthält Konstanten: `default_countries`, `default_payer_codes`, `sync_retention_days`. **Keine** Produktcodes als Magic-Values im Anwendungscode.
- [ ] Leerer Seeder `DhlCatalogSeeder` mit Lade-Funktion für JSON-Fixture-Dateien aus `database/seeders/data/dhl/`. Pfad existiert, ist aber leer (Fixtures liefert PROJ-2).
- [ ] Migrations laufen idempotent rückwärts (`down()`) und vorwärts.

## Edge Cases
- **Datenbank ohne Fixtures**: Nach Migration ist der Katalog leer. Alle Repository-Calls geben leere Listen zurück. Keine Exception. Aufrufende Schichten (PROJ-2/3/4/5) müssen damit umgehen.
- **Zirkuläre Deprecation**: Versuch, ein Produkt durch sich selbst zu ersetzen (`replacedByCode === code`) → Domain-Exception `InvalidProductSuccessorException` beim Setzen.
- **Globale vs. routing-spezifische Assignments**: Wenn `from_country=NULL` UND eine spezifische Zuordnung für `from_country='DE'` existiert, gewinnt die spezifische. Resolver-Logik im Repository (`findAllowedServicesFor`) testet beide Fälle.
- **JSON-Schema-Schema-Drift**: Wenn DHL künftig Schema-Konstrukte liefert, die `JsonSchema`-VO nicht versteht (z.B. `oneOf`) → VO wirft `UnsupportedJsonSchemaFeatureException`. PROJ-2 fängt diese als Sync-Fehler.
- **Lange Service-Codes**: Aktuelle DHL-Codes sind 1–3 Zeichen, aber zur Sicherheit varchar(8) (analog Produkt). Tests verifizieren Annahme nicht.
- **Soft-deprecated Produkt mit aktiven Assignments**: Deprecation kaskadiert NICHT — Assignments bleiben unverändert. Anzeige (PROJ-6) zeigt sie als „über deprecated Produkt verknüpft".
- **Concurrent Sync vs. Read**: Sync läuft in Transaktion; Lese-Queries sehen entweder vollständig alten oder vollständig neuen Stand pro Tabelle (PostgreSQL Default-Isolation reicht).
- **Restore eines deprecated Eintrags**: Sync findet den Code wieder im API-Output → `deprecatedAt=null`, Audit-Eintrag `action=restored`. `replaced_by_code` wird gelöscht.

## Technical Requirements
- **Schichtung (Engineering-Handbuch §3–§8)**: Domain kennt keine Infrastructure-Imports; Repository-Implementierungen liegen in `Infrastructure/Persistence/Dhl/Catalog/`; Audit-Logger in `Application/Fulfillment/Integrations/Dhl/Catalog/`.
- **Sicherheit (§19, §21)**: Mandantentrennung ist hier irrelevant (Katalog ist global pro Installation), aber Audit-`actor` muss ein deterministischer String sein (kein Roh-`auth()->user()` in Domain). Sync-Token/Credentials NICHT in `dhl_products` ablegen.
- **Datenzugriff (§10–§13)**: Application-Code greift ausschließlich über die drei Repository-Interfaces zu, niemals direkt auf Eloquent oder DB-Query-Builder.
- **DRY/SOLID**: Bestehende VOs `DhlProductCode`, `DhlPackageType`, `DhlPayerCode` werden wiederverwendet (keine Duplikate). Neue VOs nur für `JsonSchema`, `DhlServiceCategory`, `DhlServiceRequirement`, `DhlCatalogSource`.
- **Performance**: `findAllowedServicesFor` muss mit einem einzigen Join + WHERE auskommen (kein N+1). Bei 30 Ländern × 50 Produkten × 20 Services ≈ 30k Assignment-Rows muss die Query <50 ms liefern.
- **Testing**: ≥90% Coverage auf Domain-Schicht (reine Unit-Tests ohne DB). Feature-Tests für Repository-Implementierungen gegen echte PostgreSQL (kein SQLite-Hack).
- **Reversibilität**: Migration vollständig rückwärtsfähig — `down()` löscht alle drei Tabellen + Audit-Log inkl. FKs sauber.

## Out of Scope (kommt in Folge-Features)
- Sync-Job mit DHL-API (PROJ-2)
- JSON-Fixtures mit echten Sandbox-Daten (PROJ-2)
- Nutzung des Modells in Mapping-Code (PROJ-3)
- UI (PROJ-4, PROJ-5, PROJ-6)
- Audit-Log-Retention-Cleanup-Befehl (späteres Housekeeping-Feature)

---
<!-- Sections below are added by subsequent skills -->

## Tech Design (Solution Architect)

### 1. Bounded Context & Folder-Layout

Neuer Subkontext **`Fulfillment / Shipping / Dhl / Catalog`** unterhalb des bestehenden DHL-Kontextes. Der Katalog ist ein **eigenes Aggregat-Cluster** (drei Aggregate: Product, AdditionalService, Assignment) — bewusst getrennt von `Configuration/` (Mandanten-Settings) und `ValueObjects/` (Transport-VOs).

```
app/
├── Domain/Fulfillment/Shipping/Dhl/
│   ├── ValueObjects/                              [BESTEHEND — wiederverwenden]
│   │   ├── DhlProductCode.php                     ← wiederverwendet
│   │   ├── DhlPackageType.php                     ← wiederverwendet
│   │   └── DhlPayerCode.php                       ← wiederverwendet
│   └── Catalog/                                   [NEU]
│       ├── DhlProduct.php                         (Entity, Aggregate Root)
│       ├── DhlAdditionalService.php               (Entity, Aggregate Root)
│       ├── DhlProductServiceAssignment.php        (Entity, Aggregate Root)
│       ├── ValueObjects/
│       │   ├── JsonSchema.php                     (VO, immutable)
│       │   ├── DhlServiceCategory.php             (PHP 8.1 Enum)
│       │   ├── DhlServiceRequirement.php          (PHP 8.1 Enum)
│       │   ├── DhlCatalogSource.php               (PHP 8.1 Enum)
│       │   ├── DhlMarketAvailability.php          (PHP 8.1 Enum: B2B/B2C/BOTH)
│       │   ├── WeightLimits.php                   (VO: minKg/maxKg)
│       │   ├── DimensionLimits.php                (VO: maxLengthCm/maxWidthCm/maxHeightCm)
│       │   ├── CountryCode.php                    (VO: ISO-3166-1 alpha-2)
│       │   └── AuditActor.php                     (VO: typed actor string)
│       ├── Repositories/
│       │   ├── DhlProductRepository.php           (Interface)
│       │   ├── DhlAdditionalServiceRepository.php (Interface)
│       │   └── DhlProductServiceAssignmentRepository.php (Interface)
│       └── Exceptions/
│           ├── InvalidParameterException.php
│           ├── InvalidProductSuccessorException.php
│           └── UnsupportedJsonSchemaFeatureException.php
│
├── Application/Fulfillment/Integrations/Dhl/Catalog/
│   └── DhlCatalogAuditLogger.php                  (Application Service)
│
└── Infrastructure/Persistence/Dhl/Catalog/
    ├── Eloquent/
    │   ├── DhlProductModel.php
    │   ├── DhlAdditionalServiceModel.php
    │   ├── DhlProductServiceAssignmentModel.php
    │   └── DhlCatalogAuditLogModel.php
    ├── Mappers/
    │   └── DhlCatalogPersistenceMapper.php
    └── Repositories/
        ├── EloquentDhlProductRepository.php
        ├── EloquentDhlAdditionalServiceRepository.php
        └── EloquentDhlProductServiceAssignmentRepository.php
```

**Schicht-Regel:** Domain → keine Imports aus `Application/`, `Infrastructure/`, `Illuminate/*`, `Eloquent`. Verifiziert per Architekturtest (Deptrac/PHPArkitect oder einfacher PHPUnit-Reflection-Test, siehe §6).

---

### 2. Domain-Klassen — Skelette (kein Implementations-Code)

#### 2.1 Entity `DhlProduct` (Aggregate Root)

**Properties (alle `private readonly` außer mutierbarer State):**
- `DhlProductCode $code` — Identität
- `string $name`, `string $description`
- `DhlMarketAvailability $marketAvailability`
- `array<CountryCode> $fromCountries`, `array<CountryCode> $toCountries`
- `array<DhlPackageType> $allowedPackageTypes`
- `WeightLimits $weightLimits`, `DimensionLimits $dimensionLimits`
- `DateTimeImmutable $validFrom`, `?DateTimeImmutable $validUntil`
- `?DateTimeImmutable $deprecatedAt` (mutable über Methode)
- `?DhlProductCode $replacedByCode` (mutable über Methode)
- `DhlCatalogSource $source`
- `?DateTimeImmutable $syncedAt`

**Konstruktor-Signatur:** Named-arg-Konstruktor, validiert Invarianten **bei Erzeugung** (Fail-Fast §67):
- `validUntil > validFrom` → sonst `\InvalidArgumentException`
- `fromCountries` und `toCountries` non-empty
- `weightLimits.min >= 0` und `max > min`

**Public Methods:**
- `code(): DhlProductCode` und alle weiteren Getter (Liste explizit, keine Magic).
- `deprecate(?DhlProductCode $successor, DateTimeImmutable $at): void` — wirft `InvalidProductSuccessorException` wenn `$successor?->equals($this->code)`.
- `restore(): void` — setzt `deprecatedAt = null`, `replacedByCode = null`.
- `isDeprecated(): bool`
- `isValidAt(DateTimeImmutable $moment): bool` — kombiniert `validFrom/validUntil/deprecatedAt`.
- `supportsRoute(CountryCode $from, CountryCode $to): bool`
- `markSynced(DateTimeImmutable $at): void`
- `equals(self $other): bool` — Identitätsvergleich über `code`.

**Begründung Aggregate Root:** Successor-Beziehung ist eine domain invariant des Produkts selbst — gehört nicht in einen separaten Service.

#### 2.2 Entity `DhlAdditionalService` (Aggregate Root)

**Properties:**
- `string $code` (eigener simple-String, Länge 1–8; bewusst keine VO, da Service-Codes nicht so semantisch geladen sind wie Produktcodes — Eintrag in Edge Cases nicht annehmen)
- `string $name`, `string $description`
- `DhlServiceCategory $category`
- `JsonSchema $parameterSchema`
- `?DateTimeImmutable $deprecatedAt`
- `DhlCatalogSource $source`
- `?DateTimeImmutable $syncedAt`

**Public Methods:**
- Getter (explizit).
- `validateParameters(array $parameters): void` — delegiert an `$parameterSchema->validate()`, wirft `InvalidParameterException`.
- `deprecate(DateTimeImmutable $at): void` / `restore(): void`.
- `markSynced(DateTimeImmutable $at): void`.

#### 2.3 Entity `DhlProductServiceAssignment` (Aggregate Root)

**Properties:**
- `DhlProductCode $productCode`
- `string $serviceCode`
- `?CountryCode $fromCountry`, `?CountryCode $toCountry` (null = global)
- `?DhlPayerCode $payerCode` (null = global)
- `DhlServiceRequirement $requirement`
- `array $defaultParameters`
- `DhlCatalogSource $source`
- `?DateTimeImmutable $syncedAt`

**Konstruktor-Invarianten:** Wenn `defaultParameters` nicht leer ist, wird Validierung **gegen Service** über statische Factory `DhlProductServiceAssignment::create(..., JsonSchema $serviceSchema)` erzwungen — andernfalls `InvalidParameterException`.

**Public Methods:**
- Getter, `matches(DhlProductCode, CountryCode $from, CountryCode $to, DhlPayerCode $payer): bool` (Spezifität-Resolver-Helper für Repository), `specificity(): int` (0 = global, +1 pro non-null Routing-Dim — Tiebreaker), `markSynced(...)`.

#### 2.4 Value Object `JsonSchema`

**Eigenschaften:**
- `array $schema` (immutable)
- Statische Factory: `JsonSchema::fromArray(array $raw): self` — wirft `UnsupportedJsonSchemaFeatureException` bei unbekannten Konstrukten (`oneOf`, `anyOf`, `allOf`, `$ref`, `if/then/else`).
- Whitelist erlaubter Keys: `type`, `properties`, `required`, `enum`, `minimum`, `maximum`, `format`, `items`, `additionalProperties`.

**Public Methods:**
- `validate(array $parameters): void` — wirft `InvalidParameterException` mit Pfad.
- `toArray(): array`
- `equals(self $other): bool`

**Begründung:** Eigene Implementierung statt `justinrainbow/json-schema` Library, um die Whitelist eng zu halten (KISS + Sicherheit — kein offener Schema-Surface, der DHL-Drift unkontrolliert durchwinkt).

#### 2.5 Enums

```
DhlServiceCategory: PICKUP | DELIVERY | NOTIFICATION | DANGEROUS_GOODS | SPECIAL
DhlServiceRequirement: ALLOWED | REQUIRED | FORBIDDEN
DhlCatalogSource: SEED | API | MANUAL
DhlMarketAvailability: B2B | B2C | BOTH
```

Alle als PHP-8.1-`enum: string` mit `tryFrom`/`from`-Semantik.

#### 2.6 Domain-Exceptions

Alle erben von einer neuen `DhlCatalogException extends \DomainException` (lokal in `Catalog/Exceptions/`), keine HTTP-Codes, keine Framework-Bezüge.

- `InvalidParameterException(string $path, string $reason)` — JSON-Pointer-Pfad + Klartext.
- `InvalidProductSuccessorException(DhlProductCode $code)`.
- `UnsupportedJsonSchemaFeatureException(string $unsupportedKey)`.

#### 2.7 Repository-Interfaces (vollständige Signaturen)

**`DhlProductRepository`:**
```
findByCode(DhlProductCode $code): ?DhlProduct
findAllActive(DateTimeImmutable $at): iterable<DhlProduct>
findDeprecatedSince(DateTimeImmutable $since): iterable<DhlProduct>
save(DhlProduct $product, AuditActor $actor): void
softDeprecate(DhlProductCode $code, ?DhlProductCode $successor, AuditActor $actor): void
restore(DhlProductCode $code, AuditActor $actor): void
existsByCode(DhlProductCode $code): bool
```

**`DhlAdditionalServiceRepository`:**
```
findByCode(string $serviceCode): ?DhlAdditionalService
findAllActive(): iterable<DhlAdditionalService>
findByCategory(DhlServiceCategory $category): iterable<DhlAdditionalService>
save(DhlAdditionalService $service, AuditActor $actor): void
softDeprecate(string $serviceCode, AuditActor $actor): void
restore(string $serviceCode, AuditActor $actor): void
```

**`DhlProductServiceAssignmentRepository`:**
```
findAllowedServicesFor(
    DhlProductCode $product,
    CountryCode $from,
    CountryCode $to,
    DhlPayerCode $payer
): iterable<DhlProductServiceAssignment>
findByProduct(DhlProductCode $product): iterable<DhlProductServiceAssignment>
save(DhlProductServiceAssignment $assignment, AuditActor $actor): void
delete(DhlProductServiceAssignment $assignment, AuditActor $actor): void
```

`findAllowedServicesFor` macht den Spezifitäts-Resolve auf SQL-Ebene (1 Query, ORDER BY specificity DESC, DISTINCT ON service_code) — siehe §3 Indexes.

**Kein Repository gibt jemals Eloquent-Modelle, QueryBuilder oder Arrays zurück.** Methoden sind fachlich benannt — kein `query()`, `where()`, `get()`.

---

### 3. Migrations-DDL (Pseudo-SQL, PostgreSQL)

**Migration-Datei:** `database/migrations/2026_05_12_120000_create_dhl_catalog_tables.php`. Eine einzige Migration für alle fünf Tabellen — atomar reversibel.

#### `dhl_products`
```
code              varchar(8)    PRIMARY KEY
name              varchar(200)  NOT NULL
description       text          NOT NULL DEFAULT ''
market_avail      varchar(8)    NOT NULL                    -- enum
from_countries    jsonb         NOT NULL                    -- array<ISO2>
to_countries      jsonb         NOT NULL
allowed_package_types jsonb     NOT NULL
weight_min_kg     numeric(8,3)  NOT NULL
weight_max_kg     numeric(8,3)  NOT NULL
dim_max_length_cm numeric(8,2)  NOT NULL
dim_max_width_cm  numeric(8,2)  NOT NULL
dim_max_height_cm numeric(8,2)  NOT NULL
valid_from        timestamptz   NOT NULL
valid_until       timestamptz   NULL
deprecated_at     timestamptz   NULL
replaced_by_code  varchar(8)    NULL REFERENCES dhl_products(code) ON DELETE SET NULL
source            varchar(8)    NOT NULL
synced_at         timestamptz   NULL
created_at        timestamptz   NOT NULL DEFAULT now()
updated_at        timestamptz   NOT NULL DEFAULT now()

INDEX idx_dhl_products_deprecated_at ON (deprecated_at)
INDEX idx_dhl_products_valid_window ON (valid_from, valid_until)
CHECK (weight_max_kg > weight_min_kg)
CHECK (replaced_by_code IS NULL OR replaced_by_code <> code)   -- DB-side safety
```

#### `dhl_additional_services`
```
code              varchar(8)    PRIMARY KEY
name              varchar(200)  NOT NULL
description       text          NOT NULL DEFAULT ''
category          varchar(24)   NOT NULL                    -- enum
parameter_schema  jsonb         NOT NULL
deprecated_at     timestamptz   NULL
source            varchar(8)    NOT NULL
synced_at         timestamptz   NULL
created_at, updated_at  (timestamptz, default now())

INDEX idx_dhl_services_deprecated_at ON (deprecated_at)
INDEX idx_dhl_services_category ON (category)
```

#### `dhl_product_service_assignments`
```
id                bigserial     PRIMARY KEY
product_code      varchar(8)    NOT NULL REFERENCES dhl_products(code) ON DELETE CASCADE
service_code      varchar(8)    NOT NULL REFERENCES dhl_additional_services(code) ON DELETE CASCADE
from_country      varchar(2)    NULL                        -- NULL = global
to_country        varchar(2)    NULL
payer_code        varchar(8)    NULL
requirement       varchar(16)   NOT NULL                    -- enum
default_parameters jsonb        NULL
source            varchar(8)    NOT NULL
synced_at         timestamptz   NULL
created_at, updated_at

UNIQUE (product_code, service_code, from_country, to_country, payer_code)
                                            -- NULL ist in PG distinct → bewusst akzeptiert,
                                            -- composite-key zusätzlich via partial unique index abgesichert:
INDEX idx_assignment_lookup
       ON (product_code, from_country, to_country, payer_code)
INDEX idx_assignment_service ON (service_code)
```

**Hinweis NULL-in-UNIQUE:** Wir ergänzen einen partiellen Unique-Index, der `COALESCE(from_country, '*')`, `COALESCE(to_country, '*')`, `COALESCE(payer_code, '*')` als Schlüssel verwendet — verhindert echte Duplikate trotz NULL-Semantik.

#### `dhl_catalog_audit_log`
```
id            bigserial     PRIMARY KEY
entity_type   varchar(16)   NOT NULL                        -- enum: product|service|assignment
entity_key    varchar(64)   NOT NULL                        -- product.code | service.code | assignment.id-composite
action        varchar(16)   NOT NULL                        -- enum: created|updated|deprecated|restored|deleted
actor         varchar(128)  NOT NULL                        -- z.B. system:dhl-sync | user:42
diff          jsonb         NOT NULL                        -- {"before": ..., "after": ...}
created_at    timestamptz   NOT NULL DEFAULT now()

INDEX idx_audit_lookup ON (entity_type, entity_key, created_at DESC)
INDEX idx_audit_actor_time ON (actor, created_at DESC)
```

#### `dhl_catalog_sync_status` (Placeholder für PROJ-2)
```
id            bigserial     PRIMARY KEY
sync_type     varchar(32)   NOT NULL                        -- products|services|assignments
last_run_at   timestamptz   NULL
last_success_at timestamptz NULL
last_error    text          NULL
items_synced  integer       NOT NULL DEFAULT 0

UNIQUE (sync_type)
```
Bewusst leer angelegt — PROJ-2 schreibt zuerst hinein. Strukturell hier, weil DDL-Refactoring später teurer wäre als 5 Spalten jetzt zu antizipieren (Ausnahme zu YAGNI, begründet durch Migrations-Kosten).

**`down()`-Reihenfolge:** `sync_status → audit_log → assignments → services → products` (FK-konform).

---

### 4. Eloquent-Modelle

Jedes Modell ist ein **dünner Persistenz-Repräsentant** — keine Fachlogik, keine Scopes mit fachlichem Namen, keine `boot()`-Hooks für Domain-Events.

**`DhlProductModel`:**
- `$table = 'dhl_products'`, `$primaryKey = 'code'`, `$keyType = 'string'`, `$incrementing = false`
- `$casts`:
  ```
  from_countries        => 'array'
  to_countries          => 'array'
  allowed_package_types => 'array'
  weight_min_kg         => 'decimal:3'
  weight_max_kg         => 'decimal:3'
  dim_max_length_cm     => 'decimal:2'  (analog width/height)
  valid_from            => 'immutable_datetime'
  valid_until           => 'immutable_datetime'
  deprecated_at         => 'immutable_datetime'
  synced_at             => 'immutable_datetime'
  ```
- Keine Relations — Joins macht das Repository explizit per Query Builder (innerhalb Persistenz-Schicht erlaubt).

**`DhlAdditionalServiceModel`:** `parameter_schema => 'array'`, sonst analog.

**`DhlProductServiceAssignmentModel`:** `default_parameters => 'array'`, Standard-Auto-Increment-PK.

**`DhlCatalogAuditLogModel`:** `diff => 'array'`, `created_at => 'immutable_datetime'`, **kein `updated_at`** (Audit ist immutable — append-only).

---

### 5. `DhlCatalogPersistenceMapper` — Methoden-Signaturen

Eine Klasse, **stateless**, keine Dependencies. Konvertiert in beide Richtungen ohne automatisches Spread/Hydrate.

```
toProductEntity(DhlProductModel $m): DhlProduct
toProductModel(DhlProduct $e, ?DhlProductModel $existing = null): DhlProductModel
toServiceEntity(DhlAdditionalServiceModel $m): DhlAdditionalService
toServiceModel(DhlAdditionalService $e, ?DhlAdditionalServiceModel $existing = null): DhlAdditionalServiceModel
toAssignmentEntity(DhlProductServiceAssignmentModel $m): DhlProductServiceAssignment
toAssignmentModel(DhlProductServiceAssignment $e, ?DhlProductServiceAssignmentModel $existing = null): DhlProductServiceAssignmentModel
```

- `$existing`-Parameter ist optional, um beim Update auf bestehendem Eloquent-Record zu arbeiten (kein neues Insert).
- JSON-Felder werden explizit gemappt: VO-Listen ↔ `string[]`.
- Jeder Mapper-Pfad ist ein expliziter Methodenaufruf (kein `array_combine`, kein Reflection).

---

### 6. `DhlCatalogAuditLogger` (Application Service)

**Verantwortung:** Audit-Eintrag schreiben **transaktional** zusammen mit Repository-Schreibvorgang. Wird **nicht** von Domain aufgerufen; das Repository wrapt den Audit-Call innerhalb seiner Transaktion.

**API:**
```
recordProductChange(
    string $action,                  // 'created'|'updated'|'deprecated'|'restored'
    DhlProductCode $code,
    ?DhlProduct $before,
    ?DhlProduct $after,
    AuditActor $actor
): void

recordServiceChange(...analog für Service...)
recordAssignmentChange(...analog für Assignment, entity_key = composite-string...)
```

**Transaktionales Verhalten:**
- Repository-Implementierung umschließt `save()`-Aufruf in `DB::transaction(function() { ... })`.
- Innerhalb der Transaktion: Mapper → Model->save() → `auditLogger->record...()` (schreibt nur in `dhl_catalog_audit_log`-Tabelle).
- Bei Exception in beliebigem Schritt → Rollback. Kein Audit ohne Datenänderung, keine Datenänderung ohne Audit.
- **Idempotenz:** Wenn `before == after` (Diff leer), kein Audit-Eintrag (verhindert Sync-Spam).
- `diff` enthält genau die geänderten Properties (Shallow-Diff auf Entity-Property-Ebene; JSON-Subbäume werden als Ganzes verglichen — KISS).

**`AuditActor`-VO:** Typ-sicher, Konstruktor akzeptiert `'system:'`- oder `'user:'`-Präfix. Domain stellt deterministischen String sicher (kein `auth()->user()` in Domain — siehe §19 Engineering-Handbuch).

---

### 7. Test-Strategie

#### 7.1 Coverage-Ziel
- **Domain-Schicht (`app/Domain/Fulfillment/Shipping/Dhl/Catalog/`):** ≥ 90 % Line-Coverage, **ausschließlich Unit-Tests ohne DB/Framework**.
- **Persistence + Mapper + AuditLogger:** Feature-Tests gegen echte PostgreSQL (kein SQLite, da `jsonb`/`tstz` SQLite-inkompatibel).

#### 7.2 Test-Klassen (PHPUnit)

**Domain (unter `tests/Unit/Domain/Fulfillment/Shipping/Dhl/Catalog/`):**

| Test-Klasse                                  | Edge Cases                                                                                                                                                       |
|----------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `DhlProductTest`                             | construct OK; `validUntil <= validFrom` rejects; `deprecate(self)` → `InvalidProductSuccessorException`; `restore()` clears successor; `isValidAt()` window check; `supportsRoute()` symmetric/asymmetric. |
| `DhlAdditionalServiceTest`                   | construct OK; `validateParameters` happy + schema-violation; `deprecate`/`restore` idempotent.                                                                   |
| `DhlProductServiceAssignmentTest`            | construct OK; `defaultParameters` invalid gegen Schema → `InvalidParameterException`; `matches()` Wahrheitstabelle (global vs. spezifisch); `specificity()` 0..3. |
| `JsonSchemaTest`                             | `fromArray` Whitelist; `oneOf`/`anyOf`/`$ref` → `UnsupportedJsonSchemaFeatureException`; `validate` (required/enum/min/max/format/nested properties); JSON-Pointer-Pfad in Exception. |
| `WeightLimitsTest`, `DimensionLimitsTest`, `CountryCodeTest`, `AuditActorTest` | Format-Validation, `equals`, Immutability.                                                                       |
| `DhlCatalogDomainIsolationTest`              | **Architektur-Test:** scannt alle Dateien unter `Domain/Fulfillment/Shipping/Dhl/Catalog/` per Reflection/Regex, asserted dass keine `use Illuminate\…`, `use App\Infrastructure\…`, `use App\Application\…`-Statements vorkommen. Schützt §3–§8 dauerhaft. |

**Infrastructure (unter `tests/Feature/Infrastructure/Persistence/Dhl/Catalog/`):**

| Test-Klasse                                            | Edge Cases                                                                                                                                |
|--------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------|
| `EloquentDhlProductRepositoryTest`                     | save→findByCode-Roundtrip; `softDeprecate` setzt `deprecated_at`; `replaced_by_code` FK SET NULL on delete; `findDeprecatedSince` window. |
| `EloquentDhlAdditionalServiceRepositoryTest`           | save+find+findByCategory+softDeprecate+restore.                                                                                            |
| `EloquentDhlProductServiceAssignmentRepositoryTest`    | `findAllowedServicesFor` — Resolver-Tabelle: nur global / nur spezifisch / beide → spezifisch gewinnt; FORBIDDEN überschreibt global ALLOWED; Performance-Smoke (≤ 50 ms bei 30k Rows mit Index, EXPLAIN Index-Scan asserten). |
| `DhlCatalogPersistenceMapperTest`                      | Roundtrip Entity↔Model für alle drei Aggregate; JSON-Casts; Null-Handling (deprecated/synced).                                            |
| `DhlCatalogAuditLoggerTest`                            | Transaktions-Rollback: provozierter Fehler im `model->save()` → kein Audit-Row; Diff-leer → kein Audit-Row; `entity_key` composite-format für Assignment; immutable audit (kein update path). |
| `DhlCatalogMigrationTest`                              | `migrate:fresh` + `migrate:rollback` rundläuft; alle FKs/Indexes existieren (Schema-Introspection).                                       |

**Begründung Architektur-Test:** §3–§8 sind statisch verifizierbar — der Test verhindert, dass spätere Features versehentlich Eloquent in Domain ziehen.

---

### 8. Konfiguration & Seed-Hülle

**`config/dhl-catalog.php`:**
```
default_countries: ['DE','AT','CH','NL','BE','LU','FR','IT','ES','PL']
default_payer_codes: ['DAP','DDP']           // enum-aligned mit DhlPayerCode
sync_retention_days: 365
audit_retention_days: 365
```

**Seeder:** `database/seeders/DhlCatalogSeeder.php` mit Methode `loadFromFixtures(string $dir): void`. `database/seeders/data/dhl/.gitkeep` als leeres Verzeichnis. Konkrete JSON-Fixtures kommen in PROJ-2.

---

### 9. Wiederverwendung (DRY) — explizite Übersicht

| Bestehend                                                                       | Verwendung                              |
|---------------------------------------------------------------------------------|-----------------------------------------|
| `DhlProductCode` (`…/ValueObjects/DhlProductCode.php`)                          | Identität in `DhlProduct`, FK-Bezug      |
| `DhlPackageType` (`…/ValueObjects/DhlPackageType.php`)                          | `allowedPackageTypes`-Listenelement     |
| `DhlPayerCode` (`…/ValueObjects/DhlPayerCode.php`)                              | Assignment-Routing                       |
| `DhlValueObjectException` (`…/ValueObjects/Exceptions/`)                        | Basisklasse erweitern für VO-Fehler im Catalog (`InvalidParameterException` erbt davon) |

**Keine Duplikate** dieser Typen werden im Catalog-Subkontext angelegt.

## QA Test Results
_To be added by /qa_

## Deployment
_To be added by /deploy_
