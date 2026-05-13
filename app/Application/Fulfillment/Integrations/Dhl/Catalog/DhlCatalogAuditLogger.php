<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Catalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlAdditionalService;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProduct;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProductServiceAssignment;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\AuditActor;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlCatalogAuditLogModel;
use DateTimeImmutable;

/**
 * Application service that records an immutable audit-trail entry for every
 * mutating catalog operation. Lives in Application (not Domain) because it
 * depends on Infrastructure (`DhlCatalogAuditLogModel`) and is called by
 * Repository implementations inside their persistence transaction.
 *
 * Idempotency: when before/after diffs are empty (no actual change), no row
 * is written — prevents sync-spam.
 */
class DhlCatalogAuditLogger
{
    public const ENTITY_PRODUCT = 'product';
    public const ENTITY_SERVICE = 'service';
    public const ENTITY_ASSIGNMENT = 'assignment';

    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DEPRECATED = 'deprecated';
    public const ACTION_RESTORED = 'restored';
    public const ACTION_DELETED = 'deleted';

    public function recordProductChange(
        string $action,
        string $entityKey,
        ?DhlProduct $before,
        ?DhlProduct $after,
        AuditActor $actor,
    ): void {
        $beforeArr = $before !== null ? $this->productToArray($before) : null;
        $afterArr = $after !== null ? $this->productToArray($after) : null;

        $this->write(self::ENTITY_PRODUCT, $entityKey, $action, $beforeArr, $afterArr, $actor);
    }

    public function recordServiceChange(
        string $action,
        string $entityKey,
        ?DhlAdditionalService $before,
        ?DhlAdditionalService $after,
        AuditActor $actor,
    ): void {
        $beforeArr = $before !== null ? $this->serviceToArray($before) : null;
        $afterArr = $after !== null ? $this->serviceToArray($after) : null;

        $this->write(self::ENTITY_SERVICE, $entityKey, $action, $beforeArr, $afterArr, $actor);
    }

    public function recordAssignmentChange(
        string $action,
        string $entityKey,
        ?DhlProductServiceAssignment $before,
        ?DhlProductServiceAssignment $after,
        AuditActor $actor,
    ): void {
        $beforeArr = $before !== null ? $this->assignmentToArray($before) : null;
        $afterArr = $after !== null ? $this->assignmentToArray($after) : null;

        $this->write(self::ENTITY_ASSIGNMENT, $entityKey, $action, $beforeArr, $afterArr, $actor);
    }

    /**
     * Public so repositories can build the composite key consistently.
     */
    public static function assignmentEntityKey(
        string $productCode,
        string $serviceCode,
        ?string $fromCountry,
        ?string $toCountry,
        ?string $payerCode,
    ): string {
        return sprintf(
            '%s:%s:%s:%s:%s',
            $productCode,
            $serviceCode,
            $fromCountry ?? '*',
            $toCountry ?? '*',
            $payerCode ?? '*',
        );
    }

    /**
     * @param  array<string,mixed>|null  $before
     * @param  array<string,mixed>|null  $after
     */
    private function write(
        string $entityType,
        string $entityKey,
        string $action,
        ?array $before,
        ?array $after,
        AuditActor $actor,
    ): void {
        $diff = $this->computeDiff($before, $after);

        // Idempotency: an update with no actual change writes nothing.
        // Creates / deletes still record (before xor after is null → diff non-empty).
        if ($diff === []) {
            return;
        }

        $row = new DhlCatalogAuditLogModel;
        $row->entity_type = $entityType;
        $row->entity_key = $entityKey;
        $row->action = $action;
        $row->actor = $actor->value;
        $row->diff = ['before' => $before, 'after' => $after, 'changed' => $diff];
        $row->created_at = new DateTimeImmutable;
        $row->save();
    }

    /**
     * Shallow per-property diff. JSON sub-trees are compared as wholes.
     *
     * @param  array<string,mixed>|null  $before
     * @param  array<string,mixed>|null  $after
     * @return array<string,array{before:mixed,after:mixed}>
     */
    private function computeDiff(?array $before, ?array $after): array
    {
        if ($before === null && $after === null) {
            return [];
        }
        $beforeSafe = $before ?? [];
        $afterSafe = $after ?? [];

        $keys = array_unique(array_merge(array_keys($beforeSafe), array_keys($afterSafe)));
        $changed = [];
        foreach ($keys as $key) {
            $b = $beforeSafe[$key] ?? null;
            $a = $afterSafe[$key] ?? null;
            if ($b !== $a) {
                $changed[$key] = ['before' => $b, 'after' => $a];
            }
        }

        return $changed;
    }

    /**
     * @return array<string,mixed>
     */
    private function productToArray(DhlProduct $p): array
    {
        return [
            'code' => $p->code()->value,
            'name' => $p->name(),
            'description' => $p->description(),
            'market_availability' => $p->marketAvailability()->value,
            'from_countries' => array_map(static fn ($c) => $c->value, $p->fromCountries()),
            'to_countries' => array_map(static fn ($c) => $c->value, $p->toCountries()),
            'allowed_package_types' => array_map(static fn ($pt) => $pt->code, $p->allowedPackageTypes()),
            'weight_min_kg' => $p->weightLimits()->minKg,
            'weight_max_kg' => $p->weightLimits()->maxKg,
            'dim_max_l_cm' => $p->dimensionLimits()->maxLengthCm,
            'dim_max_b_cm' => $p->dimensionLimits()->maxWidthCm,
            'dim_max_h_cm' => $p->dimensionLimits()->maxHeightCm,
            'valid_from' => $p->validFrom()->format(DATE_ATOM),
            'valid_until' => $p->validUntil()?->format(DATE_ATOM),
            'deprecated_at' => $p->deprecatedAt()?->format(DATE_ATOM),
            'replaced_by_code' => $p->replacedByCode()?->value,
            'source' => $p->source()->value,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serviceToArray(DhlAdditionalService $s): array
    {
        return [
            'code' => $s->code(),
            'name' => $s->name(),
            'description' => $s->description(),
            'category' => $s->category()->value,
            'parameter_schema' => $s->parameterSchema()->toArray(),
            'deprecated_at' => $s->deprecatedAt()?->format(DATE_ATOM),
            'source' => $s->source()->value,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function assignmentToArray(DhlProductServiceAssignment $a): array
    {
        return [
            'product_code' => $a->productCode()->value,
            'service_code' => $a->serviceCode(),
            'from_country' => $a->fromCountry()?->value,
            'to_country' => $a->toCountry()?->value,
            'payer_code' => $a->payerCode()?->value,
            'requirement' => $a->requirement()->value,
            'default_parameters' => $a->defaultParameters(),
            'source' => $a->source()->value,
        ];
    }
}
