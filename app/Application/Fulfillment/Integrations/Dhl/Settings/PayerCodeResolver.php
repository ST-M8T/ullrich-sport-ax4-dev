<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Settings;

use InvalidArgumentException;

/**
 * PayerCodeResolver
 *
 * Validiert die UI-seitig erzwungene Auswahl eines PayerCodes gegen die
 * von DHL akzeptierte Whitelist. Es gibt bewusst KEINEN Default
 * (User-Entscheidung Goal-Kontext): Die UI muss pro Auftrag eine Auswahl
 * erzwingen, der Resolver validiert lediglich.
 *
 * Wird mit t6 auf eine echte Enum (DhlPayerCode) gehoben — bis dahin
 * String-Whitelist (KISS §62).
 */
final class PayerCodeResolver
{
    private const ALLOWED = ['DAP', 'DDP', 'EXW', 'CIP'];

    /**
     * @return string Der validierte, normalisierte PayerCode (Großbuchstaben).
     *
     * @throws InvalidArgumentException Wenn der Code leer oder nicht in der Whitelist ist.
     */
    public function validate(string $payerCode): string
    {
        $normalized = strtoupper(trim($payerCode));
        if ($normalized === '') {
            throw new InvalidArgumentException('PayerCode darf nicht leer sein.');
        }

        if (! in_array($normalized, self::ALLOWED, true)) {
            throw new InvalidArgumentException(sprintf(
                "PayerCode '%s' ist ungültig. Erlaubt: %s.",
                $payerCode,
                implode(', ', self::ALLOWED)
            ));
        }

        return $normalized;
    }

    /**
     * @return array<int,string>
     */
    public function allowed(): array
    {
        return self::ALLOWED;
    }
}
