<?php

declare(strict_types=1);

namespace App\View\Composers\Fulfillment;

use App\Application\Fulfillment\Masterdata\Dto\FulfillmentMasterdataCatalog;
use App\View\Presenters\Fulfillment\MasterdataSectionPresenter;
use Illuminate\View\View;

/**
 * Fulfillment Masterdata Sender Rules Section View Composer
 * Bereitet Sender-Rules-Section-Daten vor
 */
final class FulfillmentMasterdataSenderRulesSectionComposer
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
        $senderLookup = $data['senderLookup'] ?? collect();

        $sectionData = $this->masterdataSectionService->prepareSenderRulesSection(
            $catalog,
            [
                'sender' => $senderLookup,
            ],
            12
        );

        $view->with($sectionData);
    }
}
