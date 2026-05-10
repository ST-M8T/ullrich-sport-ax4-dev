<?php

namespace Tests\Feature\Monitoring;

use App\Application\Monitoring\DomainEventService;
use App\Domain\Monitoring\Contracts\DomainEventRepository;
use App\Mail\DomainEventAlertMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Fakes\NullDomainEventRepository;
use Tests\TestCase;

final class DomainEventAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_event_triggers_alerts(): void
    {
        Mail::fake();
        Http::fake([
            'https://example.com/webhook' => Http::response('ok', 200),
        ]);

        config()->set('monitoring.alerts', [
            'enabled' => true,
            'default_channels' => ['mail', 'slack'],
            'rules' => [
                'test.event' => ['severity' => 'critical', 'channels' => ['mail', 'slack']],
            ],
            'mail' => [
                'enabled' => true,
                'recipients' => ['ops@example.com'],
                'subject_prefix' => '[Test Alert]',
            ],
            'slack' => [
                'enabled' => true,
                'webhook' => 'https://example.com/webhook',
                'channel' => '#alerts',
            ],
        ]);

        /** @var DomainEventService $events */
        $this->app->forgetInstance(DomainEventService::class);
        $this->app->instance(DomainEventRepository::class, new NullDomainEventRepository);
        $events = $this->app->make(DomainEventService::class);

        $events->record('test.event', 'test_aggregate', '123', ['foo' => 'bar'], ['severity' => 'critical']);

        Mail::assertSent(DomainEventAlertMail::class, function (DomainEventAlertMail $mail): bool {
            return $mail->hasTo('ops@example.com');
        });

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://example.com/webhook'
                && str_contains($request->body(), 'test.event');
        });
    }

    public function test_unmatched_event_does_not_trigger_alerts(): void
    {
        Mail::fake();
        Http::fake();

        config()->set('monitoring.alerts', [
            'enabled' => true,
            'default_channels' => ['mail', 'slack'],
            'rules' => [
                'other.event' => ['severity' => 'warning'],
            ],
            'mail' => [
                'enabled' => true,
                'recipients' => ['ops@example.com'],
                'subject_prefix' => '[Test Alert]',
            ],
            'slack' => [
                'enabled' => true,
                'webhook' => 'https://example.com/webhook',
            ],
        ]);

        /** @var DomainEventService $events */
        $this->app->forgetInstance(DomainEventService::class);
        $this->app->instance(DomainEventRepository::class, new NullDomainEventRepository);
        $events = $this->app->make(DomainEventService::class);

        $events->record('unmatched.event', 'test_aggregate', '123');

        Mail::assertNothingSent();
        Http::assertNothingSent();
    }
}
