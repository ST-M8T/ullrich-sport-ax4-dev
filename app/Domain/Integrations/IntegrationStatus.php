<?php

declare(strict_types=1);

namespace App\Domain\Integrations;

/**
 * Integration Status Value Object
 * DDD: Value Object - Immutable, definiert Status einer Integration
 */
enum IntegrationStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case ERROR = 'error';
    case CONFIGURING = 'configuring';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktiv',
            self::INACTIVE => 'Inaktiv',
            self::ERROR => 'Fehler',
            self::CONFIGURING => 'In Konfiguration',
        };
    }
}
