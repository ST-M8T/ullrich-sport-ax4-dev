<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata;

use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentAssemblyOptionRepository;
use App\Domain\Fulfillment\Masterdata\FulfillmentAssemblyOption;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\Concerns\FlushesMasterdataCache;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentFulfillmentAssemblyOptionRepository implements FulfillmentAssemblyOptionRepository
{
    use FlushesMasterdataCache;

    public function all(): iterable
    {
        return FulfillmentAssemblyOptionModel::query()
            ->ordered()
            ->get()
            ->map(fn (FulfillmentAssemblyOptionModel $model) => $this->mapModel($model));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return PaginatedResult<FulfillmentAssemblyOption>
     */
    public function paginate(?int $perPage = null, array $filters = []): PaginatedResult
    {
        $perPage = $this->normalisePerPage($perPage);

        $query = FulfillmentAssemblyOptionModel::query()
            ->ordered();

        if ($itemId = $this->normaliseInt($filters['assembly_item_id'] ?? null)) {
            $query->where('assembly_item_id', $itemId);
        }

        if ($packagingId = $this->normaliseInt($filters['assembly_packaging_id'] ?? null)) {
            $query->where('assembly_packaging_id', $packagingId);
        }

        if ($search = $this->normaliseSearch($filters['search'] ?? null)) {
            $query->where('description', 'like', "%{$search}%");
        }

        $paginator = $query->paginate($perPage);

        /** @var array<FulfillmentAssemblyOption> $items */
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

    public function findByAssemblyItemId(int $assemblyItemId): ?FulfillmentAssemblyOption
    {
        $model = FulfillmentAssemblyOptionModel::query()
            ->where('assembly_item_id', $assemblyItemId)
            ->first();

        return $model ? $this->mapModel($model) : null;
    }

    public function getById(Identifier $id): ?FulfillmentAssemblyOption
    {
        $model = FulfillmentAssemblyOptionModel::find($id->toInt());

        return $model ? $this->mapModel($model) : null;
    }

    public function create(array $attributes): FulfillmentAssemblyOption
    {
        $model = new FulfillmentAssemblyOptionModel;
        $model->fill($this->castAttributes($attributes));
        $model->save();
        $model->refresh();

        $this->flushMasterdataCatalogCache();

        return $this->mapModel($model);
    }

    public function update(Identifier $id, array $attributes): FulfillmentAssemblyOption
    {
        /** @var FulfillmentAssemblyOptionModel|null $model */
        $model = FulfillmentAssemblyOptionModel::find($id->toInt());
        if (! $model) {
            throw (new ModelNotFoundException)->setModel(FulfillmentAssemblyOptionModel::class, [$id->toInt()]);
        }

        $model->fill($this->castAttributes($attributes));
        $model->save();
        $model->refresh();

        $this->flushMasterdataCatalogCache();

        return $this->mapModel($model);
    }

    public function delete(Identifier $id): void
    {
        FulfillmentAssemblyOptionModel::whereKey($id->toInt())->delete();
        $this->flushMasterdataCatalogCache();
    }

    private function mapModel(FulfillmentAssemblyOptionModel $model): FulfillmentAssemblyOption
    {
        return FulfillmentAssemblyOption::hydrate(
            Identifier::fromInt((int) $model->getKey()),
            (int) $model->assembly_item_id,
            Identifier::fromInt((int) $model->assembly_packaging_id),
            $model->assembly_weight_kg !== null ? (float) $model->assembly_weight_kg : null,
            $model->description,
        );
    }

    /**
     * @param  array<string,mixed>  $attributes
     * @return array<string,mixed>
     */
    private function castAttributes(array $attributes): array
    {
        $payload = [];

        if (array_key_exists('assembly_item_id', $attributes)) {
            $payload['assembly_item_id'] = max(0, (int) $attributes['assembly_item_id']);
        }

        if (array_key_exists('assembly_packaging_id', $attributes)) {
            $payload['assembly_packaging_id'] = max(0, (int) $attributes['assembly_packaging_id']);
        }

        if (array_key_exists('assembly_weight_kg', $attributes)) {
            $weight = $attributes['assembly_weight_kg'];
            $payload['assembly_weight_kg'] = $weight === null || $weight === ''
                ? null
                : (float) $weight;
        }

        if (array_key_exists('description', $attributes)) {
            $description = $attributes['description'];
            $payload['description'] = $description !== null && $description !== ''
                ? trim((string) $description)
                : null;
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

    private function normaliseSearch(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
