<?php

namespace Tests\Feature\Console;

use App\Application\Tracking\TrackingAlertService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Tracking\TrackingAlert;
use DateTimeImmutable;
use Mockery;
use Tests\TestCase;

final class TrackingRaiseAlertCommandTest extends TestCase
{
    public function test_raise_alert_command_invokes_service(): void
    {
        $service = Mockery::mock(TrackingAlertService::class);
        $alert = $this->alert(Identifier::fromInt(5));

        $service
            ->shouldReceive('raise')
            ->once()
            ->with(
                'delivery.delay',
                'warning',
                'Delayed',
                Mockery::on(fn ($identifier) => $identifier instanceof Identifier && $identifier->toInt() === 10),
                'mail',
                ['lane' => 'A1']
            )
            ->andReturn($alert);

        $this->app->instance(TrackingAlertService::class, $service);

        $this->artisan('tracking:alerts:raise', [
            'type' => 'delivery.delay',
            'severity' => 'warning',
            'message' => 'Delayed',
            '--shipment-id' => '10',
            '--channel' => 'mail',
            '--metadata' => json_encode(['lane' => 'A1'], JSON_THROW_ON_ERROR),
        ])->expectsOutput('Tracking alert #5 created (delivery.delay / warning).')
            ->assertExitCode(0);
    }

    public function test_raise_alert_command_validates_metadata_json(): void
    {
        $service = Mockery::mock(TrackingAlertService::class);
        $this->app->instance(TrackingAlertService::class, $service);

        $this->artisan('tracking:alerts:raise', [
            'type' => 'delivery.delay',
            'severity' => 'warning',
            'message' => 'Delayed',
            '--metadata' => '{invalid}',
        ])->expectsOutput('Metadata must be valid JSON.')
            ->assertExitCode(1);
    }

    private function alert(Identifier $id): TrackingAlert
    {
        $now = new DateTimeImmutable('-1 minute');

        return TrackingAlert::hydrate(
            $id,
            Identifier::fromInt(10),
            'delivery.delay',
            'warning',
            'mail',
            'Delayed',
            null,
            null,
            ['lane' => 'A1'],
            $now,
            $now,
        );
    }
}
