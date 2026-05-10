<?php

declare(strict_types=1);

namespace App\View\Composers\Fulfillment;

use Illuminate\View\View;

/**
 * Fulfillment Variations View Composer
 * Bereitet Variations-Index-Daten vor
 * SOLID: Single Responsibility - Nur Variations-View-Daten vorbereiten
 * DDD: Presentation Layer - View-spezifische Daten
 */
final class FulfillmentVariationsComposer
{
    /**
     * Bindet Variations-Daten an View
     */
    public function compose(View $view): void
    {
        $data = $view->getData();

        /** @var iterable<int, \App\Domain\Fulfillment\Masterdata\FulfillmentPackagingProfile> $rawPackaging */
        $rawPackaging = $data['packagingProfiles'] ?? [];
        /** @var iterable<int, \App\Domain\Fulfillment\Masterdata\FulfillmentAssemblyOption> $rawAssembly */
        $rawAssembly = $data['assemblyOptions'] ?? [];

        $packagingById = collect($rawPackaging)->keyBy(fn ($profile) => $profile->id()->toInt());
        $assemblyById = collect($rawAssembly)->keyBy(fn ($option) => $option->id()->toInt());

        $view->with([
            'packagingById' => $packagingById,
            'assemblyById' => $assemblyById,
        ]);
    }
}
