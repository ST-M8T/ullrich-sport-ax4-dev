<?php

declare(strict_types=1);

namespace App\View\Composers\Fulfillment;

use Illuminate\View\View;

/**
 * Fulfillment Assembly View Composer
 * Bereitet Assembly-Index-Daten vor
 */
final class FulfillmentAssemblyComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        /** @var iterable<int, \App\Domain\Fulfillment\Masterdata\FulfillmentPackagingProfile> $rawPackaging */
        $rawPackaging = $data['packagingProfiles'] ?? [];
        $packagingById = collect($rawPackaging)->keyBy(fn ($profile) => $profile->id()->toInt());

        $view->with([
            'packagingById' => $packagingById,
        ]);
    }
}
