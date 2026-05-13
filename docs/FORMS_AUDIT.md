# FORMS_AUDIT
**Stand:** 2026-05-13 · 28 FormRequest-Klassen + 2 Traits

## 1) Inventur

| Kontext | Klassen | Auth-Pattern | messages() |
|---|---|---|---|
| Fulfillment/DhlBooking | 2 (DhlBooking/Bulk) | `can('fulfillment.orders.manage')` | ✓ |
| Fulfillment/Masterdata | 12 (Store/Update-Paare × 6) | `return true` (Route-Middleware) | ✗ |
| Fulfillment/ShipmentOrder | 6 (Index/Booking/Bulk/Manual/Tracking/Assign) | `return true` | teilweise |
| Admin/Settings | 3 (DhlFreightSettings, 2× DhlCatalog-Filter) | mixed | DhlFreight ✓, andere ✗ |
| Api/Admin | 2 (AllowedDhlServices×2) | `return true` | ✗ |
| Api/Dispatch | 1 (CaptureDispatchScan) | `return true` | ✗ |

**Traits:** `ValidatesDhlBookingServices` (Booking+Bulk), `ValidatesDhlCatalogProfile` (Store+Update Freight)

## 2) Findings

### HIGH
| # | Befund | Vorkommen | Fix |
|---|---|---|---|
| H1 | `return true` ohne Auth-Kommentar | 13/28 | Einzeilen-Kommentar je Request |
| H2 | Hardcoded `max:255` / `max:64` Magic-Values | 27× | `config/validation.php` mit `limits.*` |
| H3 | **Inkonsistenz dhl_product_id**: `max:32` in StoreFreightProfile vs. `max:64` in DhlBooking | 2 | Vereinheitlichen auf `max:64` |
| H4 | Identische Store/Update-Paare (nur `sometimes`-Diff) | 6 Paare | Trait `ProvidesStoreUpdateRules` oder Base-Request |
| H5 | Inline-Closure in `ValidatesDhlCatalogProfile::withValidator()` 87 LoC | 1 | Named Rules `ValidDhlProduct`, `ValidDhlService` |
| H6 | `messages()` fehlt in 22/28 Requests | 22 | resources/lang/de/validation.php + `attributes()` |

### MEDIUM
- M1: Identischer `prepareForValidation()` in DhlBookingRequest + DhlBulkBookingRequest (Z.211-255 ≡ Z.109-137) → Trait `NormalizesDhlInput`
- M2: `validated()`-Override mit Trim-Logik 3× identisch (ShipmentOrderIndex/BulkSync/ManualSync) → Trait `SanitizesStringInputs`
- M3: `exists:...`-Rules ohne zentrale Konstanten (5 Tabellen-Referenzen jeweils mehrfach)
- M4: `StoreSenderRuleRequest`-Enum-Werte hardcoded via `implode(',', $this->allowedRuleTypes())` — sollte `SenderRuleType`-Enum sein

### LOW
- L1: Inconsistent Route-Parameter (camelCase vs. snake_case-Fallbacks in Update*-Requests)
- L2: PHPStan-Kommentare statt Type-Casts (`/** @var string $v */ $v = ...`)

## 3) Trait-DRY-Status

| Trait | Verwendung | Inhalt | Bewertung |
|---|---|---|---|
| `ValidatesDhlBookingServices` | DhlBooking + DhlBulkBooking | Service-Validation via Mapper | ✓ gut zentralisiert |
| `ValidatesDhlCatalogProfile` | Store + UpdateFreightProfile | 87 LoC Inline-Closure | ⚠️ komplex, Named Rules nötig |

**Fehlende Traits:** `NormalizesDhlInput`, `SanitizesStringInputs`, `ProvidesStoreUpdateRules`

## 4) Top 5 Refactor-Cluster

| # | Cluster | Effort | Impact |
|---|---|---|---|
| 1 | **Config-Konstanten** für `max:*` (27 Vorkommen → 1 Source) | 1-2h | HIGH |
| 2 | **Trait `NormalizesDhlInput`** (DhlBooking + Bulk dedup) | 30min | MEDIUM |
| 3 | **Trait `ProvidesStoreUpdateRules`** (6 Paare) | 2-3h | HIGH |
| 4 | **`messages()` in 22 Requests** + i18n-Datei | 4-5h | MEDIUM-HIGH (UX + §15) |
| 5 | **Named Rules** `ValidDhlProduct` + `ValidDhlService` (raus aus Closures) | 3-4h | HIGH (Testbarkeit) |

## 5) Engineering-Handbuch Compliance

| § | Status | Befund |
|---|---|---|
| §15 (Validierung am Rand) | ⚠️ | 22/28 ohne messages, Mapper-Logik in Trait-Closure |
| §19 (Defense in Depth) | ✓ | Domain-VOs validieren zusätzlich |
| §22 (dünne Controller) | ✓ | 27/28 typisiert |
| §61 + §75 (DRY) | ✗ | 27 magic-max-Werte, 6 Store/Update-Paare |

**Compliance: ~70%** — strukturell solide, aber substanzielle DRY-Verstöße.

---
_t7-Output, GOAL-2026-05-12T194500-syscart_
