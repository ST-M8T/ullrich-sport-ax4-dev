<?php

declare(strict_types=1);

namespace App\View\Composers\Fulfillment;

use App\Application\Fulfillment\Masterdata\Dto\FulfillmentMasterdataCatalog;
use App\View\Presenters\Fulfillment\MasterdataSectionPresenter;
use Illuminate\View\View;

/**
 * Fulfillment Masterdata Freight Section View Composer
 * Bereitet Freight-Section-Daten vor
 */
final class FulfillmentMasterdataFreightSectionComposer
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

        $sectionData = $this->masterdataSectionService->prepareFreightSection($catalog, 12);

        $view->with($sectionData);
    }
}
