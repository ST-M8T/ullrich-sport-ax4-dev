<?php

namespace App\Application\Configuration\Channels;

use App\Domain\Configuration\NotificationMessage;

interface NotificationChannel
{
    public function key(): string;

    public function isEnabled(): bool;

    public function send(NotificationMessage $message): bool;
}
