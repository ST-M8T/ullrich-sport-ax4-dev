<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Persistence\Dhl\Catalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlCatalogSyncStatusRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Persistence-level tests for the single-row catalog sync status table
 * (PROJ-2, t12).
 */
final class EloquentDhlCatalogSyncStatusRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private DhlCatalogSyncStatusRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->app->make(DhlCatalogSyncStatusRepository::class);
    }

    public function test_get_returns_default_initial_state(): void
    {
        $status = $this->repo->get();

        self::assertNull($status->lastAttemptAt);
        self::assertNull($status->lastSuccessAt);
        self::assertNull($status->lastError);
        self::assertSame(0, $status->consecutiveFailures);
        self::assertFalse($status->mailSentForFailureStreak);
    }

    public function test_record_attempt_sets_last_attempt_at(): void
    {
        $at = new DateTimeImmutable('2026-05-12T10:00:00+00:00');
        $this->repo->recordAttempt($at);

        $status = $this->repo->get();
        self::assertNotNull($status->lastAttemptAt);
        self::assertSame($at->format('Y-m-d H:i:s'), $status->lastAttemptAt->format('Y-m-d H:i:s'));
    }

    public function test_record_success_resets_failure_counters(): void
    {
        $this->repo->recordFailure('boom');
        $this->repo->recordFailure('boom2');
        $this->repo->markAlertMailSent();

        $at = new DateTimeImmutable('2026-05-12T11:00:00+00:00');
        $this->repo->recordSuccess($at);

        $status = $this->repo->get();
        self::assertSame(0, $status->consecutiveFailures);
        self::assertFalse($status->mailSentForFailureStreak);
        self::assertNull($status->lastError);
        self::assertNotNull($status->lastSuccessAt);
        self::assertSame($at->format('Y-m-d H:i:s'), $status->lastSuccessAt->format('Y-m-d H:i:s'));
    }

    public function test_record_failure_increments_counter_and_stores_message(): void
    {
        $first = $this->repo->recordFailure('first error');
        self::assertSame(1, $first->consecutiveFailures);
        self::assertSame('first error', $first->lastError);

        $second = $this->repo->recordFailure('second error');
        self::assertSame(2, $second->consecutiveFailures);
        self::assertSame('second error', $second->lastError);
    }

    public function test_mark_alert_mail_sent_flag_roundtrip(): void
    {
        self::assertFalse($this->repo->get()->mailSentForFailureStreak);

        $this->repo->recordFailure('x');
        $this->repo->markAlertMailSent();

        self::assertTrue($this->repo->get()->mailSentForFailureStreak);
    }
}
