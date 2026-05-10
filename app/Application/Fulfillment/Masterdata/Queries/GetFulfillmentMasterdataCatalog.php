<?php

namespace App\Application\Fulfillment\Masterdata\Queries;

use App\Application\Fulfillment\Masterdata\Dto\FulfillmentMasterdataCatalog;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentAssemblyOptionRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentFreightProfileRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentPackagingProfileRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentSenderProfileRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentSenderRuleRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentVariationProfileRepository;
use Illuminate\Support\Facades\Cache;

final class GetFulfillmentMasterdataCatalog
{
    public function __construct(
        private readonly FulfillmentPackagingProfileRepository $packagingRepository,
        private readonly FulfillmentAssemblyOptionRepository $assemblyRepository,
        private readonly FulfillmentVariationProfileRepository $variationRepository,
        private readonly FulfillmentSenderProfileRepository $senderRepository,
        private readonly FulfillmentSenderRuleRepository $senderRuleRepository,
        private readonly FulfillmentFreightProfileRepository $freightRepository,
    ) {}

    public function __invoke(): FulfillmentMasterdataCatalog
    {
        $cacheKey = config('performance.masterdata.cache_key', 'masterdata:catalog');
        $ttl = max(1, (int) config('performance.masterdata.cache_ttl', 300));

        return Cache::remember(
            $cacheKey,
            now()->addSeconds($ttl),
            fn () => new FulfillmentMasterdataCatalog(
                $this->packagingRepository->all(),
                $this->assemblyRepository->all(),
                $this->variationRepository->all(),
                $this->senderRepository->all(),
                $this->senderRuleRepository->all(),
                $this->freightRepository->all(),
            )
        );
    }
}
