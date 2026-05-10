<?php

declare(strict_types=1);

namespace App\View\Composers\Fulfillment;

use Illuminate\View\View;

/**
 * Fulfillment Sender Rules View Composer
 * Bereitet Sender-Rules-Index-Daten vor
 */
final class FulfillmentSenderRulesComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        /** @var iterable<int, \App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile> $rawSenders */
        $rawSenders = $data['senderProfiles'] ?? [];
        $senderById = collect($rawSenders)->keyBy(fn ($profile) => $profile->id()->toInt());
        $ruleTypes = $data['ruleTypes'] ?? [];

        $view->with([
            'senderById' => $senderById,
            'ruleTypes' => $ruleTypes,
        ]);
    }
}
