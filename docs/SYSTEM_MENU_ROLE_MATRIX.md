# Menü-Rollen-Matrix (automatisch generiert)
Stand: 2026-05-10 19:54:15

## 1) Sichtbarkeit von Navigationseinträgen je Rolle
| Rolle | Label | Route | Berechtigungen | Route vorhanden | Sichtbar |
| --- | --- | --- | --- | --- | --- |
| admin | Konfiguration | — | — | Nein | Ja |
| admin, configuration, identity, leiter, operations, support, viewer | Logs · audit-logs | monitoring-audit-logs | admin.access, monitoring.audit_logs.view | Ja | Ja |
| admin, configuration, identity, leiter, operations, support, viewer | Logs · domain-events | monitoring-domain-events | admin.access, monitoring.domain_events.view | Ja | Ja |
| admin, configuration, identity, leiter, operations, support, viewer | Logs · system-logs | monitoring-logs | admin.access, admin.logs.view | Ja | Ja |
| admin | Monitoring | — | — | Nein | Ja |
| admin, configuration, identity, leiter, operations, support, viewer | Monitoring · system-jobs | monitoring-system-jobs | admin.access, monitoring.system_jobs.view | Ja | Ja |
| admin, configuration, identity, leiter, operations, support, viewer | Monitoring · system-status | monitoring-health | admin.access, admin.setup.view | Ja | Ja |
| admin, configuration, identity, leiter, operations, support, viewer | Monitoring · tracking | tracking-overview | admin.access, tracking.overview.view | Ja | Ja |
| admin | Operations | — | — | Nein | Ja |
| admin | Stammdaten | — | — | Nein | Ja |
| admin | Tracking | — | — | Nein | Ja |
| admin | Verwaltung | — | — | Nein | Ja |
| admin, configuration, identity, leiter, operations, support, viewer | Verwaltung · identity-users | identity-users | admin.access, identity.users.manage | Ja | Ja |
| admin, configuration, identity, leiter, operations, support, viewer | Verwaltung · notifications | configuration-notifications | admin.access, configuration.notifications.manage | Ja | Ja |

## 2) Zugriff ohne Berechtigung (Menü ohne expliziten Schutz)
| Label | Route |
| --- | --- |
| Konfiguration |  |
| Monitoring |  |
| Operations |  |
| Stammdaten |  |
| Tracking |  |
| Verwaltung |  |
