<?php

declare(strict_types=1);

namespace App\Domain\Dispatch;

use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use InvalidArgumentException;

use function str_ends_with;

/**
 * Aggregate root representing a dispatch list.
 *
 * @psalm-immutable
 */
final class DispatchList
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_EXPORTED = 'exported';

    private const VALID_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
        self::STATUS_EXPORTED,
    ];

    /**
     * @param  array<int,DispatchScan>  $scans
     */
    private function __construct(
        private readonly Identifier $id,
        private readonly ?string $reference,
        private readonly ?string $title,
        private readonly string $status,
        private readonly ?Identifier $createdByUserId,
        private readonly ?Identifier $closedByUserId,
        private readonly ?DateTimeImmutable $closeRequestedAt,
        private readonly ?string $closeRequestedBy,
        private readonly ?DateTimeImmutable $closedAt,
        private readonly ?DateTimeImmutable $exportedAt,
        private readonly ?string $exportFilename,
        private readonly ?int $totalPackages,
        private readonly ?int $totalOrders,
        private readonly ?int $totalTruckSlots,
        private readonly ?string $notes,
        private readonly DispatchMetrics $metrics,
        private readonly array $scans,
        private readonly int $scanCount,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {
        // Aggregate hydrates via named constructors only.
    }

    /**
     * @param  array<int,DispatchScan>  $scans
     */
    public static function hydrate(
        Identifier $id,
        ?string $reference,
        ?string $title,
        string $status,
        ?Identifier $createdByUserId,
        ?Identifier $closedByUserId,
        ?DateTimeImmutable $closeRequestedAt,
        ?string $closeRequestedBy,
        ?DateTimeImmutable $closedAt,
        ?DateTimeImmutable $exportedAt,
        ?string $exportFilename,
        ?int $totalPackages,
        ?int $totalOrders,
        ?int $totalTruckSlots,
        ?string $notes,
        DispatchMetrics $metrics,
        array $scans,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?int $scanCount = null,
    ): self {
        $normalized_status = self::sanitize_status($status);
        $normalized_scans = self::normalize_scans($scans);
        $normalized_total_packages = self::sanitize_nullable_non_negative_int($totalPackages, 'total_packages');
        $normalized_total_orders = self::sanitize_nullable_non_negative_int($totalOrders, 'total_orders');
        $normalized_total_truck_slots = self::sanitize_nullable_non_negative_int($totalTruckSlots, 'total_truck_slots');
        $normalized_export_filename = self::sanitize_optional_export_filename($exportFilename);
        [$normalized_status, $closedByUserId, $closedAt, $exportedAt, $normalized_export_filename] = self::normalizeStatusForPersistence(
            $normalized_status,
            $closedByUserId,
            $closedAt,
            $exportedAt,
            $normalized_export_filename
        );
        self::guard_chronology($createdAt, $updatedAt);
        self::guard_temporal_consistency(
            $createdAt,
            $updatedAt,
            $closeRequestedAt,
            $closedAt,
            $exportedAt
        );
        $normalized_scan_count = self::sanitize_scan_count($scanCount, $normalized_scans);
        self::guard_status_consistency(
            $normalized_status,
            $createdByUserId,
            $closedByUserId,
            $closeRequestedAt,
            $closedAt,
            $exportedAt,
            $normalized_total_packages,
            $normalized_total_orders,
            $normalized_total_truck_slots,
            $normalized_scan_count,
            $normalized_export_filename
        );

        return new self(
            $id,
            self::sanitize_nullable_string($reference),
            self::sanitize_nullable_string($title),
            $normalized_status,
            $createdByUserId,
            $closedByUserId,
            $closeRequestedAt,
            self::sanitize_nullable_string($closeRequestedBy),
            $closedAt,
            $exportedAt,
            $normalized_export_filename,
            $normalized_total_packages,
            $normalized_total_orders,
            $normalized_total_truck_slots,
            self::sanitize_nullable_string($notes),
            $metrics,
            $normalized_scans,
            $normalized_scan_count,
            $createdAt,
            $updatedAt,
        );
    }

    public function id(): Identifier
    {
        return $this->id;
    }

    public function reference(): ?string
    {
        return $this->reference;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdByUserId(): ?Identifier
    {
        return $this->createdByUserId;
    }

    public function closedByUserId(): ?Identifier
    {
        return $this->closedByUserId;
    }

    public function closeRequestedAt(): ?DateTimeImmutable
    {
        return $this->closeRequestedAt;
    }

    public function closeRequestedBy(): ?string
    {
        return $this->closeRequestedBy;
    }

    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function exportedAt(): ?DateTimeImmutable
    {
        return $this->exportedAt;
    }

    public function exportFilename(): ?string
    {
        return $this->exportFilename;
    }

    public function totalPackages(): ?int
    {
        return $this->totalPackages;
    }

    public function totalOrders(): ?int
    {
        return $this->totalOrders;
    }

    public function totalTruckSlots(): ?int
    {
        return $this->totalTruckSlots;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function metrics(): DispatchMetrics
    {
        return $this->metrics;
    }

    /**
     * @return array<int,DispatchScan>
     */
    public function scans(): array
    {
        return $this->scans;
    }

    public function scanCount(): int
    {
        return $this->scanCount;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_CLOSED, self::STATUS_EXPORTED], true);
    }

    public function canAddScans(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function recordScan(DispatchScan $scan, ?DateTimeImmutable $recordedAt = null): self
    {
        if ($this->canAddScans() === false) {
            throw new InvalidArgumentException('Cannot add scans to a closed dispatch list.');
        }

        if ($scan->dispatchListId()->equals($this->id) === false) {
            throw new InvalidArgumentException('Scan must reference the same dispatch list.');
        }

        $scansWereLoaded = $this->scanCount === count($this->scans);

        if ($scansWereLoaded) {
            foreach ($this->scans as $existing) {
                if ($existing->id()->equals($scan->id())) {
                    throw new InvalidArgumentException('Scan has already been recorded for this list.');
                }
            }
        }

        $updated_scans = $scansWereLoaded
            ? array_merge($this->scans, [$scan])
            : [];

        $updated_scan_count = $this->scanCount + 1;
        $scanUpdatedAt = $recordedAt ?? $scan->updatedAt();
        $updated_at = $scanUpdatedAt > $this->updatedAt ? $scanUpdatedAt : $this->updatedAt;

        return self::hydrate(
            $this->id,
            $this->reference,
            $this->title,
            $this->status,
            $this->createdByUserId,
            $this->closedByUserId,
            $this->closeRequestedAt,
            $this->closeRequestedBy,
            $this->closedAt,
            $this->exportedAt,
            $this->exportFilename,
            $this->totalPackages,
            $this->totalOrders,
            $this->totalTruckSlots,
            $this->notes,
            $this->metrics,
            $updated_scans,
            $this->createdAt,
            $updated_at,
            $updated_scan_count,
        );
    }

    public function close(Identifier $userId, ?string $exportFilename, DateTimeImmutable $closedAt): self
    {
        if ($this->isClosed()) {
            return $this;
        }

        $normalized_export_filename = $exportFilename !== null
            ? self::sanitize_export_filename($exportFilename)
            : $this->exportFilename;

        return self::hydrate(
            $this->id,
            $this->reference,
            $this->title,
            self::STATUS_CLOSED,
            $this->createdByUserId,
            $userId,
            $this->closeRequestedAt,
            $this->closeRequestedBy,
            $closedAt,
            $this->exportedAt,
            $normalized_export_filename,
            $this->totalPackages,
            $this->totalOrders,
            $this->totalTruckSlots,
            $this->notes,
            $this->metrics,
            $this->scans,
            $this->createdAt,
            $closedAt,
            $this->scanCount,
        );
    }

    public function export(Identifier $userId, string $exportFilename, DateTimeImmutable $exportedAt): self
    {
        if ($this->isClosed() === false) {
            throw new InvalidArgumentException('Dispatch list must be closed before it can be exported.');
        }

        $normalized_filename = self::sanitize_export_filename($exportFilename);

        if ($this->closedAt === null) {
            throw new InvalidArgumentException('Dispatch list cannot be exported without a closed_at timestamp.');
        }

        if ($exportedAt < $this->closedAt) {
            throw new InvalidArgumentException('exported_at must be greater than or equal to closed_at.');
        }

        $closedBy = $this->closedByUserId ?? $userId;

        return self::hydrate(
            $this->id,
            $this->reference,
            $this->title,
            self::STATUS_EXPORTED,
            $this->createdByUserId,
            $closedBy,
            $this->closeRequestedAt,
            $this->closeRequestedBy,
            $this->closedAt,
            $exportedAt,
            $normalized_filename,
            $this->totalPackages,
            $this->totalOrders,
            $this->totalTruckSlots,
            $this->notes,
            $this->metrics,
            $this->scans,
            $this->createdAt,
            $exportedAt,
            $this->scanCount,
        );
    }

    public function withMetrics(DispatchMetrics $metrics, ?DateTimeImmutable $updatedAt = null): self
    {
        $updatedAt ??= new DateTimeImmutable;

        return self::hydrate(
            $this->id,
            $this->reference,
            $this->title,
            $this->status,
            $this->createdByUserId,
            $this->closedByUserId,
            $this->closeRequestedAt,
            $this->closeRequestedBy,
            $this->closedAt,
            $this->exportedAt,
            $this->exportFilename,
            $metrics->totalPackages(),
            $metrics->totalOrders(),
            $metrics->totalTruckSlots(),
            $this->notes,
            $metrics,
            $this->scans,
            $this->createdAt,
            $updatedAt,
            $this->scanCount,
        );
    }

    private static function sanitize_status(string $status): string
    {
        $status = trim($status);

        if (in_array($status, self::VALID_STATUSES, true) === false) {
            throw new InvalidArgumentException(sprintf('Invalid dispatch list status "%s".', $status));
        }

        return $status;
    }

    /**
     * @param  array<int,DispatchScan>  $scans
     * @return array<int,DispatchScan>
     */
    private static function normalize_scans(array $scans): array
    {
        $normalized_scans = array_values($scans);

        foreach ($normalized_scans as $scan) {
            if (($scan instanceof DispatchScan) === false) {
                throw new InvalidArgumentException('Scans must be instances of DispatchScan.');
            }
        }

        return $normalized_scans;
    }

    private static function sanitize_nullable_string(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function sanitize_nullable_non_negative_int(?int $value, string $field_name): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value < 0) {
            throw new InvalidArgumentException(sprintf('%s must be greater than or equal to zero.', $field_name));
        }

        return $value;
    }

    /**
     * @param  array<int,DispatchScan>  $scans
     */
    private static function sanitize_scan_count(?int $scanCount, array $scans): int
    {
        if ($scanCount === null) {
            return count($scans);
        }

        if ($scanCount < 0) {
            throw new InvalidArgumentException('scan_count must be a non-negative integer.');
        }

        if ($scans !== [] && $scanCount !== count($scans)) {
            throw new InvalidArgumentException('scan_count must match provided scans.');
        }

        return $scanCount;
    }

    private static function sanitize_non_empty_string(string $value, string $field_name): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidArgumentException(sprintf('%s must be a non-empty string.', $field_name));
        }

        return $normalized;
    }

    private static function sanitize_export_filename(string $value): string
    {
        $normalized = self::sanitize_non_empty_string($value, 'export_filename');

        if (str_ends_with(strtolower($normalized), '.csv') === false) {
            throw new InvalidArgumentException('export_filename must end with ".csv".');
        }

        return $normalized;
    }

    private static function sanitize_optional_export_filename(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = self::sanitize_nullable_string($value);

        if ($normalized === null) {
            return null;
        }

        return self::sanitize_export_filename($normalized);
    }

    private static function guard_chronology(DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt): void
    {
        if ($updatedAt < $createdAt) {
            throw new InvalidArgumentException('updated_at must be greater than or equal to created_at.');
        }
    }

    private static function guard_temporal_consistency(
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $closeRequestedAt,
        ?DateTimeImmutable $closedAt,
        ?DateTimeImmutable $exportedAt,
    ): void {
        if ($closeRequestedAt !== null && $closeRequestedAt < $createdAt) {
            throw new InvalidArgumentException('close_requested_at must be greater than or equal to created_at.');
        }

        if ($closedAt !== null) {
            if ($closedAt < $createdAt) {
                throw new InvalidArgumentException('closed_at must be greater than or equal to created_at.');
            }

            if ($closeRequestedAt !== null && $closedAt < $closeRequestedAt) {
                throw new InvalidArgumentException('closed_at must be greater than or equal to close_requested_at.');
            }

            if ($closedAt > $updatedAt) {
                throw new InvalidArgumentException('closed_at must be less than or equal to updated_at.');
            }
        }

        if ($exportedAt !== null) {
            if ($exportedAt < $createdAt) {
                throw new InvalidArgumentException('exported_at must be greater than or equal to created_at.');
            }

            if ($closedAt !== null && $exportedAt < $closedAt) {
                throw new InvalidArgumentException('exported_at must be greater than or equal to closed_at.');
            }

            if ($exportedAt > $updatedAt) {
                throw new InvalidArgumentException('exported_at must be less than or equal to updated_at.');
            }
        }
    }

    private static function guard_status_consistency(
        string $status,
        ?Identifier $createdByUserId,
        ?Identifier $closedByUserId,
        ?DateTimeImmutable $closeRequestedAt,
        ?DateTimeImmutable $closedAt,
        ?DateTimeImmutable $exportedAt,
        ?int $totalPackages,
        ?int $totalOrders,
        ?int $totalTruckSlots,
        int $scanCount,
        ?string $exportFilename,
    ): void {
        if ($status === self::STATUS_OPEN) {
            if ($closedAt !== null || $closedByUserId !== null || $exportedAt !== null) {
                throw new InvalidArgumentException('Open dispatch lists cannot be closed or exported.');
            }

            if ($exportFilename !== null) {
                throw new InvalidArgumentException('Open dispatch lists cannot have an export filename.');
            }
        }

        if ($status === self::STATUS_CLOSED) {
            if ($closedAt === null || $closedByUserId === null) {
                throw new InvalidArgumentException('Closed dispatch lists require closed_at and closed_by_user_id.');
            }

            if ($exportedAt !== null && $exportFilename === null) {
                throw new InvalidArgumentException('Export filename is required when exported_at is set.');
            }
        }

        if ($status === self::STATUS_EXPORTED) {
            if ($closedAt === null || $closedByUserId === null || $exportedAt === null || $exportFilename === null) {
                throw new InvalidArgumentException('Exported dispatch lists need closed_at, closed_by_user_id, exported_at, and export_filename.');
            }
        }

        if ($scanCount < 0) {
            throw new InvalidArgumentException('scan_count must be a non-negative integer.');
        }

        $totals = [
            'total_packages' => $totalPackages,
            'total_orders' => $totalOrders,
            'total_truck_slots' => $totalTruckSlots,
        ];

        foreach ($totals as $field => $value) {
            if ($value !== null && $value < 0) {
                throw new InvalidArgumentException(sprintf('%s must be greater than or equal to zero.', $field));
            }
        }

    }

    /**
     * Normalize inconsistent persistence data before applying strict guards.
     *
     * @return array{0:string,1:?Identifier,2:?DateTimeImmutable,3:?DateTimeImmutable,4:?string}
     */
    private static function normalizeStatusForPersistence(
        string $status,
        ?Identifier $closedByUserId,
        ?DateTimeImmutable $closedAt,
        ?DateTimeImmutable $exportedAt,
        ?string $exportFilename,
    ): array {
        if ($status === self::STATUS_EXPORTED) {
            $missingExportMeta = $closedAt === null
                || $closedByUserId === null
                || $exportedAt === null
                || $exportFilename === null;

            if ($missingExportMeta) {
                // Fall back to closed/open states when historic data lacks full export metadata.
                $status = $closedAt !== null && $closedByUserId !== null
                    ? self::STATUS_CLOSED
                    : self::STATUS_OPEN;
                $exportedAt = null;
                $exportFilename = null;
            }
        }

        if ($status === self::STATUS_CLOSED && ($closedAt === null || $closedByUserId === null)) {
            // Legacy datasets sometimes write the status without the full audit trail.
            // Treat them as open so the aggregate can still hydrate.
            $status = self::STATUS_OPEN;
            $closedByUserId = null;
            $closedAt = null;
            $exportedAt = null;
            $exportFilename = null;
        }

        if ($exportedAt !== null && $exportFilename === null) {
            $exportedAt = null;

            if ($status === self::STATUS_EXPORTED) {
                $status = self::STATUS_CLOSED;
            }
        }

        return [$status, $closedByUserId, $closedAt, $exportedAt, $exportFilename];
    }
}
