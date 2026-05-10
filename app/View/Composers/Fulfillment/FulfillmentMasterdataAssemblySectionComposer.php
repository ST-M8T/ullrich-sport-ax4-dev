<?php

declare(strict_types=1);

namespace App\View\Composers\Fulfillment;

use App\Application\Fulfillment\Masterdata\Dto\FulfillmentMasterdataCatalog;
use App\View\Presenters\Fulfillment\MasterdataSectionPresenter;
use Illuminate\View\View;

/**
 * Fulfillment Masterdata Assembly Section View Composer
 * Bereitet Assembly-Section-Daten vor
 */
final class FulfillmentMasterdataAssemblySectionComposer
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

        $sectionData = $this->masterdataSectionService->prepareAssemblySection(
            $catalog,
            [
                'packaging' => $packagingLookup,
            ],
            10
        );

        $view->with($sectionData);
    }
}
