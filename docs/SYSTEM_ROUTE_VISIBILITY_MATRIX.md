# Routen-Sichtbarkeitsmatrix (Routen zu Rollen und Menüs)
Stand: 2026-05-10 08:54:13

## 1) Sichtbarkeit je Route
| Oberfläche | URI | Route | Berechtigungen | Ungeschützt | Rollen | Menü |
| --- | --- | --- | --- | --- | --- | --- |
| api | /admin/log-files | (/admin/log-files) | — | Ja | — | — |
| api | /admin/log-files/{file} | (/admin/log-files/{file}) | — | Ja | — | — |
| api | /admin/log-files/{file} | (/admin/log-files/{file}) | — | Ja | — | — |
| api | /admin/log-files/{file}/actions/download | (/admin/log-files/{file}/actions/download) | — | Ja | — | — |
| api | /admin/log-files/{file}/entries | (/admin/log-files/{file}/entries) | — | Ja | — | — |
| api | /admin/system-settings | (/admin/system-settings) | — | Ja | — | — |
| api | /admin/system-settings | (/admin/system-settings) | — | Ja | — | — |
| api | /admin/system-settings/{settingKey} | (/admin/system-settings/{settingKey}) | — | Ja | — | — |
| api | /admin/system-settings/{settingKey} | (/admin/system-settings/{settingKey}) | — | Ja | — | — |
| api | /admin/system-settings/{settingKey} | (/admin/system-settings/{settingKey}) | — | Ja | — | — |
| api | /admin/system-status | (/admin/system-status) | — | Ja | — | — |
| api | /v1/dispatch-lists | (/v1/dispatch-lists) | — | Ja | — | — |
| api | /v1/dispatch-lists/{list}/scans | api.dispatch-lists.scans | — | Ja | — | — |
| api | /v1/dispatch-lists/{list}/scans | api.dispatch-lists.scans.store | — | Ja | — | — |
| api | /v1/health/live | (/v1/health/live) | — | Ja | — | — |
| api | /v1/health/ready | (/v1/health/ready) | — | Ja | — | — |
| api | /v1/settings/{key} | (/v1/settings/{key}) | — | Ja | — | — |
| api | /v1/shipments/{trackingNumber} | (/v1/shipments/{trackingNumber}) | — | Ja | — | — |
| api | /v1/tracking-alerts | (/v1/tracking-alerts) | — | Ja | — | — |
| api | /v1/tracking-jobs | (/v1/tracking-jobs) | — | Ja | — | — |
| web | / | (/) | — | Ja | — | — |
| web | /admin/configuration/integrations | configuration-integrations | admin.access, configuration.settings.manage | Nein | admin, configuration | — |
| web | /admin/configuration/integrations/{integrationKey} | configuration-integrations.show | admin.access, configuration.settings.manage | Nein | admin, configuration | — |
| web | /admin/configuration/integrations/{integrationKey} | configuration-integrations.update | admin.access, configuration.settings.manage | Nein | admin, configuration | — |
| web | /admin/configuration/integrations/{integrationKey}/test | configuration-integrations.test | admin.access, configuration.settings.manage | Nein | admin, configuration | — |
| web | /admin/configuration/mail-templates | configuration-mail-templates | admin.access, configuration.mail_templates.manage | Nein | admin, configuration, leiter | — |
| web | /admin/configuration/mail-templates | configuration-mail-templates.store | admin.access, configuration.mail_templates.manage | Nein | admin, configuration, leiter | — |
| web | /admin/configuration/mail-templates/create | configuration-mail-templates.create | admin.access, configuration.mail_templates.manage | Nein | admin, configuration, leiter | — |
| web | /admin/configuration/mail-templates/{templateKey} | configuration-mail-templates.destroy | admin.access, configuration.mail_templates.manage | Nein | admin, configuration, leiter | — |
| web | /admin/configuration/mail-templates/{templateKey} | configuration-mail-templates.update | admin.access, configuration.mail_templates.manage | Nein | admin, configuration, leiter | — |
| web | /admin/configuration/mail-templates/{templateKey}/edit | configuration-mail-templates.edit | admin.access, configuration.mail_templates.manage | Nein | admin, configuration, leiter | — |
| web | /admin/configuration/mail-templates/{templateKey}/preview | configuration-mail-templates.preview | admin.access, configuration.mail_templates.manage | Nein | admin, configuration, leiter | — |
| web | /admin/configuration/notifications | configuration-notifications | admin.access, configuration.notifications.manage | Nein | admin, configuration, leiter | Verwaltung · notifications |
| web | /admin/configuration/notifications | configuration-notifications.store | admin.access, configuration.notifications.manage | Nein | admin, configuration, leiter | Verwaltung · notifications |
| web | /admin/configuration/notifications/dispatch | configuration-notifications.dispatch | admin.access, configuration.notifications.manage | Nein | admin, configuration, leiter | Verwaltung · notifications |
| web | /admin/configuration/notifications/settings | configuration-notifications.settings | admin.access, configuration.notifications.manage | Nein | admin, configuration, leiter | Verwaltung · notifications |
| web | /admin/configuration/notifications/{notification}/redispatch | configuration-notifications.redispatch | admin.access, configuration.notifications.manage | Nein | admin, configuration, leiter | Verwaltung · notifications |
| web | /admin/configuration/settings | configuration-settings | admin.access, configuration.settings.manage | Nein | admin, configuration | Systemeinstellungen |
| web | /admin/configuration/settings | configuration-settings.store | admin.access, configuration.settings.manage | Nein | admin, configuration | Systemeinstellungen |
| web | /admin/configuration/settings/create | configuration-settings.create | admin.access, configuration.settings.manage | Nein | admin, configuration | Systemeinstellungen |
| web | /admin/configuration/settings/groups/{group} | configuration-settings.group-update | admin.access, configuration.settings.manage | Nein | admin, configuration | Systemeinstellungen |
| web | /admin/configuration/settings/{settingKey} | configuration-settings.update | admin.access, configuration.settings.manage | Nein | admin, configuration | Systemeinstellungen |
| web | /admin/configuration/settings/{settingKey}/edit | configuration-settings.edit | admin.access, configuration.settings.manage | Nein | admin, configuration | Systemeinstellungen |
| web | /admin/csv-export | csv-export | admin.access, fulfillment.csv_export.manage | Nein | admin, leiter, operations | CSV-Export |
| web | /admin/csv-export | csv-export.trigger | admin.access, fulfillment.csv_export.manage | Nein | admin, leiter, operations | CSV-Export |
| web | /admin/csv-export/download | csv-export.download | admin.access, fulfillment.csv_export.manage | Nein | admin, leiter, operations | CSV-Export |
| web | /admin/csv-export/{job}/retry | csv-export.retry | admin.access, fulfillment.csv_export.manage | Nein | admin, leiter, operations | CSV-Export |
| web | /admin/dispatch/lists | dispatch-lists | admin.access, dispatch.lists.manage | Nein | admin, leiter, operations | Kommissionierlisten |
| web | /admin/dispatch/lists/{list}/close | dispatch-lists.close | admin.access, dispatch.lists.manage | Nein | admin, leiter, operations | Kommissionierlisten |
| web | /admin/dispatch/lists/{list}/export | dispatch-lists.export | admin.access, dispatch.lists.manage | Nein | admin, leiter, operations | Kommissionierlisten |
| web | /admin/dispatch/lists/{list}/scans | dispatch-lists.scans | admin.access, dispatch.lists.manage | Nein | admin, leiter, operations | Kommissionierlisten |
| web | /admin/fulfillment/masterdata | fulfillment-masterdata | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/assembly-options | fulfillment.masterdata.assembly.index | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/assembly-options | fulfillment.masterdata.assembly.store | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/assembly-options/create | fulfillment.masterdata.assembly.create | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/assembly-options/{assemblyOption} | fulfillment.masterdata.assembly.destroy | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/assembly-options/{assemblyOption} | fulfillment.masterdata.assembly.update | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/assembly-options/{assemblyOption}/edit | fulfillment.masterdata.assembly.edit | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/freight-profiles | fulfillment.masterdata.freight.index | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/freight-profiles | fulfillment.masterdata.freight.store | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/freight-profiles/create | fulfillment.masterdata.freight.create | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/freight-profiles/{freightProfile} | fulfillment.masterdata.freight.destroy | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/freight-profiles/{freightProfile} | fulfillment.masterdata.freight.update | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/freight-profiles/{freightProfile}/edit | fulfillment.masterdata.freight.edit | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/packaging-profiles | fulfillment.masterdata.packaging.index | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/packaging-profiles | fulfillment.masterdata.packaging.store | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/packaging-profiles/create | fulfillment.masterdata.packaging.create | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/packaging-profiles/{packagingProfile} | fulfillment.masterdata.packaging.destroy | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/packaging-profiles/{packagingProfile} | fulfillment.masterdata.packaging.update | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/packaging-profiles/{packagingProfile}/edit | fulfillment.masterdata.packaging.edit | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/sender-profiles | fulfillment.masterdata.senders.index | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/sender-profiles | fulfillment.masterdata.senders.store | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/sender-profiles/create | fulfillment.masterdata.senders.create | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/sender-profiles/{senderProfile} | fulfillment.masterdata.senders.destroy | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/sender-profiles/{senderProfile} | fulfillment.masterdata.senders.update | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/sender-profiles/{senderProfile}/edit | fulfillment.masterdata.senders.edit | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/sender-rules | fulfillment.masterdata.sender-rules.index | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/sender-rules | fulfillment.masterdata.sender-rules.store | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/sender-rules/create | fulfillment.masterdata.sender-rules.create | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/sender-rules/{senderRule} | fulfillment.masterdata.sender-rules.destroy | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/sender-rules/{senderRule} | fulfillment.masterdata.sender-rules.update | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/sender-rules/{senderRule}/edit | fulfillment.masterdata.sender-rules.edit | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/variation-profiles | fulfillment.masterdata.variations.index | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/variation-profiles | fulfillment.masterdata.variations.store | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/variation-profiles/create | fulfillment.masterdata.variations.create | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/variation-profiles/{variationProfile} | fulfillment.masterdata.variations.destroy | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/variation-profiles/{variationProfile} | fulfillment.masterdata.variations.update | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/masterdata/variation-profiles/{variationProfile}/edit | fulfillment.masterdata.variations.edit | admin.access, fulfillment.masterdata.manage | Nein | admin, leiter, operations | — |
| web | /admin/fulfillment/orders | fulfillment-orders | admin.access, fulfillment.orders.view | Nein | admin, leiter, operations, viewer | Aufträge |
| web | /admin/fulfillment/orders/actions/manual-sync | fulfillment-orders.sync-manual | admin.access, fulfillment.orders.view | Nein | admin, leiter, operations, viewer | Aufträge |
| web | /admin/fulfillment/orders/actions/sync-booked | fulfillment-orders.sync-booked | admin.access, fulfillment.orders.view | Nein | admin, leiter, operations, viewer | Aufträge |
| web | /admin/fulfillment/orders/actions/sync-visible | fulfillment-orders.sync-visible | admin.access, fulfillment.orders.view | Nein | admin, leiter, operations, viewer | Aufträge |
| web | /admin/fulfillment/orders/{order} | fulfillment-orders.show | admin.access, fulfillment.orders.view | Nein | admin, leiter, operations, viewer | Aufträge |
| web | /admin/fulfillment/orders/{order}/book | fulfillment-orders.book | admin.access, fulfillment.orders.view | Nein | admin, leiter, operations, viewer | Aufträge |
| web | /admin/fulfillment/orders/{order}/dhl/book | fulfillment-orders.dhl.book | admin.access, fulfillment.orders.view | Nein | admin, leiter, operations, viewer | Aufträge |
| web | /admin/fulfillment/orders/{order}/dhl/label | fulfillment-orders.dhl.label | admin.access, fulfillment.orders.view | Nein | admin, leiter, operations, viewer | Aufträge |
| web | /admin/fulfillment/orders/{order}/dhl/price-quote | fulfillment-orders.dhl.price-quote | admin.access, fulfillment.orders.view | Nein | admin, leiter, operations, viewer | Aufträge |
| web | /admin/fulfillment/orders/{order}/tracking-transfer | fulfillment-orders.transfer | admin.access, fulfillment.orders.view | Nein | admin, leiter, operations, viewer | Aufträge |
| web | /admin/fulfillment/shipments | fulfillment-shipments | admin.access, fulfillment.shipments.manage | Nein | admin, leiter, operations | Sendungen |
| web | /admin/fulfillment/shipments/{shipment}/sync | fulfillment-shipments.sync | admin.access, fulfillment.shipments.manage | Nein | admin, leiter, operations | Sendungen |
| web | /admin/identity/users | identity-users | admin.access, identity.users.manage | Nein | admin, identity, leiter | Verwaltung · identity-users |
| web | /admin/identity/users | identity-users.store | admin.access, identity.users.manage | Nein | admin, identity, leiter | Verwaltung · identity-users |
| web | /admin/identity/users/create | identity-users.create | admin.access, identity.users.manage | Nein | admin, identity, leiter | Verwaltung · identity-users |
| web | /admin/identity/users/{user} | identity-users.show | admin.access, identity.users.manage | Nein | admin, identity, leiter | Verwaltung · identity-users |
| web | /admin/identity/users/{user} | identity-users.update | admin.access, identity.users.manage | Nein | admin, identity, leiter | Verwaltung · identity-users |
| web | /admin/identity/users/{user}/edit | identity-users.edit | admin.access, identity.users.manage | Nein | admin, identity, leiter | Verwaltung · identity-users |
| web | /admin/identity/users/{user}/password | identity-users.reset-password | admin.access, identity.users.manage | Nein | admin, identity, leiter | Verwaltung · identity-users |
| web | /admin/identity/users/{user}/status | identity-users.update-status | admin.access, identity.users.manage | Nein | admin, identity, leiter | Verwaltung · identity-users |
| web | /admin/logs | admin-logs | admin.access, admin.logs.view | Nein | admin, leiter, support | Logs · system-logs |
| web | /admin/logs/download | admin-logs.download | admin.access, admin.logs.view | Nein | admin, leiter, support | Logs · system-logs |
| web | /admin/monitoring/audit-logs | monitoring-audit-logs | admin.access, monitoring.audit_logs.view | Nein | admin, leiter, support | Logs · audit-logs |
| web | /admin/monitoring/domain-events | monitoring-domain-events | admin.access, monitoring.domain_events.view | Nein | admin, leiter, operations, support | Logs · domain-events |
| web | /admin/monitoring/system-jobs | monitoring-system-jobs | admin.access, monitoring.system_jobs.view | Nein | admin, leiter, operations, support, viewer | Monitoring · system-jobs |
| web | /admin/setup | admin-setup | admin.access, admin.setup.view | Nein | admin, configuration, leiter | Monitoring · system-status |
| web | /admin/tracking | tracking-overview | admin.access, tracking.overview.view | Nein | admin, leiter, operations, support, viewer | Monitoring · tracking |
| web | /admin/tracking/alerts/{alert} | tracking-alerts.show | admin.access, tracking.overview.view | Nein | admin, leiter, operations, support, viewer | — |
| web | /admin/tracking/alerts/{alert}/acknowledge | tracking-alerts.acknowledge | admin.access, tracking.alerts.manage | Nein | admin, leiter, operations | — |
| web | /admin/tracking/jobs/{job} | tracking-jobs.show | admin.access, tracking.jobs.manage | Nein | admin, leiter, operations | — |
| web | /admin/tracking/jobs/{job}/fail | tracking-jobs.fail | admin.access, tracking.jobs.manage | Nein | admin, leiter, operations | — |
| web | /admin/tracking/jobs/{job}/retry | tracking-jobs.retry | admin.access, tracking.jobs.manage | Nein | admin, leiter, operations | — |
| web | /login | login | — | Ja | — | — |
| web | /login | login.perform | — | Ja | — | — |
| web | /logout | logout | — | Ja | — | — |
