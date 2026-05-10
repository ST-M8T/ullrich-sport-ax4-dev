<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata;

use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentSenderProfileRepository;
use App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\Concerns\FlushesMasterdataCache;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentFulfillmentSenderProfileRepository implements FulfillmentSenderProfileRepository
{
    use FlushesMasterdataCache;

    public function all(): iterable
    {
        return FulfillmentSenderProfileModel::query()
            ->ordered()
            ->get()
            ->map(fn (FulfillmentSenderProfileModel $model) => $this->mapModel($model));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return PaginatedResult<FulfillmentSenderProfile>
     */
    public function paginate(?int $perPage = null, array $filters = []): PaginatedResult
    {
        $perPage = $this->normalisePerPage($perPage);

        $query = FulfillmentSenderProfileModel::query()
            ->ordered();

        if ($country = $this->normaliseCountry($filters['country_iso2'] ?? null)) {
            $query->where('country_iso2', $country);
        }

        if ($search = $this->normaliseSearch($filters['search'] ?? null)) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('sender_code', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);

        /** @var array<FulfillmentSenderProfile> $items */
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

    public function findByCode(string $senderCode): ?FulfillmentSenderProfile
    {
        $model = FulfillmentSenderProfileModel::query()
            ->where('sender_code', strtolower(trim($senderCode)))
            ->first();

        return $model ? $this->mapModel($model) : null;
    }

    public function getById(Identifier $id): ?FulfillmentSenderProfile
    {
        $model = FulfillmentSenderProfileModel::find($id->toInt());

        return $model ? $this->mapModel($model) : null;
    }

    public function create(array $attributes): FulfillmentSenderProfile
    {
        $model = new FulfillmentSenderProfileModel;
        $model->fill($this->castAttributes($attributes));
        $model->save();

        $model->refresh();

        $this->flushMasterdataCatalogCache();

        return $this->mapModel($model);
    }

    public function update(Identifier $id, array $attributes): FulfillmentSenderProfile
    {
        /** @var FulfillmentSenderProfileModel|null $model */
        $model = FulfillmentSenderProfileModel::find($id->toInt());
        if (! $model) {
            throw (new ModelNotFoundException)->setModel(FulfillmentSenderProfileModel::class, [$id->toInt()]);
        }

        $model->fill($this->castAttributes($attributes));
        $model->save();

        $model->refresh();

        $this->flushMasterdataCatalogCache();

        return $this->mapModel($model);
    }

    public function delete(Identifier $id): void
    {
        FulfillmentSenderProfileModel::whereKey($id->toInt())->delete();
        $this->flushMasterdataCatalogCache();
    }

    private function mapModel(FulfillmentSenderProfileModel $model): FulfillmentSenderProfile
    {
        return FulfillmentSenderProfile::hydrate(
            Identifier::fromInt((int) $model->getKey()),
            $model->sender_code,
            $model->display_name,
            $model->company_name,
            $model->contact_person,
            $model->email,
            $model->phone,
            $model->street_name,
            $model->street_number,
            $model->address_addition,
            $model->postal_code,
            $model->city,
            $model->country_iso2,
        );
    }

    /**
     * @param  array<string,mixed>  $attributes
     * @return array<string,mixed>
     */
    private function castAttributes(array $attributes): array
    {
        $payload = [];

        if (array_key_exists('sender_code', $attributes)) {
            $payload['sender_code'] = strtolower(trim((string) $attributes['sender_code']));
        }

        foreach (['display_name', 'company_name', 'street_name', 'postal_code', 'city', 'country_iso2'] as $key) {
            if (array_key_exists($key, $attributes)) {
                $payload[$key] = trim((string) $attributes[$key]);
            }
        }

        foreach (['contact_person', 'email', 'phone', 'street_number', 'address_addition'] as $key) {
            if (array_key_exists($key, $attributes)) {
                $value = $attributes[$key];
                $payload[$key] = $value !== null && $value !== ''
                    ? trim((string) $value)
                    : null;
            }
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

    private function normaliseCountry(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtoupper(trim((string) $value));

        return $value === '' ? null : $value;
    }
}
