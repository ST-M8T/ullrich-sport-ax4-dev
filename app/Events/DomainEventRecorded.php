<?php

namespace App\Events;

use App\Domain\Monitoring\DomainEventRecord;

final class DomainEventRecorded
{
    public function __construct(public readonly DomainEventRecord $record) {}
}
