<?php

namespace App\Application\Fulfillment\Masterdata\Dto;

use App\Domain\Fulfillment\Masterdata\FulfillmentAssemblyOption;
use App\Domain\Fulfillment\Masterdata\FulfillmentFreightProfile;
use App\Domain\Fulfillment\Masterdata\FulfillmentPackagingProfile;
use App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile;
use App\Domain\Fulfillment\Masterdata\FulfillmentSenderRule;
use App\Domain\Fulfillment\Masterdata\FulfillmentVariationProfile;

/**
 * Immutable data transfer object bundling all masterdata slices.
 * Arrays contain domain entities so consumers can stay type-safe.
 */
final class FulfillmentMasterdataCatalog
{
    /** @var array<int,FulfillmentPackagingProfile> */
    private readonly array $packagingProfiles;

    /** @var array<int,FulfillmentAssemblyOption> */
    private readonly array $assemblyOptions;

    /** @var array<int,FulfillmentVariationProfile> */
    private readonly array $variationProfiles;

    /** @var array<int,FulfillmentSenderProfile> */
    private readonly array $senderProfiles;

    /** @var array<int,FulfillmentSenderRule> */
    private readonly array $senderRules;

    /** @var array<int,FulfillmentFreightProfile> */
    private readonly array $freightProfiles;

    /**
     * @param  iterable<FulfillmentPackagingProfile>  $packagingProfiles
     * @param  iterable<FulfillmentAssemblyOption>  $assemblyOptions
     * @param  iterable<FulfillmentVariationProfile>  $variationProfiles
     * @param  iterable<FulfillmentSenderProfile>  $senderProfiles
     * @param  iterable<FulfillmentSenderRule>  $senderRules
     * @param  iterable<FulfillmentFreightProfile>  $freightProfiles
     */
    public function __construct(
        iterable $packagingProfiles,
        iterable $assemblyOptions,
        iterable $variationProfiles,
        iterable $senderProfiles,
        iterable $senderRules,
        iterable $freightProfiles,
    ) {
        $this->packagingProfiles = $this->normaliseIterable($packagingProfiles);
        $this->assemblyOptions = $this->normaliseIterable($assemblyOptions);
        $this->variationProfiles = $this->normaliseIterable($variationProfiles);
        $this->senderProfiles = $this->normaliseIterable($senderProfiles);
        $this->senderRules = $this->normaliseIterable($senderRules);
        $this->freightProfiles = $this->normaliseIterable($freightProfiles);
    }

    /**
     * @return array<int,FulfillmentPackagingProfile>
     */
    public function packagingProfiles(): array
    {
        return $this->packagingProfiles;
    }

    public function packagingProfilesCount(): int
    {
        return count($this->packagingProfiles);
    }

    /**
     * @return array<int,FulfillmentAssemblyOption>
     */
    public function assemblyOptions(): array
    {
        return $this->assemblyOptions;
    }

    public function assemblyOptionsCount(): int
    {
        return count($this->assemblyOptions);
    }

    /**
     * @return array<int,FulfillmentVariationProfile>
     */
    public function variationProfiles(): array
    {
        return $this->variationProfiles;
    }

    public function variationProfilesCount(): int
    {
        return count($this->variationProfiles);
    }

    /**
     * @return array<int,FulfillmentSenderProfile>
     */
    public function senderProfiles(): array
    {
        return $this->senderProfiles;
    }

    public function senderProfilesCount(): int
    {
        return count($this->senderProfiles);
    }

    /**
     * @return array<int,FulfillmentSenderRule>
     */
    public function senderRules(): array
    {
        return $this->senderRules;
    }

    public function senderRulesCount(): int
    {
        return count($this->senderRules);
    }

    /**
     * @return array<int,FulfillmentFreightProfile>
     */
    public function freightProfiles(): array
    {
        return $this->freightProfiles;
    }

    public function freightProfilesCount(): int
    {
        return count($this->freightProfiles);
    }

    /**
     * @template T
     *
     * @param  iterable<T>  $items
     * @return array<int,T>
     */
    private function normaliseIterable(iterable $items): array
    {
        return is_array($items) ? array_values($items) : iterator_to_array($items, false);
    }
}
