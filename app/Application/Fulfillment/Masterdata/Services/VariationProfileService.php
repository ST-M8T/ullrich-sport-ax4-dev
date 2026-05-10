<?php

namespace App\Application\Fulfillment\Masterdata\Services;

use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentAssemblyOptionRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentPackagingProfileRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentVariationProfileRepository;
use App\Domain\Fulfillment\Masterdata\FulfillmentAssemblyOption;
use App\Domain\Fulfillment\Masterdata\FulfillmentPackagingProfile;
use App\Domain\Fulfillment\Masterdata\FulfillmentVariationProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class VariationProfileService
{
    public function __construct(
        private readonly FulfillmentVariationProfileRepository $variationProfiles,
        private readonly FulfillmentPackagingProfileRepository $packagingProfiles,
        private readonly FulfillmentAssemblyOptionRepository $assemblyOptions,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     */
    public function create(array $payload): FulfillmentVariationProfile
    {
        /** @var array{item_id: int, variation_id?: int|null, variation_name?: string|null, default_state: string, default_packaging_id: int, default_weight_kg?: float|null, assembly_option_id?: int|null} $normalised */
        $normalised = $this->normalise($payload);
        $this->assertPackagingExists($normalised['default_packaging_id']);

        if (array_key_exists('assembly_option_id', $normalised) && $normalised['assembly_option_id'] !== null) {
            $this->assertAssemblyOptionExists($normalised['assembly_option_id']);
        }

        return $this->variationProfiles->create($normalised);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function update(int $id, array $payload): FulfillmentVariationProfile
    {
        $normalised = $this->normalise($payload, false);

        if (array_key_exists('default_packaging_id', $normalised)) {
            $this->assertPackagingExists($normalised['default_packaging_id']);
        }

        if (array_key_exists('assembly_option_id', $normalised) && $normalised['assembly_option_id'] !== null) {
            $this->assertAssemblyOptionExists($normalised['assembly_option_id']);
        }

        return $this->variationProfiles->update(
            Identifier::fromInt($id),
            $normalised,
        );
    }

    public function delete(int $id): void
    {
        $identifier = Identifier::fromInt($id);
        if (! $this->variationProfiles->getById($identifier)) {
            /** @phpstan-ignore-next-line ModelNotFoundException::setModel akzeptiert pragmatisch auch Domain-FQNs (siehe Backlog ARCH-8). */
            throw (new ModelNotFoundException)->setModel(FulfillmentVariationProfile::class, [$id]);
        }

        $this->variationProfiles->delete($identifier);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return PaginatedResult<FulfillmentVariationProfile>
     */
    public function paginate(int $perPage, array $filters = []): PaginatedResult
    {
        return $this->variationProfiles->paginate($perPage, $filters);
    }

    public function getById(Identifier $id): ?FulfillmentVariationProfile
    {
        return $this->variationProfiles->getById($id);
    }

    /**
     * @return Collection<int, FulfillmentPackagingProfile>
     */
    public function packagingProfiles(): Collection
    {
        /** @var iterable<int, FulfillmentPackagingProfile> $items */
        $items = $this->packagingProfiles->all();

        return Collection::make($items);
    }

    /**
     * @return Collection<int, \App\Domain\Fulfillment\Masterdata\FulfillmentAssemblyOption>
     */
    public function assemblyOptions(): Collection
    {
        /** @var iterable<int, \App\Domain\Fulfillment\Masterdata\FulfillmentAssemblyOption> $items */
        $items = $this->assemblyOptions->all();

        return Collection::make($items);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function normalise(array $payload, bool $requireAll = true): array
    {
        if ($requireAll) {
            foreach (['item_id', 'default_state', 'default_packaging_id'] as $required) {
                if (! array_key_exists($required, $payload)) {
                    throw new InvalidArgumentException("Missing required field {$required}.");
                }
            }
        }

        foreach (['item_id', 'default_packaging_id'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = (int) $payload[$key];
            }
        }

        if (array_key_exists('variation_id', $payload)) {
            $value = $payload['variation_id'];
            $payload['variation_id'] = $value === null || $value === ''
                ? null
                : (int) $value;
        }

        if (array_key_exists('variation_name', $payload)) {
            $payload['variation_name'] = $this->stringOrNull($payload['variation_name']);
        }

        if (array_key_exists('default_state', $payload)) {
            $payload['default_state'] = strtolower(trim((string) $payload['default_state']));
        }

        if (array_key_exists('default_weight_kg', $payload)) {
            $value = $payload['default_weight_kg'];
            $payload['default_weight_kg'] = $value === null || $value === ''
                ? null
                : (float) $value;
        }

        if (array_key_exists('assembly_option_id', $payload)) {
            $value = $payload['assembly_option_id'];
            $payload['assembly_option_id'] = $value === null || $value === ''
                ? null
                : (int) $value;
        }

        return $payload;
    }

    private function assertPackagingExists(int $packagingId): void
    {
        $identifier = Identifier::fromInt($packagingId);
        if (! $this->packagingProfiles->getById($identifier)) {
            /** @phpstan-ignore-next-line ModelNotFoundException::setModel akzeptiert pragmatisch auch Domain-FQNs (siehe Backlog ARCH-8). */
            throw (new ModelNotFoundException)->setModel(FulfillmentPackagingProfile::class, [$packagingId]);
        }
    }

    private function assertAssemblyOptionExists(int $assemblyOptionId): void
    {
        $identifier = Identifier::fromInt($assemblyOptionId);
        if (! $this->assemblyOptions->getById($identifier)) {
            /** @phpstan-ignore-next-line ModelNotFoundException::setModel akzeptiert pragmatisch auch Domain-FQNs (siehe Backlog ARCH-8). */
            throw (new ModelNotFoundException)->setModel(FulfillmentAssemblyOption::class, [$assemblyOptionId]);
        }
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
