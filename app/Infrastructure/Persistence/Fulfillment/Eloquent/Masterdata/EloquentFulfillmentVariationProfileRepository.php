<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata;

use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentVariationProfileRepository;
use App\Domain\Fulfillment\Masterdata\FulfillmentVariationProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\Concerns\FlushesMasterdataCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentFulfillmentVariationProfileRepository implements FulfillmentVariationProfileRepository
{
    use FlushesMasterdataCache;

    public function all(): iterable
    {
        return $this->mapCollection(
            FulfillmentVariationProfileModel::query()
                ->ordered()
                ->get()
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return PaginatedResult<FulfillmentVariationProfile>
     */
    public function paginate(?int $perPage = null, array $filters = []): PaginatedResult
    {
        $perPage = $this->normalisePerPage($perPage);

        $query = FulfillmentVariationProfileModel::query()
            ->ordered();

        if ($itemId = $this->normaliseInt($filters['item_id'] ?? null)) {
            $query->where('item_id', $itemId);
        }

        if ($variationId = $this->normaliseIntAllowZero($filters['variation_id'] ?? null)) {
            $query->where('variation_id', $variationId);
        }

        if ($state = $this->normaliseSearch($filters['default_state'] ?? null)) {
            $query->where('default_state', $state);
        }

        if ($search = $this->normaliseSearch($filters['search'] ?? null)) {
            $query->where('variation_name', 'like', "%{$search}%");
        }

        $paginator = $query->paginate($perPage);

        /** @var array<FulfillmentVariationProfile> $items */
        $items = [];
        foreach ($paginator->items() as $model) {
            $items[] = $this->mapModel($model);
        }

        return PaginatedResult::create(
            items: $items,
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
        );
    }

    public function findByItemId(int $itemId): iterable
    {
        return $this->mapCollection(
            FulfillmentVariationProfileModel::query()
                ->where('item_id', $itemId)
                ->ordered()
                ->get()
        );
    }

    public function getById(Identifier $id): ?FulfillmentVariationProfile
    {
        $model = FulfillmentVariationProfileModel::find($id->toInt());

        return $model ? $this->mapModel($model) : null;
    }

    public function create(array $attributes): FulfillmentVariationProfile
    {
        $model = new FulfillmentVariationProfileModel;
        $model->fill($this->castAttributes($attributes));
        $model->save();

        $model->refresh();

        $this->flushMasterdataCatalogCache();

        return $this->mapModel($model);
    }

    public function update(Identifier $id, array $attributes): FulfillmentVariationProfile
    {
        /** @var FulfillmentVariationProfileModel|null $model */
        $model = FulfillmentVariationProfileModel::find($id->toInt());
        if (! $model) {
            throw (new ModelNotFoundException)->setModel(FulfillmentVariationProfileModel::class, [$id->toInt()]);
        }

        $model->fill($this->castAttributes($attributes));
        $model->save();

        $model->refresh();

        $this->flushMasterdataCatalogCache();

        return $this->mapModel($model);
    }

    public function delete(Identifier $id): void
    {
        FulfillmentVariationProfileModel::whereKey($id->toInt())->delete();
        $this->flushMasterdataCatalogCache();
    }

    /**
     * @param  Collection<int, FulfillmentVariationProfileModel>  $collection
     * @return iterable<FulfillmentVariationProfile>
     */
    private function mapCollection(Collection $collection): iterable
    {
        return $collection->map(fn (FulfillmentVariationProfileModel $model) => $this->mapModel($model));
    }

    private function mapModel(FulfillmentVariationProfileModel $model): FulfillmentVariationProfile
    {
        return FulfillmentVariationProfile::hydrate(
            Identifier::fromInt((int) $model->getKey()),
            (int) $model->item_id,
            $model->variation_id !== null ? (int) $model->variation_id : null,
            $model->variation_name,
            $model->default_state,
            Identifier::fromInt((int) $model->default_packaging_id),
            $model->default_weight_kg !== null ? (float) $model->default_weight_kg : null,
            $model->assembly_option_id !== null ? Identifier::fromInt((int) $model->assembly_option_id) : null,
        );
    }

    /**
     * @param  array<string,mixed>  $attributes
     * @return array<string,mixed>
     */
    private function castAttributes(array $attributes): array
    {
        $payload = [];

        if (array_key_exists('item_id', $attributes)) {
            $payload['item_id'] = max(0, (int) $attributes['item_id']);
        }

        if (array_key_exists('variation_id', $attributes)) {
            $value = $attributes['variation_id'];
            $payload['variation_id'] = $value === null || $value === ''
                ? null
                : (int) $value;
        }

        if (array_key_exists('variation_name', $attributes)) {
            $value = $attributes['variation_name'];
            $payload['variation_name'] = $value !== null && $value !== ''
                ? trim((string) $value)
                : null;
        }

        if (array_key_exists('default_state', $attributes)) {
            $payload['default_state'] = trim((string) $attributes['default_state']);
        }

        if (array_key_exists('default_packaging_id', $attributes)) {
            $payload['default_packaging_id'] = max(0, (int) $attributes['default_packaging_id']);
        }

        if (array_key_exists('default_weight_kg', $attributes)) {
            $value = $attributes['default_weight_kg'];
            $payload['default_weight_kg'] = $value === null || $value === ''
                ? null
                : (float) $value;
        }

        if (array_key_exists('assembly_option_id', $attributes)) {
            $value = $attributes['assembly_option_id'];
            $payload['assembly_option_id'] = $value === null || $value === ''
                ? null
                : (int) $value;
        }

        return $payload;
    }

    private function normalisePerPage(?int $perPage): int
    {
        $default = max(1, (int) config('performance.masterdata.page_size', 25));
        $value = $perPage !== null ? (int) $perPage : $default;

        return max(1, min(200, $value));
    }

    private function normaliseInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private function normaliseIntAllowZero(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normaliseSearch(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
