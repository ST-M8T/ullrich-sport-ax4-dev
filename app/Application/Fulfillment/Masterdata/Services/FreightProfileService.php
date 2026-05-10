<?php

namespace App\Application\Fulfillment\Masterdata\Services;

use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentFreightProfileRepository;
use App\Domain\Fulfillment\Masterdata\FulfillmentFreightProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

final class FreightProfileService
{
    public function __construct(
        private readonly FulfillmentFreightProfileRepository $freightProfiles,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     */
    public function create(array $payload): FulfillmentFreightProfile
    {
        /** @var array{shipping_profile_id: int, label?: string|null} $normalised */
        $normalised = $this->normalise($payload);

        return $this->freightProfiles->create($normalised);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return PaginatedResult<FulfillmentFreightProfile>
     */
    public function paginate(int $perPage, array $filters = []): PaginatedResult
    {
        return $this->freightProfiles->paginate($perPage, $filters);
    }

    public function getById(Identifier $id): ?FulfillmentFreightProfile
    {
        return $this->freightProfiles->getById($id);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function update(int $shippingProfileId, array $payload): FulfillmentFreightProfile
    {
        $identifier = Identifier::fromInt($shippingProfileId);
        $normalised = $this->normalise($payload, false);

        return $this->freightProfiles->update($identifier, $normalised);
    }

    public function delete(int $shippingProfileId): void
    {
        $identifier = Identifier::fromInt($shippingProfileId);
        if (! $this->freightProfiles->getById($identifier)) {
            /** @phpstan-ignore-next-line ModelNotFoundException::setModel akzeptiert pragmatisch auch Domain-FQNs (siehe Backlog ARCH-8). */
            throw (new ModelNotFoundException)->setModel(FulfillmentFreightProfile::class, [$shippingProfileId]);
        }

        $this->freightProfiles->delete($identifier);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function normalise(array $payload, bool $requireId = true): array
    {
        if ($requireId && ! array_key_exists('shipping_profile_id', $payload)) {
            throw new InvalidArgumentException('Missing required field shipping_profile_id.');
        }

        if (array_key_exists('shipping_profile_id', $payload)) {
            $payload['shipping_profile_id'] = (int) $payload['shipping_profile_id'];
        }

        if (array_key_exists('label', $payload)) {
            $payload['label'] = $this->stringOrNull($payload['label']);
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
