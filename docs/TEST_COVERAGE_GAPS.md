# TEST_COVERAGE_GAPS
**Stand:** 2026-05-13

## 1) Test-Suite-Status

- **Tests total:** 190 Test-Files (923 Tests grün in CI)
- **Aufteilung:** 113 Feature · 63 Unit · 6 Architecture · 8 Console
- **Coverage-Tooling:** **fehlt** (kein Xdebug/PCOV in phpunit.xml)
- **Pest/Dusk:** keine Browser-Tests vorhanden

## 2) Lücken nach Kritikalität

### 🔴 HIGH — Sofort schließen (geschäftskritisch)

| Pfad | Typ | Begründung |
|---|---|---|
| `Api/Admin/DhlBookingController` | API-Controller | Buchungen — keine direkten Tests |
| `Api/Admin/DhlCancellationController` | API-Controller | Stornos — schwer rückgängig |
| `Api/Admin/DhlLabelController` | API-Controller | Label-Abruf — kritisch für Operations |
| `Api/Admin/DhlPriceQuoteController` | API-Controller | Preis-Quote — keine Tests |
| `Api/Admin/DhlBulkBookingController` | API-Controller | Massenbuchung — keine Tests |
| `Api/Admin/DhlBulkCancellationController` | API-Controller | Massenstornos — keine Tests |
| `Fulfillment/ShipmentOrderActionController` | Web-Controller | sync/manual sync — keine Tests |
| `Application/Fulfillment/Orders/ShipmentOrderAdministrationService` | Application-Service | Order-Lifecycle-Zentrum — nur indirekt getestet |
| `Application/Fulfillment/Shipments/DhlTrackingSyncService` | Service | Tracking-Sync — nur Mock-Tests |
| `Application/Fulfillment/Shipments/ManualShipmentService` | Service | Manuelle Shipment-Verwaltung — KEINE Tests |
| `Application/Fulfillment/Shipments/ShipmentTrackingService` | Service | Tracking-Datenfluss — KEINE Tests |

### 🟠 MEDIUM
- 5 Masterdata-Services (FreightProfile, SenderProfile, Packaging, Variation, Assembly) — nur indirekt
- `Configuration/MailTemplateService`, `Configuration/NotificationService` — keine Tests
- `Monitoring/LogViewerService` (273 LoC) + `LogExportService` — keine Tests
- 4 Identity-Services (UserAccount/Creation/Password/Update) — keine Tests
- `Monitoring/SystemStatusService` (220 LoC) — keine Tests
- 28 FormRequests **alle ohne dedizierte Validation-Tests**

### 🟡 LOW
- Read-only-Views (AuditLog, DomainEvent, Log, Setup, SystemJob) — nur Display-Logik
- ViewHelpers wie `ShipmentTrackingViewHelper`

## 3) Detaillierte Coverage-Karte

### Domain-Aggregates: 6/6 ✓
- DhlProduct, DhlAdditionalService, DispatchList, DispatchScan, ShipmentOrder (teilweise), ReceiverAddress

### Application-Services-Tests
| Status | Anzahl | Beispiele |
|---|---|---|
| ✓ Direkt Unit-Tests | ~12 | DhlAdditionalServiceMapper, DispatchListService, PlentyOrderSyncService, DhlBulkBookingService, SystemJobLifecycleService, DhlPayloadAssembler-related |
| ⚠️ Nur indirekt | ~30+ | Masterdata-Services, ShipmentOrder*, DhlShipmentBookingService |
| ❌ KEINE | ~20 | Manual/Tracking-Services, Configuration-Services, Identity-Services, Monitoring-Services |

### Jobs: 2/5 direkt
- ✓ ProcessDomainEvent, ProcessDhlBulkBookingJob
- ⚠️ DispatchDomainEventFollowUp, WarmDomainCaches, RunDhlCatalogSyncJob (nur via Command-Tests)

### Mailables: 1/2
- ✓ DhlCatalogSyncFailedMail · ❌ DomainEventAlertMail

### Listeners: 0/6 ⚠️
- EnqueueDomainEventProcessing, NotificationSentRecorder, LogShipmentOrderBooked, LogShipmentOrderTrackingTransferred, RecordShipmentEvent, RecordManualSync — **alle ohne direkte Tests**

### FormRequests: 0/28 ⚠️
- Keine dedizierte FormRequest-Validation-Tests vorhanden — nur indirekt via Feature-Tests

## 4) Top Refactor-Cluster (Priorisierte Test-Schreiben)

| # | Cluster | Tests | Aufwand | Sprint |
|---|---|---|---|---|
| 1 | **DHL-API-Endpoints** (Booking/Cancellation/Label/PriceQuote × 2 für Bulk) | 8 Feature | 2-3h | N+1 sofort |
| 2 | **ShipmentOrder-Services** + ActionController | 6 Feature + 4 Unit | 6-8h | N+1 |
| 3 | **FormRequest-Validation** (alle 28, gebündelt) | 12 Tests | 5-7h | N+2 |
| 4 | **Event-Listeners** (6 Stück direkt) | 6 Unit | 3-4h | N+2 |
| 5 | **Large-Services** (DhlShipmentBookingService, SystemStatusService, LogViewerService) | 12 Unit | 10-15h | N+2/N+3 |
| 6 | **Masterdata-CRUD** (FreightProfile/Sender/etc.) | 8 Feature | 6-8h | N+2 |
| 7 | **Mailables** (DomainEventAlertMail) | 1 Feature | 1h | N+1 |

**Gesamt-Aufwand:** ~45–60h für vollständige Coverage

## 5) Test-Architektur Findings

### ✓ Best Practices vorhanden
- `DhlCatalogDomainIsolationTest` als Vorbild für Bounded-Context-Isolation
- 6 Architecture-Tests für Layer-Compliance
- Saubere Test-Fixtures (RefreshDatabase)

### ✗ Lücken
- **Kein Coverage-Tool** (Xdebug/PCOV) in phpunit.xml — sollte aktiviert werden
- **Keine Browser/E2E-Tests** (Pest 4 / Dusk)
- **Listener-Blind-Spot**: 6 Listener ohne isolierte Tests
- **FormRequest-Blind-Spot**: 28 Requests ohne Validation-Tests
- Große Services >200 LoC oft nur indirekt getestet

## 6) Engineering-Handbuch §68 Compliance

| Bereich | Score |
|---|---|
| Domain-Tests für Fachregeln | 8/10 ✓ |
| Application-Tests für Use-Cases | 5/10 ⚠️ |
| API-Tests | 6/10 ⚠️ |
| End-to-End geschäftskritisch | 6/10 ⚠️ |

**Gesamt: 6.25/10 (62%)**

## 7) Top-10 Quick-Win-Tests

1. `DhlBookingControllerTest` — 3 Szenarien (Validierung, DHL-Error, Unauth)
2. `DhlCancellationControllerTest` — 2 Szenarien (Success, NotFound)
3. `ShipmentOrderActionControllerTest` — 3 Aktionen
4. `DhlBookingRequestTest` — Validation
5. `NotificationSentRecorderTest` — 1 Listener-Test
6. `SystemStatusServiceTest` — Health-Aggregation
7. `ShipmentOrderAdministrationServiceTest` — Pagination & Sync
8. `ManualShipmentServiceTest` — Manual-Sync
9. `MasterdataProfilesRequestTest` — CRUD-Validation (4 Profile)
10. `DhlLabelControllerTest` — PDF-Handling

**Erwartete Tests danach:** 190 → ~225 Test-Files.

---
_t9-Output, GOAL-2026-05-12T194500-syscart_
