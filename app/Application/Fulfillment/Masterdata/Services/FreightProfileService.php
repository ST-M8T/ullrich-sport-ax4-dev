<?php

namespace App\Application\Fulfillment\Masterdata\Services;

use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentFreightProfileRepository;
use App\Domain\Fulfillment\Masterdata\Exceptions\FreightProfileNotFoundException;
use App\Domain\Fulfillment\Masterdata\FulfillmentFreightProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
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
     * @return array<int, FulfillmentFreightProfile>
     */
    public function all(): array
    {
        $profiles = [];
        foreach ($this->freightProfiles->all() as $profile) {
            $profiles[] = $profile;
        }

        return $profiles;
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

    /**
     * @param  array<int, string>  $serviceCodes
     * @param  array<string, array{product_id: string, service_codes?: array<int, string>}>  $mapping
     */
    public function updateDhlMappings(
        int $shippingProfileId,
        ?string $dhlProductId,
        array $serviceCodes,
        array $mapping,
        ?string $accountNumber,
    ): FulfillmentFreightProfile {
        $identifier = Identifier::fromInt($shippingProfileId);
        $normalised = [
            'dhl_product_id' => $this->stringOrNull($dhlProductId),
            'dhl_default_service_codes' => $serviceCodes,
            'shipping_method_mapping' => $mapping,
            'account_number' => $this->stringOrNull($accountNumber),
        ];

        return $this->freightProfiles->update($identifier, $normalised);
    }

    public function delete(int $shippingProfileId): void
    {
        $identifier = Identifier::fromInt($shippingProfileId);
        if (! $this->freightProfiles->getById($identifier)) {
            throw new FreightProfileNotFoundException($shippingProfileId);
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

        if (array_key_exists('dhl_product_id', $payload)) {
            $payload['dhl_product_id'] = $this->stringOrNull($payload['dhl_product_id']);
        }

        if (array_key_exists('dhl_default_service_codes', $payload)) {
            $value = $payload['dhl_default_service_codes'];
            if ($value === null || $value === '' || $value === []) {
                $payload['dhl_default_service_codes'] = null;
            } elseif (is_array($value)) {
                $payload['dhl_default_service_codes'] = array_values($value);
            }
        }

        if (array_key_exists('shipping_method_mapping', $payload)) {
            $value = $payload['shipping_method_mapping'];
            if ($value === null || $value === '' || $value === []) {
                $payload['shipping_method_mapping'] = null;
            }
        }

        if (array_key_exists('account_number', $payload)) {
            $payload['account_number'] = $this->stringOrNull($payload['account_number']);
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
