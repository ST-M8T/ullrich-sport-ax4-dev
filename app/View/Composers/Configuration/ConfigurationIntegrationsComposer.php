<?php

declare(strict_types=1);

namespace App\View\Composers\Configuration;

use App\Domain\Integrations\IntegrationType;
use Illuminate\View\View;

/**
 * Configuration Integrations View Composer
 * Bereitet Integrations-Index-Daten vor
 */
final class ConfigurationIntegrationsComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        $integrationTypes = IntegrationType::cases();
        $typeLabels = [];
        foreach ($integrationTypes as $type) {
            $typeLabels[$type->value] = $type->label();
        }

        $view->with([
            'typeLabels' => $typeLabels,
            'integrationTypeEnum' => IntegrationType::class,
        ]);
    }
}
