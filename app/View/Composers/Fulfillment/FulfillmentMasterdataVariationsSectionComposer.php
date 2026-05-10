<?php

declare(strict_types=1);

namespace App\View\Composers\Fulfillment;

use App\Application\Fulfillment\Masterdata\Dto\FulfillmentMasterdataCatalog;
use App\View\Presenters\Fulfillment\MasterdataSectionPresenter;
use Illuminate\View\View;

/**
 * Fulfillment Masterdata Variations Section View Composer
 * Bereitet Variations-Section-Daten vor
 */
final class FulfillmentMasterdataVariationsSectionComposer
{
    public function __construct(
        private readonly MasterdataSectionPresenter $masterdataSectionService
    ) {}

    public function compose(View $view): void
    {
        $data = $view->getData();

        $catalog = $data['catalog'] ?? null;
        if (! $catalog instanceof FulfillmentMasterdataCatalog) {
            return;
        }
        $packagingLookup = $data['packagingLookup'] ?? collect();
        $assemblyLookup = $data['assemblyLookup'] ?? collect();

        $sectionData = $this->masterdataSectionService->prepareVariationsSection(
            $catalog,
            [
                'packaging' => $packagingLookup,
                'assembly' => $assemblyLookup,
            ],
            12
        );

        $view->with($sectionData);
    }
}
