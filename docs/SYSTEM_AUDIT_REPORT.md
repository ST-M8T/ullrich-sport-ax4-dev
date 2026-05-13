# System Audit Report
Stand: 2026-05-11 05:13:07

## 1) Qualitäts-Gate Status
| Kriterium | Status |
| --- | --- |
| Routen insgesamt | 123 |
| Route-Duplikate (Namen) | 0 |
| Route-Duplikate (Method+URI) | 0 |
| Menüpunkte ohne bestehende Route | 0 |
| Berechtigungen insgesamt | 19 |
| Genutzte Berechtigungen | 19 |
| Unbenutzte Berechtigungen | 0 |
| Unbekannte Route-Berechtigungen | 0 |

## 2) Route-Duplikate (kritisch)
| Typ | Schlüssel | Menge | Beispiele |
| --- | --- | ---: | --- |
| — | — | 0 | Keine kritischen Duplikate |

## 3) Berechtigungs-Diskrepanz
| Art | Wert |
| --- | --- |
| Unbenutzte Berechtigungen | 0 |
| Unbekannte Berechtigungen in Routen | 0 |

## 4) Modul- und Surface-Zuordnung
| Bereich | Routen-Anzahl |
| --- | ---: |
| api:log-files | 5 |
| api:system-settings | 5 |
| api:system-status | 1 |
| api:v1 | 9 |
| web:/ | 1 |
| web:configuration | 22 |
| web:csv-export | 4 |
| web:dispatch | 4 |
| web:fulfillment | 49 |
| web:identity | 8 |
| web:login | 2 |
| web:logout | 1 |
| web:logs | 2 |
| web:monitoring | 3 |
| web:setup | 1 |
| web:tracking | 6 |

## 5) Navigation-Integritätscheck
| Typ | Count | Einträge |
| --- | ---: | --- |
| Menüpunkte mit fehlender Route | 0 |  |
| Menüpunkte ohne explizite Berechtigung | 0 |  |
| Menüpunkte mit leerer Route | 6 | Operations<br>Stammdaten<br>Tracking<br>Monitoring<br>Verwaltung<br>Konfiguration |

## 6) Ungeroutete Views (potenzieller Bereinigungsbereich)
| Kategorie | Anzahl | Beispiele |
| --- | ---: | --- |
| Komponente | 24 | components.filters.filter-form<br>components.filters.filter-tabs<br>components.flash-messages<br>components.forms.checkbox<br>components.forms.form<br>components.forms.form-actions<br>components.forms.input<br>components.forms.select<br>components.forms.textarea<br>components.navigation<br>components.order-status<br>components.sidebar-tabs<br>components.tabs<br>components.ui.action-card<br>components.ui.action-link<br>components.ui.breadcrumbs<br>components.ui.data-table<br>components.ui.definition-list<br>components.ui.empty-state<br>components.ui.info-card<br>components.ui.page-header<br>components.ui.pagination-footer<br>components.ui.section-header<br>components.ui.spinner |
| Mail | 2 | mail.domain-event-alert<br>mail.domain-event-alert_plain |
| Partial | 11 | configuration.settings.partials.logs.sections.audit-logs<br>configuration.settings.partials.logs.sections.domain-events<br>configuration.settings.partials.logs.sections.system-logs<br>configuration.settings.partials.monitoring.sections.system-jobs<br>configuration.settings.partials.monitoring.sections.system-status<br>configuration.settings.partials.monitoring.sections.tracking<br>configuration.settings.partials.notification-form<br>configuration.settings.partials.tab-nav<br>configuration.settings.partials.user-form<br>configuration.settings.partials.verwaltung.sections.identity-users<br>configuration.settings.partials.verwaltung.sections.notifications |
| Test | 1 | tests.layout-sample |

## 7) View-Nutzungsintensität (referenzgestützt)
| View | Eingebunden durch |
| --- | ---: |
| layouts.admin | 46 |
| monitoring.partials.modal | 3 |
| configuration.settings._form | 2 |
| fulfillment.masterdata.partials.catalog | 2 |
| configuration.mail-templates._form | 2 |
| identity.users.partials.user-fields | 2 |
| fulfillment.masterdata.packaging._form | 2 |
| fulfillment.masterdata.variations._form | 2 |
| fulfillment.masterdata.assembly._form | 2 |
| fulfillment.masterdata.freight._form | 2 |
| fulfillment.masterdata.senders._form | 2 |
| fulfillment.masterdata.sender-rules._form | 2 |
| configuration.settings.partials.settings | 1 |
| configuration.settings.partials.masterdata | 1 |
| configuration.settings.partials.monitoring | 1 |
| configuration.settings.partials.logs | 1 |
| configuration.settings.partials.verwaltung | 1 |
| configuration.settings.partials.mail-template-form | 1 |
| configuration.settings.partials.notification-form | 1 |
| configuration.settings.partials.user-form | 1 |
| fulfillment.masterdata.sections.packaging | 1 |
| fulfillment.masterdata.sections.assembly | 1 |
| fulfillment.masterdata.sections.variations | 1 |
| fulfillment.masterdata.sections.senders | 1 |
| fulfillment.masterdata.sections.sender-rules | 1 |
| fulfillment.masterdata.sections.freight | 1 |
| monitoring.setup.partials.system-overview | 1 |

## 8) Mehrfachverwendung von Views
| Ergebnis | Wert |
| --- | --- |
| keine Mehrfach-Nutzung gefunden | 0 |

## 9) Vollständig identische View-Dateien
| Ergebnis | Wert |
| --- | --- |
| keine exakten Duplikate gefunden | 0 |

## 10) Rollenmodell für Mitarbeiter / Leiter / Admin
| Persona | Rollen | Sichtbare Routen | Sichtbare Menüeinträge |
| --- | --- | ---: | --- |
| Mitarbeiter | operations | 65 | Logs · domain-events, Monitoring · system-jobs, Monitoring · tracking |
| Leiter | leiter | 98 | Logs · audit-logs, Logs · domain-events, Logs · system-logs, Monitoring · system-jobs, Monitoring · system-status, Monitoring · tracking, Verwaltung · identity-users, Verwaltung · notifications |
| Admin | admin | 106 | Logs · audit-logs, Logs · domain-events, Logs · system-logs, Monitoring · system-jobs, Monitoring · system-status, Monitoring · tracking, Verwaltung · identity-users, Verwaltung · notifications |

## Test-Suite-Recovery (2026-05-13)

### Befund
- **Vorher (Wave-2-Stand):** 973 Tests, 76 Errors, 47 Failures
- **Nachher (tracked baseline):** 689 Tests, 0 Errors, 0 Failures
- **Nachher (inkl. WIP-Working-Tree):** 973 Tests, 76 Errors, 45 Failures

### Root-Causes

#### 1) Tracked-Baseline-Drift (2 Failures, jetzt grün)
- **`AdminLayoutSnapshotTest`** (1 Failure): Stale Snapshot — Commit `f795a65` (consolidated "Versand → DHL Freight" settings page) hat einen neuen Nav-Eintrag im Admin-Layout hinzugefügt, der Snapshot war veraltet. **Fix:** Snapshot regeneriert (semantisch identisch, nur ein zusätzlicher legitimer Menüpunkt).
- **`FormAccessibilityTest::test_tabs_use_aria_current_instead_of_aria_pressed`** (1 Failure): Test-Bug. Test-Name sagt explizit „use aria-current", aber Assertion forderte `aria-selected="true"`. Die `tabs.blade.php`-Komponente verwendet korrekt `aria-current="page"` (Tabs sind Page-Links, kein WAI-ARIA Tab-Widget). **Fix:** Assertion auf `aria-current="page"` korrigiert.

#### 2) WIP-Working-Tree „DHL Catalog"-Feature (76 Errors + 45 Failures, blockiert)
Cluster (alle untracked / `?? ` in `git status`):
| Test-Cluster | Anzahl | Ursache |
| --- | ---: | --- |
| `EloquentDhl*RepositoryTest` (Products/AdditionalService/Assignment/SyncStatus) | 24 | Migrations für `dhl_products`, `dhl_additional_services`, `dhl_product_service_assignments`, `dhl_catalog_sync_status` sind WIP, Schema/Factories inkonsistent zum Repo-Code |
| `DhlCatalog*ControllerTest` (Index/Product/Service/Audit/SyncTrigger) | 27 | **Routen nicht in `routes/` registriert** → alle Endpoints liefern 404 statt 302/200/403/409 |
| `AllowedDhlServicesControllerTest` + `*IntersectionTest` | 12 | API-Routen + Permissions noch nicht verdrahtet |
| `FreightProfileDhlCatalogValidationTest` + `StoreFreightProfileRequestTest` | 9 | `FormRequest`-Trait für `dhl_product_code`/`dhl_service_codes` ist in untrackten Concerns, Wire-Up fehlt |
| `Console\\*DhlCatalog*CommandTest` (Bootstrap/Sync/SetSuccessor/Unset/List) | 15 | Artisan-Commands existieren als untrackte Files, nicht in `app/Console/Kernel`-Schedule |
| `Mail\\DhlCatalogSyncFailedMailTest` | 4 | Mailable-Klasse referenziert untrackten View-Pfad |
| `Database\\Seeders\\DhlCatalogSeederTest` | 2 | `DhlCatalogSeeder` + `database/data/`-JSON-Quellen untracked |
| `Application\\...\\SynchroniseDhlCatalogServiceTest` | 9 | DTOs/Mapper/Domain-VOs für Catalog-Sync untracked |
| `Unit\\Application\\Fulfillment\\Masterdata\\*ProfileServiceTest` (4 Files) | 12 | Tests überschreiben bestehende `FreightProfileServiceTest`-Erwartungen mit `dhl_product_code`-Feldern + erwarten neue Exception-Klassen (`FreightProfileNotFoundException`), die als untrackte Files existieren aber nicht zu der getrackten Service-Implementierung passen |
| `Listeners`, `View`, sonstige WIP-Tests | 9 | WIP-Komponenten/Listener noch nicht in `EventServiceProvider` registriert |

**Klassifizierung:** Klasse (d) WIP-Code — Tests referenzieren Klassen/Routen/Migrations/Views, die als untrackte Files existieren aber nicht vollständig integriert sind. Es handelt sich um eine **halb-fertige Feature-Implementierung** (DHL Catalog), nicht um eine Regression.

### Geblieben (akzeptiert — benötigt User-Entscheidung)
- **76 Errors + 45 Failures** in untrackten WIP-Test-Files.
- **Blocker:** Pro Task-Constraint dürfen WIP-Files nicht ohne User-Rücksprache entfernt oder modifiziert werden, und `markTestSkipped` auf dutzende untrackte Tests wäre eine Mutation des User-Working-Tree, die das eigentliche Feature-Wiring verschleiert.
- **Empfehlung:** User entscheidet:
  1. **Option A:** WIP-Feature „DHL Catalog" zu Ende implementieren (Routen registrieren, FormRequests verdrahten, Migrations laufen lassen, Commands in Kernel registrieren, Mail-Views committen). Eigener Task / Goal.
  2. **Option B:** WIP-Files temporär stashen (`git stash -u`) bis das Feature implementiert ist → baseline 689/689 grün.
  3. **Option C:** WIP-Test-Files mit `markTestSkipped('DHL Catalog feature WIP — pending integration')` versehen (User-Approval nötig, da untrackte Files modifiziert werden).

### Geänderte Files
- `tests/__snapshots__/layout-admin.snap.html` (regeneriert)
- `tests/Feature/Forms/FormAccessibilityTest.php` (Assertion korrigiert)
