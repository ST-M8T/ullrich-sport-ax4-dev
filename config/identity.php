<?php

return [
    'permissions' => [
        'admin.access' => [
            'label' => 'Allgemeiner Admin-Zugang',
            'description' => 'Erlaubt den Zugriff auf den Admin-Bereich.',
        ],
        'admin.logs.view' => [
            'label' => 'System-Logs einsehen',
            'description' => 'Ermöglicht das Anzeigen und Herunterladen von System-Logs.',
        ],
        'admin.setup.view' => [
            'label' => 'Setup-Übersicht anzeigen',
            'description' => 'Erlaubt den Zugriff auf die Setup- und Systemstatus-Ansicht.',
        ],
        'configuration.mail_templates.manage' => [
            'label' => 'Mail-Vorlagen verwalten',
            'description' => 'Ermöglicht das Anlegen, Bearbeiten und Löschen von Mail-Vorlagen.',
        ],
        'configuration.notifications.manage' => [
            'label' => 'Benachrichtigungen verwalten',
            'description' => 'Ermöglicht das Verwalten und Versenden von Systembenachrichtigungen.',
        ],
        'configuration.settings.manage' => [
            'label' => 'Systemeinstellungen verwalten',
            'description' => 'Ermöglicht das Verwalten zentraler Systemeinstellungen.',
        ],
        'configuration.integrations.manage' => [
            'label' => 'Integrationen verwalten',
            'description' => 'Ermöglicht das Einsehen, Bearbeiten und Testen von Integration-Konfigurationen.',
        ],
        'settings.dhl_freight.manage' => [
            'label' => 'DHL Freight Einstellungen verwalten',
            'description' => 'Ermöglicht das Bearbeiten der konsolidierten DHL Freight Konfiguration (Auth, API, Tracking, Push).',
        ],
        'dispatch.lists.manage' => [
            'label' => 'Dispatch-Listen verwalten',
            'description' => 'Erlaubt den Zugriff auf Dispatch-Listen und die Ausführung von Aktionen.',
        ],
        'fulfillment.csv_export.manage' => [
            'label' => 'CSV-Export steuern',
            'description' => 'Erlaubt das Auslösen, Wiederholen und Herunterladen von CSV-Exporten.',
        ],
        'fulfillment.masterdata.manage' => [
            'label' => 'Fulfillment-Stammdaten verwalten',
            'description' => 'Ermöglicht das Verwalten sämtlicher Fulfillment-Stammdaten.',
        ],
        'fulfillment.orders.view' => [
            'label' => 'Aufträge einsehen',
            'description' => 'Erlaubt den Zugriff auf die Auftragsübersicht und -details.',
        ],
        'fulfillment.orders.manage' => [
            'label' => 'Aufträge verwalten',
            'description' => 'Erlaubt das Buchen, Stornieren und Bulk-Verarbeiten von Aufträgen (DHL Booking, Cancellation, Bulk).',
        ],
        'fulfillment.shipments.manage' => [
            'label' => 'Sendungen verwalten',
            'description' => 'Erlaubt Zugriff auf Sendungsübersicht sowie Synchronisationsaktionen.',
        ],
        'identity.users.manage' => [
            'label' => 'Benutzerverwaltung',
            'description' => 'Ermöglicht das Anzeigen und Bearbeiten von Benutzern.',
        ],
        'monitoring.audit_logs.view' => [
            'label' => 'Audit-Logs einsehen',
            'description' => 'Erlaubt den Zugriff auf Audit-Log-Daten.',
        ],
        'monitoring.domain_events.view' => [
            'label' => 'Domain Events einsehen',
            'description' => 'Erlaubt den Zugriff auf Domain-Event-Listen.',
        ],
        'monitoring.system_jobs.view' => [
            'label' => 'System-Jobs überwachen',
            'description' => 'Erlaubt den Zugriff auf die System-Jobs-Übersicht.',
        ],
        'tracking.alerts.manage' => [
            'label' => 'Tracking-Alerts verwalten',
            'description' => 'Erlaubt das Anzeigen und Bearbeiten von Tracking-Alerts.',
        ],
        'tracking.jobs.manage' => [
            'label' => 'Tracking-Jobs verwalten',
            'description' => 'Erlaubt das Einsehen und Steuern von Tracking-Jobs.',
        ],
        'tracking.overview.view' => [
            'label' => 'Tracking-Übersicht anzeigen',
            'description' => 'Erlaubt den Zugriff auf die Tracking-Übersicht.',
        ],
    ],

    'roles' => [
        'admin' => [
            'label' => 'Administrator',
            'description' => 'Voller Zugriff auf sämtliche administrativen Funktionen.',
            'permissions' => ['*'],
        ],
        'leiter' => [
            'label' => 'Leiter',
            'description' => 'Leiter Operations: alle operativen Berechtigungen plus Benutzerverwaltung, Audit-Logs und Mail-/Notification-Verwaltung. Kein Vollzugriff wie Admin.',
            'permissions' => [
                'admin.access',
                'fulfillment.orders.view',
                'fulfillment.orders.manage',
                'fulfillment.shipments.manage',
                'fulfillment.masterdata.manage',
                'fulfillment.csv_export.manage',
                'dispatch.lists.manage',
                'tracking.overview.view',
                'tracking.jobs.manage',
                'tracking.alerts.manage',
                'monitoring.system_jobs.view',
                'monitoring.domain_events.view',
                'monitoring.audit_logs.view',
                'admin.logs.view',
                'admin.setup.view',
                'identity.users.manage',
                'configuration.integrations.manage',
                'configuration.notifications.manage',
                'configuration.mail_templates.manage',
                'settings.dhl_freight.manage',
            ],
        ],
        'operations' => [
            'label' => 'Mitarbeiter Operations',
            'description' => 'Operative Aufgaben für Fulfillment, Dispatch und Tracking. Kein Identity- oder Konfigurations-Zugriff.',
            'permissions' => [
                'admin.access',
                'fulfillment.orders.view',
                'fulfillment.orders.manage',
                'fulfillment.shipments.manage',
                'fulfillment.masterdata.manage',
                'fulfillment.csv_export.manage',
                'dispatch.lists.manage',
                'tracking.overview.view',
                'tracking.jobs.manage',
                'tracking.alerts.manage',
                'monitoring.system_jobs.view',
                'monitoring.domain_events.view',
            ],
        ],
        'support' => [
            'label' => 'Support',
            'description' => 'Unterstützung mit Fokus auf Monitoring und Fehleranalyse.',
            'permissions' => [
                'admin.access',
                'monitoring.audit_logs.view',
                'monitoring.system_jobs.view',
                'monitoring.domain_events.view',
                'admin.logs.view',
                'tracking.overview.view',
            ],
        ],
        'configuration' => [
            'label' => 'Konfiguration',
            'description' => 'Verantwortlich für Systemeinstellungen und Benachrichtigungen.',
            'permissions' => [
                'admin.access',
                'configuration.settings.manage',
                'configuration.integrations.manage',
                'configuration.mail_templates.manage',
                'configuration.notifications.manage',
                'settings.dhl_freight.manage',
                'admin.setup.view',
            ],
        ],
        'identity' => [
            'label' => 'Identity-Administrator',
            'description' => 'Verantwortlich für Benutzer- und Rollenverwaltung.',
            'permissions' => [
                'admin.access',
                'identity.users.manage',
            ],
        ],
        'viewer' => [
            'label' => 'Viewer',
            'description' => 'Nur lesender Zugriff auf ausgewählte Ansichten.',
            'permissions' => [
                'admin.access',
                'fulfillment.orders.view',
                'tracking.overview.view',
                'monitoring.system_jobs.view',
            ],
        ],
        'noaccess' => [
            'label' => 'Kein Zugriff',
            'description' => 'Default-Rolle fuer neu angelegte Konten ohne explizite Rollenzuweisung. Erteilt KEINE Backend-Berechtigungen (insbesondere kein admin.access). Ein Administrator weist nach Anlage die zutreffende Rolle zu.',
            'permissions' => [],
        ],
    ],

    /*
    | Default-Rolle fuer neu angelegte User. Wird verwendet, wenn beim Anlegen
    | keine Rolle uebergeben wird (siehe UserCreationService::create). Eine
    | Self-Registration existiert in dieser Anwendung nicht; Konten entstehen
    | ausschliesslich durch Administratoren ueber das identity-users-Backend
    | oder via Console (CreateUserCommand). Default ist absichtlich
    | 'noaccess' (Fail-Closed): wer faelschlich ohne Rollenzuweisung angelegt
    | wird, hat keinen Backend-Zugriff, bis ein Admin explizit eine Rolle
    | vergibt. Frueher war die Default-Rolle 'viewer' und damit implizit
    | admin.access-berechtigt.
    */
    'defaults' => [
        'role' => 'noaccess',
    ],
];
