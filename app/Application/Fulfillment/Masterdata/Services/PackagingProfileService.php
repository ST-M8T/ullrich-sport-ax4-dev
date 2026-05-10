<?php

namespace App\Application\Fulfillment\Masterdata\Services;

use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentPackagingProfileRepository;
use App\Domain\Fulfillment\Masterdata\FulfillmentPackagingProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

final class PackagingProfileService
{
    public function __construct(
        private readonly FulfillmentPackagingProfileRepository $packagingProfiles,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     */
    public function create(array $payload): FulfillmentPackagingProfile
    {
        /** @var array{package_name: string, packaging_code?: string|null, length_mm: int, width_mm: int, height_mm: int, truck_slot_units: int, max_units_per_pallet_same_recipient: int, max_units_per_pallet_mixed_recipient: int, max_stackable_pallets_same_recipient: int, max_stackable_pallets_mixed_recipient: int, notes?: string|null} $normalised */
        $normalised = $this->normalise($payload);

        return $this->packagingProfiles->create($normalised);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return PaginatedResult<FulfillmentPackagingProfile>
     */
    public function paginate(int $perPage, array $filters = []): PaginatedResult
    {
        return $this->packagingProfiles->paginate($perPage, $filters);
    }

    public function getById(Identifier $id): ?FulfillmentPackagingProfile
    {
        return $this->packagingProfiles->getById($id);
    }

    /**
     * @return Collection<int, FulfillmentPackagingProfile>
     */
    public function all(): Collection
    {
        /** @var iterable<int, FulfillmentPackagingProfile> $items */
        $items = $this->packagingProfiles->all();

        return Collection::make($items);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function update(int $id, array $payload): FulfillmentPackagingProfile
    {
        return $this->packagingProfiles->update(
            Identifier::fromInt($id),
            $this->normalise($payload),
        );
    }

    public function delete(int $id): void
    {
        $identifier = Identifier::fromInt($id);

        if (! $this->packagingProfiles->getById($identifier)) {
            /** @phpstan-ignore-next-line ModelNotFoundException::setModel akzeptiert pragmatisch auch Domain-FQNs (siehe Backlog ARCH-8). */
            throw (new ModelNotFoundException)->setModel(FulfillmentPackagingProfile::class, [$id]);
        }

        $this->packagingProfiles->delete($identifier);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function normalise(array $payload): array
    {
        $numericKeys = [
            'length_mm',
            'width_mm',
            'height_mm',
            'truck_slot_units',
            'max_units_per_pallet_same_recipient',
            'max_units_per_pallet_mixed_recipient',
            'max_stackable_pallets_same_recipient',
            'max_stackable_pallets_mixed_recipient',
        ];

        foreach ($numericKeys as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = (int) $payload[$key];
            }
        }

        $payload['package_name'] = trim((string) ($payload['package_name'] ?? ''));

        if (array_key_exists('packaging_code', $payload)) {
            $payload['packaging_code'] = $this->stringOrNull($payload['packaging_code']);
        }

        if (array_key_exists('notes', $payload)) {
            $payload['notes'] = $this->stringOrNull($payload['notes']);
        }

        return $payload;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
