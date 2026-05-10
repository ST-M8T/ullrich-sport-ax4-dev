<?php

declare(strict_types=1);

namespace App\Application\Monitoring;

use App\Domain\Monitoring\AuditLogEntry;
use App\Domain\Monitoring\Contracts\AuditLogRepository;
use DateTimeImmutable;

class AuditLogger
{
    public function __construct(private readonly AuditLogRepository $logs) {}

    /**
     * @psalm-param array<string,mixed> $context
     */
    public function log(
        string $action,
        string $actorType = 'user',
        ?string $actorId = null,
        ?string $actorName = null,
        array $context = [],
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        $entry = AuditLogEntry::hydrate(
            0,
            $actorType,
            $actorId,
            $actorName,
            $action,
            $context,
            $ipAddress,
            $userAgent,
            new DateTimeImmutable,
        );

        $this->logs->append($entry);
    }
}
