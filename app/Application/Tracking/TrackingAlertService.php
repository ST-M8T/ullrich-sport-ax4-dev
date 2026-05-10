<?php

namespace App\Application\Tracking;

use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Tracking\Contracts\TrackingAlertRepository;
use App\Domain\Tracking\TrackingAlert;
use DateTimeImmutable;

class TrackingAlertService
{
    public function __construct(private readonly TrackingAlertRepository $alerts) {}

    /**
     * @psalm-param array<string,mixed> $metadata
     */
    public function raise(
        string $alertType,
        string $severity,
        string $message,
        ?Identifier $shipmentId = null,
        ?string $channel = null,
        array $metadata = []
    ): TrackingAlert {
        $id = $this->alerts->nextIdentity();
        $now = new DateTimeImmutable;

        $alert = TrackingAlert::raise(
            $id,
            $alertType,
            $severity,
            $message,
            $shipmentId,
            $channel,
            $metadata,
            $now
        );

        $this->alerts->save($alert);

        return $alert;
    }

    public function markSent(Identifier $id): ?TrackingAlert
    {
        $alert = $this->alerts->getById($id);
        if (! $alert) {
            return null;
        }

        try {
            $updated = $alert->markSent(new DateTimeImmutable);
        } catch (\Throwable) {
            return null;
        }

        $this->alerts->save($updated);

        return $updated;
    }

    public function acknowledge(Identifier $id): ?TrackingAlert
    {
        $alert = $this->alerts->getById($id);
        if (! $alert) {
            return null;
        }

        try {
            $updated = $alert->acknowledge(new DateTimeImmutable);
        } catch (\Throwable) {
            return null;
        }

        $this->alerts->save($updated);

        return $updated;
    }

    public function get(Identifier $id): ?TrackingAlert
    {
        return $this->alerts->getById($id);
    }
}
