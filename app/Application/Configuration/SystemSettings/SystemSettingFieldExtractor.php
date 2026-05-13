<?php

declare(strict_types=1);

namespace App\Application\Configuration\SystemSettings;

use Illuminate\Http\Request;

/**
 * Extrahiert den Persistenz-Wert eines system-settings-Feldes aus
 * validierten Request-Daten. Reine Transformations-Logik (text-Repräsentation).
 */
final class SystemSettingFieldExtractor
{
    /**
     * @param  array<string,mixed>  $field
     * @param  array<string,mixed>  $validated
     */
    public function extract(Request $request, array $field, array $validated): ?string
    {
        $key = $field['key'];
        $type = $field['type'] ?? 'text';

        return match ($type) {
            'number' => isset($validated[$key])
                ? (string) $validated[$key]
                : null,
            'checkbox' => $request->boolean($key) ? '1' : '0',
            default => $validated[$key] ?? null,
        };
    }
}
