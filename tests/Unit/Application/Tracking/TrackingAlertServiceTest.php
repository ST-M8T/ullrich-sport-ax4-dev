<?php

namespace Tests\Unit\Application\Tracking;

use App\Application\Tracking\TrackingAlertService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Tracking\Contracts\TrackingAlertRepository;
use App\Domain\Tracking\TrackingAlert;
use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class TrackingAlertServiceTest extends TestCase
{
    private TrackingAlertRepository&MockInterface $repository;

    private TrackingAlertService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(TrackingAlertRepository::class);
        $this->service = new TrackingAlertService($this->repository);
    }

    public function test_raise_creates_alert_with_metadata(): void
    {
        $identifier = Identifier::fromInt(1);

        $this->repository
            ->shouldReceive('nextIdentity')
            ->once()
            ->andReturn($identifier);

        $this->repository
            ->shouldReceive('save')
            ->once()
            ->withArgs(function (TrackingAlert $alert) use ($identifier): bool {
                $this->assertTrue($alert->id()->equals($identifier));
                $this->assertSame('delivery.delay', $alert->alertType());
                $this->assertSame('warning', $alert->severity());
                $this->assertSame('mail', $alert->channel());
                $this->assertSame('Shipment delayed', $alert->message());
                $this->assertSame(['tracking_number' => 'DHL123'], $alert->metadata());
                $this->assertNull($alert->sentAt());
                $this->assertNull($alert->acknowledgedAt());

                return true;
            });

        $alert = $this->service->raise(
            'delivery.delay',
            'warning',
            'Shipment delayed',
            Identifier::fromInt(321),
            'mail',
            ['tracking_number' => 'DHL123']
        );

        $this->assertSame('delivery.delay', $alert->alertType());
        $this->assertSame('warning', $alert->severity());
        $this->assertSame(['tracking_number' => 'DHL123'], $alert->metadata());
    }

    public function test_mark_sent_returns_null_when_alert_missing(): void
    {
        $identifier = Identifier::fromInt(5);

        $this->repository
            ->shouldReceive('getById')
            ->with($identifier)
            ->once()
            ->andReturnNull();

        $this->assertNull($this->service->markSent($identifier));
    }

    public function test_mark_sent_updates_timestamp(): void
    {
        $identifier = Identifier::fromInt(11);
        $existing = $this->alertInstance($identifier);

        $this->repository
            ->shouldReceive('getById')
            ->with($identifier)
            ->once()
            ->andReturn($existing);

        $this->repository
            ->shouldReceive('save')
            ->once()
            ->withArgs(function (TrackingAlert $alert) use ($identifier): bool {
                $this->assertTrue($alert->id()->equals($identifier));
                $this->assertNotNull($alert->sentAt());
                $this->assertNull($alert->acknowledgedAt());

                return true;
            });

        $updated = $this->service->markSent($identifier);

        $this->assertNotNull($updated);
        $this->assertTrue($updated->id()->equals($identifier));
        $this->assertNotNull($updated->sentAt());
    }

    public function test_acknowledge_updates_acknowledged_timestamp(): void
    {
        $identifier = Identifier::fromInt(42);
        $existing = $this->alertInstance($identifier, new DateTimeImmutable('-1 minute'));

        $this->repository
            ->shouldReceive('getById')
            ->with($identifier)
            ->once()
            ->andReturn($existing);

        $this->repository
            ->shouldReceive('save')
            ->once()
            ->withArgs(function (TrackingAlert $alert) use ($identifier): bool {
                $this->assertTrue($alert->id()->equals($identifier));
                $this->assertNotNull($alert->acknowledgedAt());

                return true;
            });

        $updated = $this->service->acknowledge($identifier);

        $this->assertNotNull($updated);
        $this->assertNotNull($updated->acknowledgedAt());
    }

    private function alertInstance(Identifier $id, ?DateTimeImmutable $sentAt = null): TrackingAlert
    {
        $now = new DateTimeImmutable('-10 minutes');

        return TrackingAlert::hydrate(
            $id,
            null,
            'delivery.delay',
            'warning',
            'mail',
            'Shipment delayed',
            $sentAt,
            null,
            ['tracking_number' => 'ABC'],
            $now,
            $now,
        );
    }
}
