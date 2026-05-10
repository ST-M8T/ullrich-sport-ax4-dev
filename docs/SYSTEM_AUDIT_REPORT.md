# System Audit Report
Stand: 2026-05-10 08:54:13

## 1) Qualitäts-Gate Status
| Kriterium | Status |
| --- | --- |
| Routen insgesamt | 123 |
| Route-Duplikate (Namen) | 0 |
| Route-Duplikate (Method+URI) | 0 |
| Menüpunkte ohne bestehende Route | 0 |
| Berechtigungen insgesamt | 18 |
| Genutzte Berechtigungen | 18 |
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
| Menüpunkte mit leerer Route | 0 |  |

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
| Mitarbeiter | operations | 65 | Aufträge, CSV-Export, Kommissionierlisten, Logs · domain-events, Monitoring · system-jobs, Monitoring · tracking, Sendungen |
| Leiter | leiter | 89 | Aufträge, CSV-Export, Kommissionierlisten, Logs · audit-logs, Logs · domain-events, Logs · system-logs, Monitoring · system-jobs, Monitoring · system-status, Monitoring · tracking, Sendungen, Verwaltung · identity-users, Verwaltung · notifications |
| Admin | admin | 99 | Aufträge, CSV-Export, Kommissionierlisten, Logs · audit-logs, Logs · domain-events, Logs · system-logs, Monitoring · system-jobs, Monitoring · system-status, Monitoring · tracking, Sendungen, Systemeinstellungen, Verwaltung · identity-users, Verwaltung · notifications |
