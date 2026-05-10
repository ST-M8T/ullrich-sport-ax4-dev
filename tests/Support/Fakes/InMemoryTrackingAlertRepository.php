<?php

namespace Tests\Support\Fakes;

use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Tracking\Contracts\TrackingAlertRepository;
use App\Domain\Tracking\TrackingAlert;

final class InMemoryTrackingAlertRepository implements TrackingAlertRepository
{
    /**
     * @var array<int,TrackingAlert>
     */
    private array $alerts = [];

    private int $nextId = 1;

    public function nextIdentity(): Identifier
    {
        return Identifier::fromInt($this->nextId++);
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array<int,TrackingAlert>
     */
    public function find(array $filters = []): iterable
    {
        $alerts = array_filter(
            $this->alerts,
            function (TrackingAlert $alert) use ($filters) {
                if (isset($filters['alert_type']) && $alert->alertType() !== $filters['alert_type']) {
                    return false;
                }

                if (isset($filters['severity']) && $alert->severity() !== $filters['severity']) {
                    return false;
                }

                if (isset($filters['channel']) && $alert->channel() !== $filters['channel']) {
                    return false;
                }

                if (array_key_exists('is_acknowledged', $filters)) {
                    $expected = (bool) $filters['is_acknowledged'];
                    if ($alert->isAcknowledged() !== $expected) {
                        return false;
                    }
                }

                return true;
            }
        );

        usort(
            $alerts,
            fn (TrackingAlert $a, TrackingAlert $b) => $b->createdAt() <=> $a->createdAt()
        );

        return array_values($alerts);
    }

    public function getById(Identifier $id): ?TrackingAlert
    {
        return $this->alerts[$id->toInt()] ?? null;
    }

    public function save(TrackingAlert $alert): void
    {
        $this->alerts[$alert->id()->toInt()] = $alert;
        if ($alert->id()->toInt() >= $this->nextId) {
            $this->nextId = $alert->id()->toInt() + 1;
        }
    }
}
