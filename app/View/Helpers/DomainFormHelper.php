<?php

declare(strict_types=1);

namespace App\View\Helpers;

/**
 * Domain Form Helper
 * Extrahiert Werte aus Domain-Objekten für Formulare
 * SOLID: Single Responsibility - Nur Wert-Extraktion
 * DDD: Presentation Layer - View-spezifische Hilfsfunktionen
 */
final class DomainFormHelper
{
    /**
     * Extrahiert Wert aus Domain-Objekt oder old() Input
     *
     * @param  mixed  $fallback
     * @param  array<string, callable>  $fieldMappers
     * @return mixed
     */
    public static function value(string $field, $fallback = null, ?object $domainObject = null, array $fieldMappers = [])
    {
        $oldValue = old($field);

        if ($oldValue !== null) {
            return $oldValue;
        }

        if ($domainObject === null) {
            return $fallback;
        }

        if (isset($fieldMappers[$field])) {
            return $fieldMappers[$field]($domainObject);
        }

        $methodName = self::fieldToMethod($field);
        if (method_exists($domainObject, $methodName)) {
            $value = $domainObject->$methodName();

            if (is_object($value) && method_exists($value, 'toInt')) {
                return $value->toInt();
            }

            return $value;
        }

        return $fallback;
    }

    /**
     * Konvertiert Feldname zu Methodenname
     */
    private static function fieldToMethod(string $field): string
    {
        return str_replace('_', '', ucwords($field, '_'));
    }
}
