<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class DomainEventAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string,mixed>  $context
     */
    public function __construct(
        private readonly string $subjectLine,
        private readonly string $messageLine,
        private readonly array $context,
        private readonly string $severity,
    ) {}

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view('mail.domain-event-alert')
            ->with([
                'messageLine' => $this->messageLine,
                'context' => $this->context,
                'severity' => strtoupper($this->severity),
            ])
            ->text('mail.domain-event-alert_plain');
    }
}
