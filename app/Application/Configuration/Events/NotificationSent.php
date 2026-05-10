<?php

declare(strict_types=1);

namespace App\Application\Configuration\Events;

use App\Domain\Configuration\NotificationMessage;
use DateTimeImmutable;

final class NotificationSent
{
    public function __construct(
        public readonly NotificationMessage $message,
        public readonly string $channel,
        public readonly DateTimeImmutable $sentAt,
        public readonly ?string $recipient,
        public readonly ?string $template,
    ) {}
}
