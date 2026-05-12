<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Configuration;

/**
 * Repository-Interface für die globale DHL-Konfiguration.
 *
 * Single-Tenant (YAGNI §63): es existiert genau eine Konfiguration pro
 * Installation, daher nehmen die Methoden keine Identifier entgegen.
 *
 * Implementierungen sind dafür verantwortlich, die kanonischen Quellen
 * zu konsolidieren bzw. beim Speichern wieder in die jeweilige Quelle
 * zurückzuschreiben (Engineering-Handbuch §11).
 */
interface DhlConfigurationRepository
{
    public function load(): DhlConfiguration;

    public function save(DhlConfiguration $configuration): void;
}
