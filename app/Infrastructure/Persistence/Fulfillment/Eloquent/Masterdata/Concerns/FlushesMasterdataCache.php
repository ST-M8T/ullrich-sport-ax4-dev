<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\Concerns;

use Illuminate\Support\Facades\Cache;

trait FlushesMasterdataCache
{
    protected function flushMasterdataCatalogCache(): void
    {
        Cache::forget(config('performance.masterdata.cache_key', 'masterdata:catalog'));
    }
}
