<?php

declare(strict_types=1);

namespace App\View\Composers\Fulfillment;

use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Fulfillment Shipments View Composer
 * Bereitet Shipments-Index-Daten vor
 */
final class FulfillmentShipmentsComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        $formatDate = static fn (?string $value) => $value
            ? Carbon::parse($value)->timezone(config('app.timezone', 'UTC'))->format('d.m.Y H:i')
            : '—';

        $view->with([
            'formatDate' => $formatDate,
        ]);
    }
}
