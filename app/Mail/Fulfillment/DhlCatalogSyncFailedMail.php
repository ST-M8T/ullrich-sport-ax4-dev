<?php

declare(strict_types=1);

namespace App\Mail\Fulfillment;

use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Alert mail sent on the FIRST sync failure of a streak (Engineering-Handbuch §24).
 *
 * The class itself is purely declarative — idempotency is enforced by the
 * caller via `DhlCatalogSyncStatusRepository::markAlertMailSent()`.
 *
 * No tokens / credentials may flow into the body — the token scrubber tap
 * runs on the dedicated `dhl-catalog` log channel, but the mail body is
 * built from already-scrubbed status fields.
 */
final class DhlCatalogSyncFailedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string,mixed>  $resultSummary
     */
    public function __construct(
        public readonly string $errorMessage,
        public readonly ?DateTimeImmutable $lastSuccessAt,
        public readonly int $consecutiveFailures,
        public readonly ?string $routingFilter,
        public readonly array $resultSummary,
    ) {}

    public function build(): self
    {
        return $this->subject('[AX4] DHL-Katalog-Sync fehlgeschlagen')
            ->view('mail.dhl-catalog-sync-failed')
            ->text('mail.dhl-catalog-sync-failed_plain')
            ->with([
                'errorMessage' => $this->errorMessage,
                'lastSuccessAt' => $this->lastSuccessAt?->format(DATE_ATOM) ?? 'nie',
                'consecutiveFailures' => $this->consecutiveFailures,
                'routingFilter' => $this->routingFilter ?? '(alle Routings)',
                'resultSummary' => $this->resultSummary,
            ]);
    }
}
