<?php

declare(strict_types=1);

namespace App\View\Composers\Configuration;

use Illuminate\View\View;

/**
 * Configuration Settings Tab Nav View Composer
 * Bereitet Tab-Navigation-Daten vor
 */
final class ConfigurationSettingsTabNavComposer
{
    public function compose(View $view): void
    {
        $baseParameters = request()->query();
        unset($baseParameters['tab']);

        $view->with([
            'baseParameters' => $baseParameters,
        ]);
    }
}
