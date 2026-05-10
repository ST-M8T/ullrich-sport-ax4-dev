<?php

declare(strict_types=1);

namespace App\Domain\Integrations;

/**
 * Integration Type Value Object
 * DDD: Value Object - Immutable, definiert Typen von Integrationen
 */
enum IntegrationType: string
{
    case ECOMMERCE = 'ecommerce';
    case SHIPPING = 'shipping';
    case WAREHOUSE = 'warehouse';
    case ERP = 'erp';
    case MARKETPLACE = 'marketplace';
    case PAYMENT = 'payment';
    case NOTIFICATION = 'notification';
    case ANALYTICS = 'analytics';

    public function label(): string
    {
        return match ($this) {
            self::ECOMMERCE => 'E-Commerce',
            self::SHIPPING => 'Versand',
            self::WAREHOUSE => 'Lager',
            self::ERP => 'ERP',
            self::MARKETPLACE => 'Marktplatz',
            self::PAYMENT => 'Zahlung',
            self::NOTIFICATION => 'Benachrichtigung',
            self::ANALYTICS => 'Analytics',
        };
    }
}
