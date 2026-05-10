<?php

namespace App\Application\Fulfillment\Masterdata\Services;

use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentAssemblyOptionRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentPackagingProfileRepository;
use App\Domain\Fulfillment\Masterdata\FulfillmentAssemblyOption;
use App\Domain\Fulfillment\Masterdata\FulfillmentPackagingProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class AssemblyOptionService
{
    public function __construct(
        private readonly FulfillmentAssemblyOptionRepository $assemblyOptions,
        private readonly FulfillmentPackagingProfileRepository $packagingProfiles,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     */
    public function create(array $payload): FulfillmentAssemblyOption
    {
        /** @var array{assembly_item_id: int, assembly_packaging_id: int, assembly_weight_kg?: float|null, description?: string|null} $normalised */
        $normalised = $this->normalise($payload);
        $this->assertPackagingExists($normalised['assembly_packaging_id']);

        return $this->assemblyOptions->create($normalised);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return PaginatedResult<FulfillmentAssemblyOption>
     */
    public function paginate(int $perPage, array $filters = []): PaginatedResult
    {
        return $this->assemblyOptions->paginate($perPage, $filters);
    }

    public function getById(Identifier $id): ?FulfillmentAssemblyOption
    {
        return $this->assemblyOptions->getById($id);
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
     * @param  array<string,mixed>  $payload
     */
    public function update(int $id, array $payload): FulfillmentAssemblyOption
    {
        $normalised = $this->normalise($payload, false);

        if (array_key_exists('assembly_packaging_id', $normalised)) {
            $this->assertPackagingExists($normalised['assembly_packaging_id']);
        }

        return $this->assemblyOptions->update(
            Identifier::fromInt($id),
            $normalised,
        );
    }

    public function delete(int $id): void
    {
        $identifier = Identifier::fromInt($id);
        if (! $this->assemblyOptions->getById($identifier)) {
            /** @phpstan-ignore-next-line ModelNotFoundException::setModel akzeptiert pragmatisch auch Domain-FQNs (siehe Backlog ARCH-8). */
            throw (new ModelNotFoundException)->setModel(FulfillmentAssemblyOption::class, [$id]);
        }

        $this->assemblyOptions->delete($identifier);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function normalise(array $payload, bool $requireAll = true): array
    {
        if ($requireAll) {
            foreach (['assembly_item_id', 'assembly_packaging_id'] as $required) {
                if (! array_key_exists($required, $payload)) {
                    throw new InvalidArgumentException("Missing required field {$required}.");
                }
            }
        }

        if (array_key_exists('assembly_item_id', $payload)) {
            $payload['assembly_item_id'] = (int) $payload['assembly_item_id'];
        }

        if (array_key_exists('assembly_packaging_id', $payload)) {
            $payload['assembly_packaging_id'] = (int) $payload['assembly_packaging_id'];
        }

        if (array_key_exists('assembly_weight_kg', $payload)) {
            $value = $payload['assembly_weight_kg'];
            $payload['assembly_weight_kg'] = $value === null || $value === ''
                ? null
                : (float) $value;
        }

        if (array_key_exists('description', $payload)) {
            $payload['description'] = $this->stringOrNull($payload['description']);
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

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
