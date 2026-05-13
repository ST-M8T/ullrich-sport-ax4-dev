<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dhl\Catalog\Repositories;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\DhlCatalogAuditLogger;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProductServiceAssignment;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductServiceAssignmentRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\AuditActor;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\CountryCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPayerCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlProductServiceAssignmentModel;
use App\Infrastructure\Persistence\Dhl\Catalog\Mappers\DhlCatalogPersistenceMapper;
use Illuminate\Support\Facades\DB;

final class EloquentDhlProductServiceAssignmentRepository implements DhlProductServiceAssignmentRepository
{
    public function __construct(
        private readonly DhlCatalogPersistenceMapper $mapper,
        private readonly DhlCatalogAuditLogger $auditLogger,
    ) {
    }

    public function findAllowedServicesFor(
        DhlProductCode $product,
        CountryCode $from,
        CountryCode $to,
        DhlPayerCode $payer,
    ): iterable {
        // Single query, leveraging idx_dpsa_lookup
        // (product_code, from_country, to_country, payer_code).
        // Per dimension we accept either an exact match or NULL (= global).
        $rows = DhlProductServiceAssignmentModel::query()
            ->where('product_code', $product->value)
            ->where(function ($q) use ($from): void {
                $q->whereNull('from_country')->orWhere('from_country', $from->value);
            })
            ->where(function ($q) use ($to): void {
                $q->whereNull('to_country')->orWhere('to_country', $to->value);
            })
            ->where(function ($q) use ($payer): void {
                $q->whereNull('payer_code')->orWhere('payer_code', $payer->value);
            })
            ->get();

        // Spezifitäts-Resolve: pro service_code gewinnt die spezifischste Zeile
        // (mehr non-NULL Routing-Dimensionen). Tie-breaker = höchste id (stable).
        $bestByService = [];
        foreach ($rows as $row) {
            $specificity = ($row->from_country !== null ? 1 : 0)
                + ($row->to_country !== null ? 1 : 0)
                + ($row->payer_code !== null ? 1 : 0);

            $current = $bestByService[$row->service_code] ?? null;
            if ($current === null
                || $specificity > $current['specificity']
                || ($specificity === $current['specificity'] && $row->id > $current['id'])
            ) {
                $bestByService[$row->service_code] = [
                    'model' => $row,
                    'specificity' => $specificity,
                    'id' => $row->id,
                ];
            }
        }

        foreach ($bestByService as $entry) {
            yield $this->mapper->toAssignmentEntity($entry['model']);
        }
    }

    public function findByProduct(DhlProductCode $product): iterable
    {
        $rows = DhlProductServiceAssignmentModel::query()
            ->where('product_code', $product->value)
            ->get();

        foreach ($rows as $row) {
            yield $this->mapper->toAssignmentEntity($row);
        }
    }

    public function save(DhlProductServiceAssignment $assignment, AuditActor $actor): void
    {
        DB::transaction(function () use ($assignment, $actor): void {
            $existing = DhlProductServiceAssignmentModel::query()
                ->where('product_code', $assignment->productCode()->value)
                ->where('service_code', $assignment->serviceCode())
                ->where(function ($q) use ($assignment): void {
                    $from = $assignment->fromCountry()?->value;
                    $from === null ? $q->whereNull('from_country') : $q->where('from_country', $from);
                })
                ->where(function ($q) use ($assignment): void {
                    $to = $assignment->toCountry()?->value;
                    $to === null ? $q->whereNull('to_country') : $q->where('to_country', $to);
                })
                ->where(function ($q) use ($assignment): void {
                    $payer = $assignment->payerCode()?->value;
                    $payer === null ? $q->whereNull('payer_code') : $q->where('payer_code', $payer);
                })
                ->first();

            $before = $existing !== null ? $this->mapper->toAssignmentEntity($existing) : null;

            $model = $this->mapper->toAssignmentModel($assignment, $existing);
            $model->save();

            $action = $before === null
                ? DhlCatalogAuditLogger::ACTION_CREATED
                : DhlCatalogAuditLogger::ACTION_UPDATED;

            $this->auditLogger->recordAssignmentChange(
                action: $action,
                entityKey: DhlCatalogAuditLogger::assignmentEntityKey(
                    $assignment->productCode()->value,
                    $assignment->serviceCode(),
                    $assignment->fromCountry()?->value,
                    $assignment->toCountry()?->value,
                    $assignment->payerCode()?->value,
                ),
                before: $before,
                after: $assignment,
                actor: $actor,
            );
        });
    }

    public function delete(DhlProductServiceAssignment $assignment, AuditActor $actor): void
    {
        DB::transaction(function () use ($assignment, $actor): void {
            $existing = DhlProductServiceAssignmentModel::query()
                ->where('product_code', $assignment->productCode()->value)
                ->where('service_code', $assignment->serviceCode())
                ->where(function ($q) use ($assignment): void {
                    $from = $assignment->fromCountry()?->value;
                    $from === null ? $q->whereNull('from_country') : $q->where('from_country', $from);
                })
                ->where(function ($q) use ($assignment): void {
                    $to = $assignment->toCountry()?->value;
                    $to === null ? $q->whereNull('to_country') : $q->where('to_country', $to);
                })
                ->where(function ($q) use ($assignment): void {
                    $payer = $assignment->payerCode()?->value;
                    $payer === null ? $q->whereNull('payer_code') : $q->where('payer_code', $payer);
                })
                ->first();

            if ($existing === null) {
                return;
            }
            $before = $this->mapper->toAssignmentEntity($existing);
            $existing->delete();

            $this->auditLogger->recordAssignmentChange(
                action: DhlCatalogAuditLogger::ACTION_DELETED,
                entityKey: DhlCatalogAuditLogger::assignmentEntityKey(
                    $assignment->productCode()->value,
                    $assignment->serviceCode(),
                    $assignment->fromCountry()?->value,
                    $assignment->toCountry()?->value,
                    $assignment->payerCode()?->value,
                ),
                before: $before,
                after: null,
                actor: $actor,
            );
        });
    }
}
