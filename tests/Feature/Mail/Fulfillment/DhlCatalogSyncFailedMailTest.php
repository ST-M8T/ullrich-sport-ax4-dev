<?php

declare(strict_types=1);

namespace Tests\Feature\Mail\Fulfillment;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\DhlCatalogBootstrapper;
use App\Application\Fulfillment\Integrations\Dhl\Catalog\SynchroniseDhlCatalogCommand;
use App\Application\Fulfillment\Integrations\Dhl\Catalog\SynchroniseDhlCatalogService;
use App\Mail\Fulfillment\DhlCatalogSyncFailedMail;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Mail/idempotency tests for the DHL catalog sync failure alert (PROJ-2, t12).
 */
final class DhlCatalogSyncFailedMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('dhl-catalog.alert_recipients', ['ops@example.test', 'lead@example.test']);
        config()->set('dhl-catalog.default_countries', ['DE']);
        config()->set('dhl-catalog.default_payer_codes', ['DAP']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_mail_renders_with_error_message_and_routing(): void
    {
        Mail::fake();

        $mail = new DhlCatalogSyncFailedMail(
            errorMessage: 'API unreachable',
            lastSuccessAt: new DateTimeImmutable('2026-05-01T00:00:00+00:00'),
            consecutiveFailures: 1,
            routingFilter: 'DE-AT',
            resultSummary: ['products_added' => 0],
        );

        // Build to verify the view variables are wired.
        $mail->build();

        self::assertSame('API unreachable', $mail->errorMessage);
        self::assertSame(1, $mail->consecutiveFailures);
        self::assertSame('DE-AT', $mail->routingFilter);
        self::assertSame('2026-05-01T00:00:00+00:00', $mail->lastSuccessAt?->format(DATE_ATOM));
    }

    public function test_alert_mail_is_sent_to_all_recipients_on_first_failure(): void
    {
        Mail::fake();
        $this->bindFailingBootstrapper();

        $service = $this->app->make(SynchroniseDhlCatalogService::class);
        $service->execute(new SynchroniseDhlCatalogCommand);

        Mail::assertSent(DhlCatalogSyncFailedMail::class, function (DhlCatalogSyncFailedMail $mail): bool {
            $mail->build();
            $recipients = array_map(static fn ($r): string => $r['address'], $mail->to);
            return in_array('ops@example.test', $recipients, true)
                && in_array('lead@example.test', $recipients, true);
        });
    }

    public function test_alert_mail_not_resent_on_second_consecutive_failure(): void
    {
        Mail::fake();
        $this->bindFailingBootstrapper();

        $service = $this->app->make(SynchroniseDhlCatalogService::class);
        $service->execute(new SynchroniseDhlCatalogCommand);
        // Same service instance, second failure → idempotency must kick in.
        $service->execute(new SynchroniseDhlCatalogCommand);

        Mail::assertSent(DhlCatalogSyncFailedMail::class, 1);
    }

    public function test_alert_mail_resent_after_recovery_then_new_failure(): void
    {
        Mail::fake();

        // 1st failure -> mail
        $this->bindFailingBootstrapper();
        $svc = $this->app->make(SynchroniseDhlCatalogService::class);
        $svc->execute(new SynchroniseDhlCatalogCommand);

        // Recovery via successful sync
        $this->bindSuccessfulBootstrapper();
        $svc = $this->app->make(SynchroniseDhlCatalogService::class);
        $svc->execute(new SynchroniseDhlCatalogCommand);

        // New failure -> mail again (streak reset)
        $this->bindFailingBootstrapper();
        $svc = $this->app->make(SynchroniseDhlCatalogService::class);
        $svc->execute(new SynchroniseDhlCatalogCommand);

        Mail::assertSent(DhlCatalogSyncFailedMail::class, 2);
    }

    public function test_no_mail_sent_when_recipients_config_empty(): void
    {
        Mail::fake();
        config()->set('dhl-catalog.alert_recipients', []);
        $this->bindFailingBootstrapper();

        $service = $this->app->make(SynchroniseDhlCatalogService::class);
        $service->execute(new SynchroniseDhlCatalogCommand);

        Mail::assertNothingSent();
    }

    private function bindFailingBootstrapper(): void
    {
        $mock = Mockery::mock(DhlCatalogBootstrapper::class);
        $mock->shouldReceive('bootstrap')
            ->andThrow(new RuntimeException('API unreachable: connection refused'));
        $this->app->instance(DhlCatalogBootstrapper::class, $mock);
        $this->app->forgetInstance(SynchroniseDhlCatalogService::class);
    }

    private function bindSuccessfulBootstrapper(): void
    {
        $mock = Mockery::mock(DhlCatalogBootstrapper::class);
        $mock->shouldReceive('bootstrap')->andReturn([
            'products' => [],
            'services' => [],
            'assignments' => [],
            'errors' => [],
            'counts' => [],
        ]);
        $this->app->instance(DhlCatalogBootstrapper::class, $mock);
        $this->app->forgetInstance(SynchroniseDhlCatalogService::class);
    }
}
