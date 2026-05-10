<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata;

use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentPackagingProfileRepository;
use App\Domain\Fulfillment\Masterdata\FulfillmentPackagingProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\Concerns\FlushesMasterdataCache;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentFulfillmentPackagingProfileRepository implements FulfillmentPackagingProfileRepository
{
    use FlushesMasterdataCache;

    public function all(): iterable
    {
        return FulfillmentPackagingProfileModel::query()
            ->ordered()
            ->get()
            ->map(fn (FulfillmentPackagingProfileModel $model) => $this->mapModel($model));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return PaginatedResult<FulfillmentPackagingProfile>
     */
    public function paginate(?int $perPage = null, array $filters = []): PaginatedResult
    {
        $perPage = $this->normalisePerPage($perPage);

        $query = FulfillmentPackagingProfileModel::query()
            ->ordered();

        if ($search = $this->normaliseSearch($filters['search'] ?? null)) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('package_name', 'like', "%{$search}%")
                    ->orWhere('packaging_code', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);

        /** @var array<FulfillmentPackagingProfile> $items */
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

    public function getById(Identifier $id): ?FulfillmentPackagingProfile
    {
        $model = FulfillmentPackagingProfileModel::find($id->toInt());

        return $model ? $this->mapModel($model) : null;
    }

    public function create(array $attributes): FulfillmentPackagingProfile
    {
        $model = new FulfillmentPackagingProfileModel;
        $model->fill($this->castAttributes($attributes));
        $model->save();

        $model->refresh();

        $this->flushMasterdataCatalogCache();

        return $this->mapModel($model);
    }

    public function update(Identifier $id, array $attributes): FulfillmentPackagingProfile
    {
        /** @var FulfillmentPackagingProfileModel|null $model */
        $model = FulfillmentPackagingProfileModel::find($id->toInt());
        if (! $model) {
            throw (new ModelNotFoundException)->setModel(FulfillmentPackagingProfileModel::class, [$id->toInt()]);
        }

        $model->fill($this->castAttributes($attributes));
        $model->save();

        $model->refresh();

        $this->flushMasterdataCatalogCache();

        return $this->mapModel($model);
    }

    public function delete(Identifier $id): void
    {
        FulfillmentPackagingProfileModel::whereKey($id->toInt())->delete();
        $this->flushMasterdataCatalogCache();
    }

    private function mapModel(FulfillmentPackagingProfileModel $model): FulfillmentPackagingProfile
    {
        return FulfillmentPackagingProfile::hydrate(
            Identifier::fromInt((int) $model->getKey()),
            $model->package_name,
            $model->packaging_code,
            (int) $model->length_mm,
            (int) $model->width_mm,
            (int) $model->height_mm,
            (int) $model->truck_slot_units,
            (int) $model->max_units_per_pallet_same_recipient,
            (int) $model->max_units_per_pallet_mixed_recipient,
            (int) $model->max_stackable_pallets_same_recipient,
            (int) $model->max_stackable_pallets_mixed_recipient,
            $model->notes,
        );
    }

    /**
     * @param  array<string,mixed>  $attributes
     * @return array<string, mixed>
     */
    private function castAttributes(array $attributes): array
    {
        $payload = [];

        if (array_key_exists('package_name', $attributes)) {
            $payload['package_name'] = trim((string) $attributes['package_name']);
        }

        if (array_key_exists('packaging_code', $attributes)) {
            $value = $attributes['packaging_code'];
            $payload['packaging_code'] = $value !== null && $value !== ''
                ? trim((string) $value)
                : null;
        }

        foreach (['length_mm', 'width_mm', 'height_mm', 'truck_slot_units'] as $key) {
            if (array_key_exists($key, $attributes)) {
                $payload[$key] = max(0, (int) $attributes[$key]);
            }
        }

        foreach ([
            'max_units_per_pallet_same_recipient',
            'max_units_per_pallet_mixed_recipient',
            'max_stackable_pallets_same_recipient',
            'max_stackable_pallets_mixed_recipient',
        ] as $key) {
            if (array_key_exists($key, $attributes)) {
                $payload[$key] = max(0, (int) $attributes[$key]);
            }
        }

        if (array_key_exists('notes', $attributes)) {
            $notes = $attributes['notes'];
            $payload['notes'] = $notes !== null && $notes !== ''
                ? trim((string) $notes)
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

    private function normaliseSearch(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
