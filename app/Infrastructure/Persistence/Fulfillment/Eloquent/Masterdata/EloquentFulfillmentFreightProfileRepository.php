<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata;

use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentFreightProfileRepository;
use App\Domain\Fulfillment\Masterdata\FulfillmentFreightProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\Concerns\FlushesMasterdataCache;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

final class EloquentFulfillmentFreightProfileRepository implements FulfillmentFreightProfileRepository
{
    use FlushesMasterdataCache;

    public function all(): iterable
    {
        return FulfillmentFreightProfileModel::query()
            ->ordered()
            ->get()
            ->map(fn (FulfillmentFreightProfileModel $model) => $this->mapModel($model));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return PaginatedResult<FulfillmentFreightProfile>
     */
    public function paginate(?int $perPage = null, array $filters = []): PaginatedResult
    {
        $perPage = $this->normalisePerPage($perPage);

        $query = FulfillmentFreightProfileModel::query()
            ->ordered();

        if ($search = $this->normaliseSearch($filters['search'] ?? null)) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('shipping_profile_id', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);

        /** @var array<FulfillmentFreightProfile> $items */
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

    public function getById(Identifier $shippingProfileId): ?FulfillmentFreightProfile
    {
        $model = FulfillmentFreightProfileModel::find($shippingProfileId->toInt());

        return $model ? $this->mapModel($model) : null;
    }

    public function create(array $attributes): FulfillmentFreightProfile
    {
        $model = new FulfillmentFreightProfileModel;
        $model->fill($this->castAttributes($attributes, true));
        $model->save();

        $model->refresh();

        $this->flushMasterdataCatalogCache();

        return $this->mapModel($model);
    }

    public function update(Identifier $shippingProfileId, array $attributes): FulfillmentFreightProfile
    {
        /** @var FulfillmentFreightProfileModel|null $model */
        $model = FulfillmentFreightProfileModel::find($shippingProfileId->toInt());
        if (! $model) {
            throw (new ModelNotFoundException)->setModel(FulfillmentFreightProfileModel::class, [$shippingProfileId->toInt()]);
        }

        $model->fill($this->castAttributes($attributes, false));
        $model->save();

        $model->refresh();

        $this->flushMasterdataCatalogCache();

        return $this->mapModel($model);
    }

    public function delete(Identifier $shippingProfileId): void
    {
        FulfillmentFreightProfileModel::whereKey($shippingProfileId->toInt())->delete();
        $this->flushMasterdataCatalogCache();
    }

    private function mapModel(FulfillmentFreightProfileModel $model): FulfillmentFreightProfile
    {
        $serviceCodes = $model->dhl_default_service_codes;
        if (is_string($serviceCodes)) {
            $serviceCodes = json_decode($serviceCodes, true) ?? null;
        }

        $shippingMapping = $model->shipping_method_mapping;
        if (is_string($shippingMapping)) {
            $shippingMapping = json_decode($shippingMapping, true) ?? null;
        }

        return FulfillmentFreightProfile::hydrate(
            Identifier::fromInt((int) $model->getKey()),
            $model->label,
            $model->dhl_product_id,
            is_array($serviceCodes) ? $serviceCodes : null,
            is_array($shippingMapping) ? $shippingMapping : null,
            $model->account_number,
        );
    }

    /**
     * @param  array<string,mixed>  $attributes
     * @return array<string,mixed>
     */
    private function castAttributes(array $attributes, bool $forCreate): array
    {
        $payload = [];

        if ($forCreate && ! array_key_exists('shipping_profile_id', $attributes)) {
            throw new InvalidArgumentException('shipping_profile_id attribute is required.');
        }

        if (array_key_exists('shipping_profile_id', $attributes)) {
            $payload['shipping_profile_id'] = max(0, (int) $attributes['shipping_profile_id']);
        }

        if (array_key_exists('label', $attributes)) {
            $value = $attributes['label'];
            $payload['label'] = $value !== null && $value !== ''
                ? trim((string) $value)
                : null;
        }

        if (array_key_exists('dhl_product_id', $attributes)) {
            $value = $attributes['dhl_product_id'];
            $payload['dhl_product_id'] = $value !== null && $value !== ''
                ? trim((string) $value)
                : null;
        }

        if (array_key_exists('dhl_default_service_codes', $attributes)) {
            $value = $attributes['dhl_default_service_codes'];
            if ($value === null || $value === '' || $value === []) {
                $payload['dhl_default_service_codes'] = null;
            } elseif (is_array($value)) {
                $payload['dhl_default_service_codes'] = json_encode(array_values($value));
            } else {
                $payload['dhl_default_service_codes'] = $value;
            }
        }

        if (array_key_exists('shipping_method_mapping', $attributes)) {
            $value = $attributes['shipping_method_mapping'];
            if ($value === null || $value === '' || $value === []) {
                $payload['shipping_method_mapping'] = null;
            } elseif (is_array($value)) {
                $payload['shipping_method_mapping'] = json_encode($value);
            } else {
                $payload['shipping_method_mapping'] = $value;
            }
        }

        if (array_key_exists('account_number', $attributes)) {
            $value = $attributes['account_number'];
            $payload['account_number'] = $value !== null && $value !== ''
                ? trim((string) $value)
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
