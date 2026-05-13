<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Admin\Settings\DhlCatalog;

use App\Http\Controllers\Admin\Settings\DhlCatalog\DhlCatalogSyncTriggerController;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlCatalogAuditLogModel;
use App\Jobs\Fulfillment\Dhl\RunDhlCatalogSyncJob;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Feature tests for the manual sync trigger controller (PROJ-6 / t15c).
 *
 * Engineering-Handbuch §24 (Idempotenz): doubles-trigger window enforces 60s.
 */
final class DhlCatalogSyncTriggerControllerTest extends TestCase
{
    use DhlCatalogControllerTestHelpers;
    use RefreshDatabase;

    private const TRIGGER_URI = '/admin/settings/dhl/katalog/sync';

    private const STATUS_URI = '/admin/settings/dhl/katalog/sync/status';

    public function test_trigger_redirects_unauthenticated_to_login(): void
    {
        $response = $this->post(self::TRIGGER_URI);

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_trigger_returns_403_for_user_without_sync_permission(): void
    {
        // operations role has dhl-catalog.view but NOT dhl-catalog.sync.
        $this->actingAs($this->operationsUser());

        $response = $this->post(self::TRIGGER_URI);

        $response->assertForbidden();
    }

    public function test_trigger_dispatches_sync_job_for_admin(): void
    {
        Queue::fake();
        $this->actingAs($this->adminUser());

        $response = $this->post(self::TRIGGER_URI);

        $response->assertRedirect(route('admin.settings.dhl.catalog.index'));
        $response->assertSessionHas('success');

        Queue::assertPushed(RunDhlCatalogSyncJob::class, function (RunDhlCatalogSyncJob $job): bool {
            return str_contains($job->actor, 'dhl-catalog-admin@example.com');
        });

        // §18: a "manual_sync_triggered" audit row is written by the controller.
        $audit = DhlCatalogAuditLogModel::query()->first();
        $this->assertNotNull($audit);
        $this->assertSame('product', $audit->entity_type);
        $this->assertSame('*', $audit->entity_key);
        $this->assertSame(['event' => 'manual_sync_triggered'], $audit->diff);
    }

    public function test_trigger_returns_409_when_sync_already_running(): void
    {
        Queue::fake();
        // last_attempt 30s ago, no later success → running.
        $this->setSyncStatus(
            lastAttemptAt: (new DateTimeImmutable)->modify('-30 seconds'),
            lastSuccessAt: null,
        );
        $this->actingAs($this->adminUser());

        $response = $this->post(self::TRIGGER_URI);

        $response->assertStatus(Response::HTTP_CONFLICT);
        $response->assertSessionHas('error');
        Queue::assertNothingPushed();
    }

    public function test_trigger_allowed_again_after_running_window_passed(): void
    {
        Queue::fake();
        // last_attempt older than the 60s cooldown window → not running.
        $this->setSyncStatus(
            lastAttemptAt: (new DateTimeImmutable)->modify(
                '-' . (DhlCatalogSyncTriggerController::RUNNING_WINDOW_SECONDS + 5) . ' seconds'
            ),
            lastSuccessAt: null,
        );
        $this->actingAs($this->adminUser());

        $response = $this->post(self::TRIGGER_URI);

        $response->assertRedirect(route('admin.settings.dhl.catalog.index'));
        $response->assertSessionHas('success');
        Queue::assertPushed(RunDhlCatalogSyncJob::class);
    }

    public function test_status_returns_json_with_expected_structure_for_admin(): void
    {
        $lastAttempt = new DateTimeImmutable('2026-05-12 10:00:00');
        $lastSuccess = new DateTimeImmutable('2026-05-12 10:05:00');
        $this->setSyncStatus(
            lastAttemptAt: $lastAttempt,
            lastSuccessAt: $lastSuccess,
            lastError: 'Vorheriger Fehler',
            consecutiveFailures: 0,
        );
        $this->actingAs($this->adminUser());

        $response = $this->get(self::STATUS_URI);

        $response->assertOk();
        $response->assertJsonStructure([
            'running',
            'last_attempt_at',
            'last_success_at',
            'last_error',
            'consecutive_failures',
        ]);
        $payload = $response->json();
        $this->assertFalse($payload['running']);
        $this->assertSame($lastAttempt->format(DATE_ATOM), $payload['last_attempt_at']);
        $this->assertSame($lastSuccess->format(DATE_ATOM), $payload['last_success_at']);
        $this->assertSame(0, $payload['consecutive_failures']);
    }

    public function test_status_returns_403_for_user_without_sync_permission(): void
    {
        $this->actingAs($this->operationsUser());

        $response = $this->get(self::STATUS_URI);

        $response->assertForbidden();
    }

    public function test_status_redirects_unauthenticated_to_login(): void
    {
        $response = $this->get(self::STATUS_URI);

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }
}
