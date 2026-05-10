<?php

namespace App\Application\Configuration\Channels;

use App\Application\Configuration\SystemSettingService;
use App\Domain\Configuration\Contracts\MailTemplateRepository;
use App\Domain\Configuration\MailTemplate;
use App\Domain\Configuration\NotificationMessage;
use Illuminate\Support\Facades\Mail;

final class MailNotificationChannel implements NotificationChannel
{
    public function __construct(
        private readonly MailTemplateRepository $templates,
        private readonly SystemSettingService $settings,
    ) {}

    public function key(): string
    {
        return 'mail';
    }

    public function isEnabled(): bool
    {
        return $this->toBool($this->settings->get('notifications.mail.enabled', '1'));
    }

    public function send(NotificationMessage $message): bool
    {
        $payload = $message->payload();
        $templateKey = $payload['template'] ?? null;
        $recipient = $payload['to'] ?? null;

        if (! $templateKey || ! $recipient) {
            return false;
        }

        $template = $this->templates->getByKey($templateKey);
        if (! $template instanceof MailTemplate || ! $template->isActive()) {
            return false;
        }

        $fromAddress = $this->settings->get('mail_from_email') ?? config('mail.from.address');
        $fromName = $this->settings->get('mail_from_name') ?? config('mail.from.name');

        Mail::send([], [], function ($mail) use ($template, $recipient, $fromAddress, $fromName) {
            $mail->to($recipient)
                ->from($fromAddress, $fromName)
                ->subject($template->subject())
                ->setBody(
                    $template->bodyHtml() ?? $template->bodyText() ?? '',
                    $template->bodyHtml() ? 'text/html' : 'text/plain'
                );
        });

        return true;
    }

    private function toBool(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}
