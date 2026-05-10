<?php

namespace App\Application\Tracking\Resources;

use App\Domain\Tracking\TrackingAlert;

final class TrackingAlertResource
{
    private function __construct(private readonly TrackingAlert $alert) {}

    public static function fromAlert(TrackingAlert $alert): self
    {
        return new self($alert);
    }

    public function domain(): TrackingAlert
    {
        return $this->alert;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->alert->id()->toInt(),
            'shipment_id' => $this->alert->shipmentId()?->toInt(),
            'alert_type' => $this->alert->alertType(),
            'severity' => $this->alert->severity(),
            'channel' => $this->alert->channel(),
            'message' => $this->alert->message(),
            'sent_at' => $this->alert->sentAt()?->format(DATE_ATOM),
            'acknowledged_at' => $this->alert->acknowledgedAt()?->format(DATE_ATOM),
            'metadata' => $this->alert->metadata(),
            'created_at' => $this->alert->createdAt()->format(DATE_ATOM),
            'updated_at' => $this->alert->updatedAt()->format(DATE_ATOM),
            'is_sent' => $this->alert->isSent(),
            'is_acknowledged' => $this->alert->isAcknowledged(),
        ];
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (! method_exists($this->alert, $name)) {
            throw new \BadMethodCallException(sprintf('Method %s::%s does not exist.', TrackingAlert::class, $name));
        }

        return $this->alert->{$name}(...$arguments);
    }
}
