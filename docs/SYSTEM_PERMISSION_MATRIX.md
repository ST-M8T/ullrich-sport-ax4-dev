# Berechtigung & Route Coverage
Stand: 2026-05-11 05:13:07

## 1) Berechtigungen aus Routing
| Berechtigung | Label | Routen-Anzahl | Beispiele |
| --- | --- | ---: | --- |
| admin.access | Allgemeiner Admin-Zugang | 99 | configuration-integrations<br>configuration-integrations.show<br>configuration-integrations.update<br>configuration-integrations.test<br>configuration-mail-templates<br>configuration-mail-templates.store<br>configuration-mail-templates.create<br>configuration-mail-templates.destroy<br>configuration-mail-templates.update<br>configuration-mail-templates.edit<br>configuration-mail-templates.preview<br>configuration-notifications<br>configuration-notifications.store<br>configuration-notifications.dispatch<br>configuration-notifications.settings<br>configuration-notifications.redispatch<br>configuration-settings<br>configuration-settings.store<br>configuration-settings.create<br>configuration-settings.group-update<br>configuration-settings.update<br>configuration-settings.edit<br>csv-export<br>csv-export.trigger<br>csv-export.download<br>csv-export.retry<br>dispatch-lists<br>dispatch-lists.close<br>dispatch-lists.export<br>dispatch-lists.scans<br>fulfillment-masterdata<br>fulfillment.masterdata.assembly.index<br>fulfillment.masterdata.assembly.store<br>fulfillment.masterdata.assembly.create<br>fulfillment.masterdata.assembly.destroy<br>fulfillment.masterdata.assembly.update<br>fulfillment.masterdata.assembly.edit<br>fulfillment.masterdata.freight.index<br>fulfillment.masterdata.freight.store<br>fulfillment.masterdata.freight.create<br>fulfillment.masterdata.freight.destroy<br>fulfillment.masterdata.freight.update<br>fulfillment.masterdata.freight.edit<br>fulfillment.masterdata.packaging.index<br>fulfillment.masterdata.packaging.store<br>fulfillment.masterdata.packaging.create<br>fulfillment.masterdata.packaging.destroy<br>fulfillment.masterdata.packaging.update<br>fulfillment.masterdata.packaging.edit<br>fulfillment.masterdata.senders.index<br>fulfillment.masterdata.senders.store<br>fulfillment.masterdata.senders.create<br>fulfillment.masterdata.senders.destroy<br>fulfillment.masterdata.senders.update<br>fulfillment.masterdata.senders.edit<br>fulfillment.masterdata.sender-rules.index<br>fulfillment.masterdata.sender-rules.store<br>fulfillment.masterdata.sender-rules.create<br>fulfillment.masterdata.sender-rules.destroy<br>fulfillment.masterdata.sender-rules.update<br>fulfillment.masterdata.sender-rules.edit<br>fulfillment.masterdata.variations.index<br>fulfillment.masterdata.variations.store<br>fulfillment.masterdata.variations.create<br>fulfillment.masterdata.variations.destroy<br>fulfillment.masterdata.variations.update<br>fulfillment.masterdata.variations.edit<br>fulfillment-orders<br>fulfillment-orders.sync-manual<br>fulfillment-orders.sync-booked<br>fulfillment-orders.sync-visible<br>fulfillment-orders.show<br>fulfillment-orders.book<br>fulfillment-orders.dhl.book<br>fulfillment-orders.dhl.label<br>fulfillment-orders.dhl.price-quote<br>fulfillment-orders.transfer<br>fulfillment-shipments<br>fulfillment-shipments.sync<br>identity-users<br>identity-users.store<br>identity-users.create<br>identity-users.show<br>identity-users.update<br>identity-users.edit<br>identity-users.reset-password<br>identity-users.update-status<br>monitoring-logs<br>monitoring-logs.download<br>monitoring-audit-logs<br>monitoring-domain-events<br>monitoring-system-jobs<br>monitoring-health<br>tracking-alerts.show<br>tracking-alerts.acknowledge<br>tracking-jobs.show<br>tracking-jobs.fail<br>tracking-jobs.retry<br>tracking-overview |
| admin.logs.view | System-Logs einsehen | 7 | (/admin/log-files)<br>(/admin/log-files/{file})<br>(/admin/log-files/{file})<br>(/admin/log-files/{file}/actions/download)<br>(/admin/log-files/{file}/entries)<br>monitoring-logs<br>monitoring-logs.download |
| admin.setup.view | Setup-Übersicht anzeigen | 2 | (/admin/system-status)<br>monitoring-health |
| configuration.integrations.manage | Integrationen verwalten | 4 | configuration-integrations<br>configuration-integrations.show<br>configuration-integrations.update<br>configuration-integrations.test |
| configuration.mail_templates.manage | Mail-Vorlagen verwalten | 7 | configuration-mail-templates<br>configuration-mail-templates.store<br>configuration-mail-templates.create<br>configuration-mail-templates.destroy<br>configuration-mail-templates.update<br>configuration-mail-templates.edit<br>configuration-mail-templates.preview |
| configuration.notifications.manage | Benachrichtigungen verwalten | 5 | configuration-notifications<br>configuration-notifications.store<br>configuration-notifications.dispatch<br>configuration-notifications.settings<br>configuration-notifications.redispatch |
| configuration.settings.manage | Systemeinstellungen verwalten | 11 | (/admin/system-settings)<br>(/admin/system-settings)<br>(/admin/system-settings/{settingKey})<br>(/admin/system-settings/{settingKey})<br>(/admin/system-settings/{settingKey})<br>configuration-settings<br>configuration-settings.store<br>configuration-settings.create<br>configuration-settings.group-update<br>configuration-settings.update<br>configuration-settings.edit |
| dispatch.lists.manage | Dispatch-Listen verwalten | 4 | dispatch-lists<br>dispatch-lists.close<br>dispatch-lists.export<br>dispatch-lists.scans |
| fulfillment.csv_export.manage | CSV-Export steuern | 4 | csv-export<br>csv-export.trigger<br>csv-export.download<br>csv-export.retry |
| fulfillment.masterdata.manage | Fulfillment-Stammdaten verwalten | 37 | fulfillment-masterdata<br>fulfillment.masterdata.assembly.index<br>fulfillment.masterdata.assembly.store<br>fulfillment.masterdata.assembly.create<br>fulfillment.masterdata.assembly.destroy<br>fulfillment.masterdata.assembly.update<br>fulfillment.masterdata.assembly.edit<br>fulfillment.masterdata.freight.index<br>fulfillment.masterdata.freight.store<br>fulfillment.masterdata.freight.create<br>fulfillment.masterdata.freight.destroy<br>fulfillment.masterdata.freight.update<br>fulfillment.masterdata.freight.edit<br>fulfillment.masterdata.packaging.index<br>fulfillment.masterdata.packaging.store<br>fulfillment.masterdata.packaging.create<br>fulfillment.masterdata.packaging.destroy<br>fulfillment.masterdata.packaging.update<br>fulfillment.masterdata.packaging.edit<br>fulfillment.masterdata.senders.index<br>fulfillment.masterdata.senders.store<br>fulfillment.masterdata.senders.create<br>fulfillment.masterdata.senders.destroy<br>fulfillment.masterdata.senders.update<br>fulfillment.masterdata.senders.edit<br>fulfillment.masterdata.sender-rules.index<br>fulfillment.masterdata.sender-rules.store<br>fulfillment.masterdata.sender-rules.create<br>fulfillment.masterdata.sender-rules.destroy<br>fulfillment.masterdata.sender-rules.update<br>fulfillment.masterdata.sender-rules.edit<br>fulfillment.masterdata.variations.index<br>fulfillment.masterdata.variations.store<br>fulfillment.masterdata.variations.create<br>fulfillment.masterdata.variations.destroy<br>fulfillment.masterdata.variations.update<br>fulfillment.masterdata.variations.edit |
| fulfillment.orders.view | Aufträge einsehen | 10 | fulfillment-orders<br>fulfillment-orders.sync-manual<br>fulfillment-orders.sync-booked<br>fulfillment-orders.sync-visible<br>fulfillment-orders.show<br>fulfillment-orders.book<br>fulfillment-orders.dhl.book<br>fulfillment-orders.dhl.label<br>fulfillment-orders.dhl.price-quote<br>fulfillment-orders.transfer |
| fulfillment.shipments.manage | Sendungen verwalten | 2 | fulfillment-shipments<br>fulfillment-shipments.sync |
| identity.users.manage | Benutzerverwaltung | 8 | identity-users<br>identity-users.store<br>identity-users.create<br>identity-users.show<br>identity-users.update<br>identity-users.edit<br>identity-users.reset-password<br>identity-users.update-status |
| monitoring.audit_logs.view | Audit-Logs einsehen | 1 | monitoring-audit-logs |
| monitoring.domain_events.view | Domain Events einsehen | 1 | monitoring-domain-events |
| monitoring.system_jobs.view | System-Jobs überwachen | 1 | monitoring-system-jobs |
| tracking.alerts.manage | Tracking-Alerts verwalten | 1 | tracking-alerts.acknowledge |
| tracking.jobs.manage | Tracking-Jobs verwalten | 3 | tracking-jobs.show<br>tracking-jobs.fail<br>tracking-jobs.retry |
| tracking.overview.view | Tracking-Übersicht anzeigen | 2 | tracking-alerts.show<br>tracking-overview |

| Typ | Gesamt-Routen | Bemerkung |
| --- | ---: | --- |
| Rollen-geschützt | 110 | Nur Routen mit mindestens einem can:-Middleware Eintrag |
| Ungefiltert | 13 | Werden in dieser Auswertung nicht als rollenabhängig bewertet |

## 2) Rollenreichweite
| Rolle | Label | Sichtbare Routen |
| --- | --- | ---: |
| admin | Administrator | 110 |
| leiter | Leiter | 99 |
| operations | Mitarbeiter Operations | 65 |
| support | Support | 12 |
| configuration | Konfiguration | 29 |
| identity | Identity-Administrator | 8 |
| viewer | Viewer | 13 |
| noaccess | Kein Zugriff | 0 |

## 3) Rollen und Menüsichtbarkeit
| Rolle | Sichtbare Menüpunkte |
| --- | --- |
| admin | Monitoring · system-status, Monitoring · system-jobs, Monitoring · tracking, Verwaltung · identity-users, Verwaltung · notifications, Logs · system-logs, Logs · audit-logs, Logs · domain-events |
| leiter | Monitoring · system-status, Monitoring · system-jobs, Monitoring · tracking, Verwaltung · identity-users, Verwaltung · notifications, Logs · system-logs, Logs · audit-logs, Logs · domain-events |
| operations | Monitoring · system-status, Monitoring · system-jobs, Monitoring · tracking, Verwaltung · identity-users, Verwaltung · notifications, Logs · system-logs, Logs · audit-logs, Logs · domain-events |
| support | Monitoring · system-status, Monitoring · system-jobs, Monitoring · tracking, Verwaltung · identity-users, Verwaltung · notifications, Logs · system-logs, Logs · audit-logs, Logs · domain-events |
| configuration | Monitoring · system-status, Monitoring · system-jobs, Monitoring · tracking, Verwaltung · identity-users, Verwaltung · notifications, Logs · system-logs, Logs · audit-logs, Logs · domain-events |
| identity | Monitoring · system-status, Monitoring · system-jobs, Monitoring · tracking, Verwaltung · identity-users, Verwaltung · notifications, Logs · system-logs, Logs · audit-logs, Logs · domain-events |
| viewer | Monitoring · system-status, Monitoring · system-jobs, Monitoring · tracking, Verwaltung · identity-users, Verwaltung · notifications, Logs · system-logs, Logs · audit-logs, Logs · domain-events |
| noaccess |  |

## 4) Oberfläche / Routenverteilung
| Oberfläche | Anzahl |
| --- | ---: |
| api | 20 |
| web | 103 |
