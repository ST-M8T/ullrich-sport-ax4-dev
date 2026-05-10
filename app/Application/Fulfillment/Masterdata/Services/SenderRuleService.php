<?php

namespace App\Application\Fulfillment\Masterdata\Services;

use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentSenderProfileRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentSenderRuleRepository;
use App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile;
use App\Domain\Fulfillment\Masterdata\FulfillmentSenderRule;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class SenderRuleService
{
    public function __construct(
        private readonly FulfillmentSenderRuleRepository $senderRules,
        private readonly FulfillmentSenderProfileRepository $senderProfiles,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     */
    public function create(array $payload): FulfillmentSenderRule
    {
        /** @var array{priority: int, rule_type: string, match_value: string, target_sender_id: int, is_active: bool, description?: string|null} $normalised */
        $normalised = $this->normalise($payload);
        $this->assertSenderExists($normalised['target_sender_id']);

        return $this->senderRules->create($normalised);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function update(int $id, array $payload): FulfillmentSenderRule
    {
        $normalised = $this->normalise($payload, false);

        if (array_key_exists('target_sender_id', $normalised)) {
            $this->assertSenderExists($normalised['target_sender_id']);
        }

        return $this->senderRules->update(
            Identifier::fromInt($id),
            $normalised,
        );
    }

    public function delete(int $id): void
    {
        $identifier = Identifier::fromInt($id);
        if (! $this->senderRules->getById($identifier)) {
            /** @phpstan-ignore-next-line ModelNotFoundException::setModel akzeptiert pragmatisch auch Domain-FQNs (siehe Backlog ARCH-8). */
            throw (new ModelNotFoundException)->setModel(FulfillmentSenderRule::class, [$id]);
        }

        $this->senderRules->delete($identifier);
    }

    /**
     * @param  array<string,mixed>  $query
     * @return array{
     *     paginator: PaginatedResult<FulfillmentSenderRule>,
     *     filters: array<string,mixed>,
     *     per_page: int
     * }
     */
    public function list(array $query): array
    {
        $filters = $this->normaliseFilters($query);
        $perPage = $this->normalisePerPage($query['per_page'] ?? null);

        return [
            'paginator' => $this->senderRules->paginate($perPage, $filters),
            'filters' => array_merge($filters, ['per_page' => $perPage]),
            'per_page' => $perPage,
        ];
    }

    public function getById(Identifier $id): ?FulfillmentSenderRule
    {
        return $this->senderRules->getById($id);
    }

    /**
     * @return Collection<int, \App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile>
     */
    public function senderProfiles(): Collection
    {
        /** @var iterable<int, \App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile> $items */
        $items = $this->senderProfiles->all();

        return Collection::make($items);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function normalise(array $payload, bool $requireAll = true): array
    {
        if ($requireAll) {
            foreach (['priority', 'rule_type', 'match_value', 'target_sender_id'] as $required) {
                if (! array_key_exists($required, $payload)) {
                    throw new InvalidArgumentException("Missing required field {$required}.");
                }
            }
        }

        $normalized = [];

        if (array_key_exists('priority', $payload)) {
            $normalized['priority'] = max(0, (int) $payload['priority']);
        }

        if (array_key_exists('rule_type', $payload)) {
            $value = $this->normaliseSearch($payload['rule_type']);
            if ($value === null && $requireAll) {
                throw new InvalidArgumentException('rule_type is required.');
            }
            if ($value !== null) {
                $normalized['rule_type'] = $value;
            }
        }

        if (array_key_exists('match_value', $payload)) {
            $value = $this->normaliseSearch($payload['match_value']);
            if ($value === null && $requireAll) {
                throw new InvalidArgumentException('match_value is required.');
            }
            if ($value !== null) {
                $normalized['match_value'] = $value;
            }
        }

        if (array_key_exists('target_sender_id', $payload)) {
            $senderId = $this->normaliseInt($payload['target_sender_id']);
            if ($senderId === null && $requireAll) {
                throw new InvalidArgumentException('target_sender_id is required.');
            }
            if ($senderId !== null) {
                $normalized['target_sender_id'] = $senderId;
            }
        }

        if (array_key_exists('is_active', $payload)) {
            $normalized['is_active'] = (bool) $payload['is_active'];
        }

        if (array_key_exists('description', $payload)) {
            $normalized['description'] = $this->stringOrNull($payload['description']);
        }

        return $normalized;
    }

    private function assertSenderExists(int $senderId): void
    {
        $identifier = Identifier::fromInt($senderId);
        if (! $this->senderProfiles->getById($identifier)) {
            /** @phpstan-ignore-next-line ModelNotFoundException::setModel akzeptiert pragmatisch auch Domain-FQNs (siehe Backlog ARCH-8). */
            throw (new ModelNotFoundException)->setModel(FulfillmentSenderProfile::class, [$senderId]);
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

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    private function normaliseFilters(array $filters): array
    {
        $normalized = [];

        if (array_key_exists('target_sender_id', $filters)) {
            $value = $this->normaliseInt($filters['target_sender_id']);
            if ($value !== null) {
                $normalized['target_sender_id'] = $value;
            }
        }

        if (array_key_exists('rule_type', $filters)) {
            $value = $this->normaliseSearch($filters['rule_type']);
            if ($value !== null) {
                $normalized['rule_type'] = $value;
            }
        }

        if (array_key_exists('is_active', $filters)) {
            $value = $this->normaliseBoolean($filters['is_active']);
            if ($value !== null) {
                $normalized['is_active'] = $value;
            }
        }

        if (array_key_exists('search', $filters)) {
            $value = $this->normaliseSearch($filters['search']);
            if ($value !== null) {
                $normalized['search'] = $value;
            }
        }

        return $normalized;
    }

    private function normalisePerPage(mixed $perPage): int
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

    private function normaliseBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => null,
        };
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
