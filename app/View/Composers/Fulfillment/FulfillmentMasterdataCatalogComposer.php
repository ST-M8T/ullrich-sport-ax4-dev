<?php

declare(strict_types=1);

namespace App\View\Composers\Fulfillment;

use App\Application\Fulfillment\Masterdata\Dto\FulfillmentMasterdataCatalog;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Fulfillment Masterdata Catalog View Composer
 * Bereitet Catalog-Daten für Sections vor
 */
final class FulfillmentMasterdataCatalogComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        $catalog = $data['catalog'] ?? null;
        if (! $catalog instanceof FulfillmentMasterdataCatalog) {
            return;
        }

        $packagingLookup = collect($catalog->packagingProfiles())
            ->keyBy(fn ($profile) => $profile->id()->toInt());
        $assemblyLookup = collect($catalog->assemblyOptions())
            ->keyBy(fn ($option) => $option->id()->toInt());

        $variationsListUrl = Route::has('fulfillment.masterdata.variations.index')
            ? route('fulfillment.masterdata.variations.index')
            : null;
        $packagingListUrl = Route::has('fulfillment.masterdata.packaging.index')
            ? route('fulfillment.masterdata.packaging.index')
            : null;
        $senderListUrl = Route::has('fulfillment.masterdata.senders.index')
            ? route('fulfillment.masterdata.senders.index')
            : null;

        $view->with([
            'packagingLookup' => $packagingLookup,
            'assemblyLookup' => $assemblyLookup,
            'variationsListUrl' => $variationsListUrl,
            'packagingListUrl' => $packagingListUrl,
            'senderListUrl' => $senderListUrl,
        ]);
    }
}
