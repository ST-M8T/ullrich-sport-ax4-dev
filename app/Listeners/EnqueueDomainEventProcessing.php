<?php

namespace App\Listeners;

use App\Events\DomainEventRecorded;
use App\Jobs\ProcessDomainEvent;

final class EnqueueDomainEventProcessing
{
    public function handle(DomainEventRecorded $event): void
    {
        ProcessDomainEvent::dispatch($event->record);
    }
}
