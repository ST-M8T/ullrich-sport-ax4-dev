<?php

declare(strict_types=1);

namespace App\View\Composers\Fulfillment;

use App\Application\Fulfillment\Masterdata\Dto\FulfillmentMasterdataCatalog;
use App\View\Presenters\Fulfillment\MasterdataSectionPresenter;
use Illuminate\View\View;

/**
 * Fulfillment Masterdata Senders Section View Composer
 * Bereitet Senders-Section-Daten vor
 */
final class FulfillmentMasterdataSendersSectionComposer
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

        $sectionData = $this->masterdataSectionService->prepareSendersSection($catalog, 10);

        $view->with($sectionData);
    }
}
