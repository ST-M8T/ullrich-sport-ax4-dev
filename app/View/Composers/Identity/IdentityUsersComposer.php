<?php

declare(strict_types=1);

namespace App\View\Composers\Identity;

use Illuminate\View\View;

/**
 * Identity Users View Composer
 * Bereitet Users-Index-Daten vor
 * SOLID: Single Responsibility - Nur Users-View-Daten vorbereiten
 * DDD: Presentation Layer - View-spezifische Daten
 */
final class IdentityUsersComposer
{
    /**
     * Bindet Users-Daten an View
     */
    public function compose(View $view): void
    {
        $data = $view->getData();

        $roleChoices = [];
        if (isset($data['roleOptions']) && is_iterable($data['roleOptions'])) {
            foreach ($data['roleOptions'] as $option) {
                if (! is_array($option) || empty($option['value'])) {
                    continue;
                }
                $value = strtolower(trim((string) $option['value']));
                $roleChoices[$value] = [
                    'label' => $option['label'] ?? \Illuminate\Support\Str::headline($value),
                    'description' => $option['description'] ?? null,
                ];
            }
        }

        $selectedRoleFilter = strtolower(trim((string) ($data['filters']['role'] ?? '')));

        $view->with([
            'roleChoices' => $roleChoices,
            'selectedRoleFilter' => $selectedRoleFilter,
        ]);
    }
}
