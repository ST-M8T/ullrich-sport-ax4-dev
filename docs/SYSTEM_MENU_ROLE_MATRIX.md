# Menü-Rollen-Matrix (automatisch generiert)
Stand: 2026-05-10 08:54:13

## 1) Sichtbarkeit von Navigationseinträgen je Rolle
| Rolle | Label | Route | Berechtigungen | Route vorhanden | Sichtbar |
| --- | --- | --- | --- | --- | --- |
| admin, leiter, operations, viewer | Aufträge | fulfillment-orders | fulfillment.orders.view | Ja | Ja |
| admin, leiter, operations | CSV-Export | csv-export | fulfillment.csv_export.manage | Ja | Ja |
| admin, leiter, operations | Kommissionierlisten | dispatch-lists | dispatch.lists.manage | Ja | Ja |
| admin, configuration, identity, leiter, operations, support, viewer | Logs · audit-logs | monitoring-audit-logs | admin.access, monitoring.audit_logs.view | Ja | Ja |
| admin, configuration, identity, leiter, operations, support, viewer | Logs · domain-events | monitoring-domain-events | admin.access, monitoring.domain_events.view | Ja | Ja |
| admin, configuration, identity, leiter, operations, support, viewer | Logs · system-logs | admin-logs | admin.access, admin.logs.view | Ja | Ja |
| admin, configuration, identity, leiter, operations, support, viewer | Monitoring · system-jobs | monitoring-system-jobs | admin.access, monitoring.system_jobs.view | Ja | Ja |
| admin, configuration, identity, leiter, operations, support, viewer | Monitoring · system-status | admin-setup | admin.access, admin.setup.view | Ja | Ja |
| admin, configuration, identity, leiter, operations, support, viewer | Monitoring · tracking | tracking-overview | admin.access, tracking.overview.view | Ja | Ja |
| admin, leiter, operations | Sendungen | fulfillment-shipments | fulfillment.shipments.manage | Ja | Ja |
| admin, configuration | Systemeinstellungen | configuration-settings | configuration.settings.manage | Ja | Ja |
| admin, configuration, identity, leiter, operations, support, viewer | Verwaltung · identity-users | identity-users | admin.access, identity.users.manage | Ja | Ja |
| admin, configuration, identity, leiter, operations, support, viewer | Verwaltung · notifications | configuration-notifications | admin.access, configuration.notifications.manage | Ja | Ja |

## 2) Zugriff ohne Berechtigung (Menü ohne expliziten Schutz)
| Status | Anzahl |
| --- | ---: |
| Keine | 0 |
