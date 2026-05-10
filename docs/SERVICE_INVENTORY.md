# Application-Service-Inventar

Stand: 2026-05-08

> **Ziel**: Engineering-Handbuch §60 verlangt klare Verantwortungen pro `*Service`-Klasse.
> Diese Liste dokumentiert alle 43 Application-Services, ihren Use-Case und Auffälligkeiten,
> die in einer späteren Welle adressiert werden sollten.

## 1) Inventar pro Bounded Context

### Configuration (6)

| Service | Zweck | Hinweis |
|---|---|---|
| `SystemSettingService` | CRUD + Read-Through-Cache für `system_settings`-Tabelle | klar |
| `MailTemplateService` | Verwaltung von Mail-Vorlagen (Template-Body, Variablen) | klar |
| `NotificationService` | Lifecycle der Notification-Records (queue, status, retry) | klar |
| `NotificationDispatchService` | Versand-Orchestrierung über Channels (Mail, Slack, SMS) | klar |
| `SecretEncryptionService` | AES-256 Verschlüsselung für `system_secret_versions` | klar |
| `SecretRotationService` | Rotations-Workflow für gespeicherte Secrets | klar |

### Dispatch (1)

| Service | Zweck | Hinweis |
|---|---|---|
| `DispatchListService` | CRUD + Status-Übergänge der Dispatch-Listen | klar |

### Fulfillment (16)

| Service | Zweck | Hinweis |
|---|---|---|
| `ManualShipmentService` | Manuelles Erstellen von Shipments außerhalb Plenty | klar |
| `PlentyOrderSyncService` | Pull-Sync der Aufträge von Plenty | klar |
| `ShipmentOrderAdministrationService` | Admin-Aktionen auf Aufträgen (Bearbeitungs-Workflow) | klar |
| `ShipmentOrderViewService` | Read-Optimized Aggregat für Detail-View | klar |
| `ShipmentTrackingService` | Tracking-Status-Aggregation pro Shipment | klar |
| `DhlTrackingSyncService` | Pull-Sync der DHL-Tracking-Daten | klar |
| `DhlShipmentBookingService` | DHL-Booking-Aufruf, Idempotenz | klar |
| `DhlLabelService` | Label-PDF-Download von DHL | klar |
| `DhlPriceQuoteService` | DHL-Preisabfrage | klar |
| `MasterdataSectionService` | Section-Daten-Aufbereitung für UI-Composer | UI-affin, an Application-Grenze |
| `PackagingProfileService` | CRUD Verpackungsprofile | klar |
| `AssemblyOptionService` | CRUD Vormontage-Optionen | klar |
| `VariationProfileService` | CRUD Varianten-Profile | klar |
| `SenderProfileService` | CRUD Sender-Profile | klar |
| `SenderRuleService` | CRUD Sender-Regeln (Routing) | klar |
| `FreightProfileService` | CRUD Freight-Profile | klar |

### Identity (5)

| Service | Zweck | Hinweis |
|---|---|---|
| `AuthenticationService` | Login-Flow, Brute-Force-Throttle | klar |
| `UserAccountService` | Aggregate-Root-Operationen auf User | klar |
| `UserCreationService` | Use-Case: User anlegen | klar |
| `UserUpdateService` | Use-Case: User updaten (Profil, Rolle) | klar |
| `UserPasswordService` | Use-Case: Passwort setzen / zurücksetzen | klar |

### Integrations (2)

| Service | Zweck | Hinweis |
|---|---|---|
| `IntegrationSettingsService` | Settings-CRUD pro Integration (Plenty, DHL) | klar |
| `IntegrationFormFieldService` | Form-Field-Generation für Settings-UI | UI-affin |

### Monitoring (11)

| Service | Zweck | Hinweis |
|---|---|---|
| `HealthCheckService` | Live/Ready-Checks (DB, Cache, Queue) | klar |
| `LogViewerService` | Log-File-Listing + Tail | klar |
| `LogExportService` | Log-Export für Support | klar |
| `DomainEventService` | Domain-Event-Listing aus dem Audit-Trail | klar |
| `SystemStatusService` | Aggregator für System-Status-View (Setup-Page) | klar |
| `SystemJobService` | CRUD + Lifecycle-Hooks für Jobs | siehe ⚠ Cluster |
| `SystemJobLifecycleService` | start/finish/fail-Übergänge | siehe ⚠ Cluster |
| `SystemJobFailureStreakService` | Berechnung Failure-Streak für Alerts | siehe ⚠ Cluster |
| `SystemJobPolicyService` | Retry-Policy-Entscheidungen | siehe ⚠ Cluster |
| `SystemJobRetryService` | Manuelles Retry | siehe ⚠ Cluster |
| `SystemJobAlertService` | Alert-Versand bei Job-Failures | siehe ⚠ Cluster |

### Tracking (2)

| Service | Zweck | Hinweis |
|---|---|---|
| `TrackingJobService` | CRUD + Lifecycle der Tracking-Jobs | klar |
| `TrackingAlertService` | CRUD + Acknowledge der Tracking-Alerts | klar |

---

## 2) Auffällige Cluster (Empfehlung für späteren Refactor)

### ⚠ Cluster A — `SystemJob*` in Monitoring (6 Services)

`SystemJobService`, `SystemJobLifecycleService`, `SystemJobFailureStreakService`, `SystemJobPolicyService`, `SystemJobRetryService`, `SystemJobAlertService` zerlegen einen einzigen Domain-Begriff (System-Job) in 6 Application-Services. Engineering-Handbuch §60: **Service-Suffix nur mit klarem Zweck**.

**Hypothesen für Refactor**:
- Lifecycle-Übergänge gehören als Methoden in den `SystemJob`-Aggregate-Root (Domain), nicht in 6 Services (Anti-Anemic-Domain-Modell).
- `SystemJobAlertService` ist ein Application-Listener auf Domain-Events — könnte zum Listener werden.
- `SystemJobRetryService` ist ein Use-Case `RetrySystemJob` — könnte schmaler geschnitten werden.

**Empfohlene Aktion (Welle 4)**:
1. Geschäftsregeln aus den 5 Sub-Services in `App\Domain\Monitoring\SystemJob` ziehen (Tell-Don't-Ask).
2. Application-Services nur als Use-Case-Orchestrator stehen lassen (`StartSystemJob`, `FinishSystemJob`, `RetrySystemJob`).
3. Erwartung: 6 Services → 3 Use-Case-Klassen + Domain-Methoden.

### ⚠ Cluster B — Identity User-Services (4 Use-Case-Services + 1 Aggregate-Service)

`UserAccountService` ist Fassade über die User-Aggregate. `UserCreationService`, `UserUpdateService`, `UserPasswordService` sind Use-Case-Services, die durch die Fassade gehen. Das ist saubere CQRS-Schichtung — **kein Refactor nötig**.

### ⚠ Cluster C — Configuration `NotificationService` vs `NotificationDispatchService`

Die Naming-Trennung ist gut (Lifecycle vs. Versand), aber das Wort `Service` kann verwirren. **Optional**: Umbenennen in `NotificationLifecycleService` und `NotificationDispatcher` (ohne `-Service`-Suffix). Geringe Priorität.

### ⚠ Cluster D — UI-affine Services in Application

`MasterdataSectionService`, `IntegrationFormFieldService` enthalten Aufbereitung für Blade-Views. Engineering-Handbuch §3: **UI gehört nicht ins Application-Layer**. 

**Empfohlene Aktion (Welle 4)**:
- Diese zwei Services als ViewComposer-Hilfen umorganisieren oder die UI-Anteile in dedizierte ViewComposer-Klassen extrahieren.

---

## 3) Statistik

| Metrik | Wert |
|---|---|
| Application-Services gesamt | 43 |
| Bounded Contexts | 7 |
| Services pro Context (Min/Max/Median) | 1 / 16 / 5 |
| Auffällige Cluster für späteren Refactor | 4 |

## 4) Hinweis zur Anwendung

Dieses Inventar ist **Audit-Doku**, nicht das Refactoring selbst. Konkrete Refactorings sind im Backlog [SYSTEM_CLEANUP_BACKLOG.md](SYSTEM_CLEANUP_BACKLOG.md) Abschnitt B-2 / ARCH-5 referenziert.
