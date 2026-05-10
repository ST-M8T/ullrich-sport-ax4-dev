<?php

declare(strict_types=1);

namespace App\View\Presenters\Fulfillment;

use App\Application\Fulfillment\Masterdata\Dto\FulfillmentMasterdataCatalog;

/**
 * Masterdata Section Service
 * Bereitet Masterdata-Section-Daten vor
 * SOLID: Single Responsibility - Nur Section-Daten-Verarbeitung
 * DDD: Application Service - Orchestriert Masterdata-Section-Logik
 */
final class MasterdataSectionPresenter
{
    /**
     * Bereitet Variations-Section-Daten vor
     *
     * @param  array<string, mixed>  $lookups
     * @return array<string, mixed>
     */
    public function prepareVariationsSection(
        FulfillmentMasterdataCatalog $catalog,
        array $lookups,
        int $limit = 12
    ): array {
        $variationProfiles = collect($catalog->variationProfiles())->take($limit);
        $packagingLookup = $lookups['packaging'] ?? collect();
        $assemblyLookup = $lookups['assembly'] ?? collect();

        $processedProfiles = [];
        foreach ($variationProfiles as $profile) {
            $packaging = $packagingLookup->get($profile->defaultPackagingId()->toInt());
            $assembly = $profile->assemblyOptionId()
                ? $assemblyLookup->get($profile->assemblyOptionId()->toInt())
                : null;

            $processedProfiles[] = [
                'profile' => $profile,
                'packaging' => $packaging,
                'assembly' => $assembly,
                'formattedWeight' => $profile->defaultWeightKg() !== null
                    ? number_format($profile->defaultWeightKg(), 2, ',', '.')
                    : '—',
            ];
        }

        return [
            'variationProfiles' => $variationProfiles,
            'processedProfiles' => $processedProfiles,
        ];
    }

    /**
     * Bereitet Packaging-Section-Daten vor
     *
     * @return array<string, mixed>
     */
    public function preparePackagingSection(
        FulfillmentMasterdataCatalog $catalog,
        int $limit = 10
    ): array {
        $packagingProfiles = collect($catalog->packagingProfiles());
        $previewProfiles = $packagingProfiles->take($limit);

        return [
            'packagingProfiles' => $packagingProfiles,
            'previewProfiles' => $previewProfiles,
        ];
    }

    /**
     * Bereitet Senders-Section-Daten vor
     *
     * @return array<string, mixed>
     */
    public function prepareSendersSection(
        FulfillmentMasterdataCatalog $catalog,
        int $limit = 10
    ): array {
        $senderProfiles = collect($catalog->senderProfiles())->take($limit);

        $processedSenders = [];
        foreach ($senderProfiles as $sender) {
            $processedSenders[] = [
                'sender' => $sender,
                'contactInfo' => $sender->contactPerson() ?? '—',
                'contactDetail' => $sender->email() ?? $sender->phone() ?? '—',
                'addressLine1' => $sender->streetName().' '.$sender->streetNumber(),
                'addressLine2' => $sender->postalCode().' '.$sender->city(),
            ];
        }

        return [
            'senderProfiles' => $senderProfiles,
            'processedSenders' => $processedSenders,
        ];
    }

    /**
     * Bereitet Assembly-Section-Daten vor
     *
     * @param  array<string, mixed>  $lookups
     * @return array<string, mixed>
     */
    public function prepareAssemblySection(
        FulfillmentMasterdataCatalog $catalog,
        array $lookups,
        int $limit = 10
    ): array {
        $packagingLookup = $lookups['packaging'] ?? collect();
        $assemblyOptions = collect($catalog->assemblyOptions())->take($limit);

        $processedOptions = [];
        foreach ($assemblyOptions as $option) {
            $packaging = $packagingLookup->get($option->assemblyPackagingId()->toInt());

            $processedOptions[] = [
                'option' => $option,
                'packaging' => $packaging,
                'formattedWeight' => $option->assemblyWeightKg() !== null
                    ? number_format($option->assemblyWeightKg(), 2, ',', '.')
                    : '—',
            ];
        }

        return [
            'assemblyOptions' => $assemblyOptions,
            'processedOptions' => $processedOptions,
        ];
    }

    /**
     * Bereitet Sender-Rules-Section-Daten vor
     *
     * @param  array<string, mixed>  $lookups
     * @return array<string, mixed>
     */
    public function prepareSenderRulesSection(
        FulfillmentMasterdataCatalog $catalog,
        array $lookups,
        int $limit = 12
    ): array {
        $senderLookup = $lookups['sender'] ?? collect();
        $senderRules = collect($catalog->senderRules())
            ->sortByDesc(fn ($rule) => $rule->isActive())
            ->take($limit);

        $processedRules = [];
        foreach ($senderRules as $rule) {
            $targetSender = $senderLookup->get($rule->targetSenderId()->toInt());

            $processedRules[] = [
                'rule' => $rule,
                'targetSender' => $targetSender,
                'formattedRuleType' => \Illuminate\Support\Str::title(str_replace('_', ' ', $rule->ruleType())),
                'statusBadgeClass' => $rule->isActive() ? 'bg-success' : 'bg-secondary',
                'statusLabel' => $rule->isActive() ? 'AKTIV' : 'INAKTIV',
            ];
        }

        return [
            'senderRules' => $senderRules,
            'processedRules' => $processedRules,
        ];
    }

    /**
     * Bereitet Freight-Section-Daten vor
     *
     * @return array<string, mixed>
     */
    public function prepareFreightSection(
        FulfillmentMasterdataCatalog $catalog,
        int $limit = 12
    ): array {
        $freightProfiles = collect($catalog->freightProfiles())->take($limit);

        return [
            'freightProfiles' => $freightProfiles,
        ];
    }
}
