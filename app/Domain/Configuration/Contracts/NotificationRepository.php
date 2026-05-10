<?php

declare(strict_types=1);

namespace App\Domain\Configuration\Contracts;

use App\Domain\Configuration\NotificationMessage;
use App\Domain\Shared\ValueObjects\Identifier;

interface NotificationRepository
{
    public function nextIdentity(): Identifier;

    public function save(NotificationMessage $notification): void;

    public function getById(Identifier $id): ?NotificationMessage;

    /**
     * @param  array<string,mixed>  $filters
     * @return iterable<NotificationMessage>
     */
    public function search(array $filters = [], int $limit = 100, int $offset = 0): iterable;
}
