<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Contracts;

use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Tracking\TrackingJob;
use DateTimeImmutable;

interface TrackingJobRepository
{
    public function nextIdentity(): Identifier;

    /**
     * @param  array<string,mixed>  $filters
     * @return iterable<TrackingJob>
     */
    public function find(array $filters = []): iterable;

    public function getById(Identifier $id): ?TrackingJob;

    public function save(TrackingJob $job): void;

    public function findLatestForType(string $jobType): ?TrackingJob;

    /**
     * @return iterable<TrackingJob>
     */
    public function findDueJobs(DateTimeImmutable $cutoff, int $limit = 50): iterable;
}
