<?php

namespace App\Application\Fulfillment\Masterdata\Services;

use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentSenderProfileRepository;
use App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

final class SenderProfileService
{
    public function __construct(
        private readonly FulfillmentSenderProfileRepository $senderProfiles,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     */
    public function create(array $payload): FulfillmentSenderProfile
    {
        /** @var array{sender_code: string, display_name: string, company_name: string, contact_person?: string|null, email?: string|null, phone?: string|null, street_name: string, street_number?: string|null, address_addition?: string|null, postal_code: string, city: string, country_iso2: string} $normalised */
        $normalised = $this->normalise($payload);

        return $this->senderProfiles->create($normalised);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return PaginatedResult<FulfillmentSenderProfile>
     */
    public function paginate(int $perPage, array $filters = []): PaginatedResult
    {
        return $this->senderProfiles->paginate($perPage, $filters);
    }

    public function getById(Identifier $id): ?FulfillmentSenderProfile
    {
        return $this->senderProfiles->getById($id);
    }

    /**
     * @return array<int,FulfillmentSenderProfile>
     */
    public function all(): array
    {
        $profiles = $this->senderProfiles->all();

        return is_array($profiles) ? $profiles : iterator_to_array($profiles, false);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function update(int $id, array $payload): FulfillmentSenderProfile
    {
        $normalised = $this->normalise($payload, false);

        return $this->senderProfiles->update(
            Identifier::fromInt($id),
            $normalised,
        );
    }

    public function delete(int $id): void
    {
        $identifier = Identifier::fromInt($id);
        if (! $this->senderProfiles->getById($identifier)) {
            /** @phpstan-ignore-next-line ModelNotFoundException::setModel akzeptiert pragmatisch auch Domain-FQNs (siehe Backlog ARCH-8). */
            throw (new ModelNotFoundException)->setModel(FulfillmentSenderProfile::class, [$id]);
        }

        $this->senderProfiles->delete($identifier);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function normalise(array $payload, bool $requireAll = true): array
    {
        if ($requireAll) {
            foreach (['sender_code', 'display_name', 'company_name', 'street_name', 'postal_code', 'city', 'country_iso2'] as $required) {
                if (! array_key_exists($required, $payload)) {
                    throw new InvalidArgumentException("Missing required field {$required}.");
                }
            }
        }

        foreach (['sender_code', 'display_name', 'company_name', 'street_name', 'postal_code', 'city', 'country_iso2'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = trim((string) $payload[$key]);
            }
        }

        foreach (['contact_person', 'email', 'phone', 'street_number', 'address_addition'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = $this->stringOrNull($payload[$key]);
            }
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
