<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Admin\Settings\DhlCatalog;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\DhlCatalogAuditLogger;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlCatalogSource;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlAdditionalServiceModel;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlCatalogAuditLogModel;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlCatalogSyncStatusModel;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlProductModel;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlProductServiceAssignmentModel;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use DateTimeImmutable;

/**
 * Shared fixtures for the DhlCatalog controller feature tests (PROJ-6 / t15c).
 *
 * Engineering-Handbuch §75 (Frontend-DRY) is enforced for tests too: one
 * place for user/product/service/assignment/audit/sync-status fixtures.
 */
trait DhlCatalogControllerTestHelpers
{
    private function adminUser(): UserModel
    {
        return UserModel::query()->create([
            'username' => 'dhl-catalog-admin',
            'display_name' => 'DHL Catalog Admin',
            'email' => 'dhl-catalog-admin@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'admin',
            'must_change_password' => false,
            'disabled' => false,
        ]);
    }

    /**
     * `viewer` has `admin.access` but NO `dhl-catalog.*` permission —
     * perfect for the 403 path on every catalog route.
     */
    private function viewerUser(): UserModel
    {
        return UserModel::query()->create([
            'username' => 'dhl-catalog-viewer',
            'display_name' => 'DHL Catalog Viewer',
            'email' => 'dhl-catalog-viewer@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'viewer',
            'must_change_password' => false,
            'disabled' => false,
        ]);
    }

    /**
     * `operations` has `dhl-catalog.view` only — used to verify that a
     * permission-scoped sub-route (sync, audit) is denied while index works.
     */
    private function operationsUser(): UserModel
    {
        return UserModel::query()->create([
            'username' => 'dhl-catalog-ops',
            'display_name' => 'DHL Catalog Ops',
            'email' => 'dhl-catalog-ops@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'operations',
            'must_change_password' => false,
            'disabled' => false,
        ]);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createProduct(string $code = 'ECI', array $overrides = []): DhlProductModel
    {
        return DhlProductModel::query()->create(array_merge([
            'code' => $code,
            'name' => 'Product ' . $code,
            'description' => '',
            'market_availability' => 'B2B',
            'from_countries' => ['DE'],
            'to_countries' => ['AT'],
            'allowed_package_types' => ['PLT'],
            'weight_min_kg' => 0.0,
            'weight_max_kg' => 2500.0,
            'dim_max_l_cm' => 240.0,
            'dim_max_b_cm' => 120.0,
            'dim_max_h_cm' => 220.0,
            'valid_from' => '2020-01-01 00:00:00',
            'valid_until' => null,
            'deprecated_at' => null,
            'replaced_by_code' => null,
            'source' => DhlCatalogSource::SEED->value,
            'synced_at' => null,
        ], $overrides));
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createService(string $code = 'NOT', array $overrides = []): DhlAdditionalServiceModel
    {
        return DhlAdditionalServiceModel::query()->create(array_merge([
            'code' => $code,
            'name' => 'Service ' . $code,
            'description' => '',
            'category' => 'notification',
            'parameter_schema' => ['type' => 'object'],
            'deprecated_at' => null,
            'source' => DhlCatalogSource::SEED->value,
            'synced_at' => null,
        ], $overrides));
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createAssignment(
        string $productCode,
        string $serviceCode,
        array $overrides = [],
    ): DhlProductServiceAssignmentModel {
        return DhlProductServiceAssignmentModel::query()->create(array_merge([
            'product_code' => $productCode,
            'service_code' => $serviceCode,
            'from_country' => 'DE',
            'to_country' => 'AT',
            'payer_code' => 'DAP',
            'requirement' => 'allowed',
            'default_parameters' => [],
            'source' => DhlCatalogSource::SEED->value,
            'synced_at' => null,
        ], $overrides));
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createAuditEntry(array $overrides = []): DhlCatalogAuditLogModel
    {
        $row = new DhlCatalogAuditLogModel;
        $row->entity_type = $overrides['entity_type'] ?? DhlCatalogAuditLogger::ENTITY_PRODUCT;
        $row->entity_key = $overrides['entity_key'] ?? 'ECI';
        $row->action = $overrides['action'] ?? DhlCatalogAuditLogger::ACTION_UPDATED;
        $row->actor = $overrides['actor'] ?? 'system:dhl-sync';
        $row->diff = $overrides['diff'] ?? ['before' => [], 'after' => []];
        $row->created_at = $overrides['created_at'] ?? new DateTimeImmutable;
        $row->save();

        return $row;
    }

    private function setSyncStatus(
        ?DateTimeImmutable $lastAttemptAt = null,
        ?DateTimeImmutable $lastSuccessAt = null,
        ?string $lastError = null,
        int $consecutiveFailures = 0,
    ): DhlCatalogSyncStatusModel {
        $row = DhlCatalogSyncStatusModel::query()->find('current')
            ?? new DhlCatalogSyncStatusModel(['id' => 'current']);
        $row->id = 'current';
        $row->last_attempt_at = $lastAttemptAt;
        $row->last_success_at = $lastSuccessAt;
        $row->last_error = $lastError;
        $row->consecutive_failures = $consecutiveFailures;
        $row->mail_sent_for_failure_streak = false;
        $row->save();

        return $row;
    }
}
