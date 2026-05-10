<?php

namespace App\Application\Monitoring;

use App\Application\Monitoring\Metrics\MetricsRecorder;
use App\Domain\Monitoring\DomainEventRecord;
use App\Mail\DomainEventAlertMail;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

final class DomainEventAlertDispatcher
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly MetricsRecorder $metrics,
    ) {}

    public function dispatch(DomainEventRecord $record): bool
    {
        $config = $this->config->get('monitoring.alerts', []);
        if (! ($config['enabled'] ?? false)) {
            return false;
        }

        $rule = $this->matchRule($record->eventName(), $config['rules'] ?? []);
        if ($rule === null) {
            return false;
        }

        $severity = strtolower((string) ($rule['severity'] ?? $record->metadata()['severity'] ?? 'warning'));
        $channels = $this->resolveChannels($rule['channels'] ?? null, $config['default_channels'] ?? []);

        if ($channels === []) {
            return false;
        }

        $context = [
            'event' => $record->eventName(),
            'aggregate_type' => $record->aggregateType(),
            'aggregate_id' => $record->aggregateId(),
            'payload' => $record->payload(),
            'metadata' => $record->metadata(),
            'occurred_at' => $record->occurredAt()->format(DATE_ATOM),
        ];

        $message = sprintf(
            '%s event "%s" for %s:%s',
            ucfirst($severity),
            $record->eventName(),
            $record->aggregateType(),
            $record->aggregateId(),
        );

        $subjectPrefix = (string) ($config['mail']['subject_prefix'] ?? '[Alert]');
        $subject = trim(sprintf('%s %s', $subjectPrefix, strtoupper($severity)));

        $sent = false;

        if (in_array('mail', $channels, true)
            && $this->sendMail($subject, $message, $context, $severity, $config['mail'] ?? [])) {
            $sent = true;
        }

        if (in_array('slack', $channels, true)
            && $this->sendSlack($message, $context, $severity, $config['slack'] ?? [])) {
            $sent = true;
        }

        if ($sent) {
            $this->metrics->increment('alerts.sent', 1, [
                'severity' => $severity,
                'event' => $record->eventName(),
            ]);
        }

        return $sent;
    }

    /**
     * @param  array<string,mixed>  $rules
     * @return array<string, mixed>|null
     */
    private function matchRule(string $eventName, array $rules): ?array
    {
        foreach ($rules as $pattern => $rule) {
            $pattern = is_string($pattern)
                ? $pattern
                : (is_array($rule) && isset($rule['pattern']) ? (string) $rule['pattern'] : null);

            if ($pattern === null) {
                continue;
            }

            if (Str::is($pattern, $eventName)) {
                return is_array($rule) ? $rule : [];
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function resolveChannels(mixed $ruleChannels, mixed $default): array
    {
        $channels = $this->normalizeChannels($ruleChannels);
        if ($channels === []) {
            $channels = $this->normalizeChannels($default);
        }

        return array_values(array_unique($channels));
    }

    /**
     * @param  array<string,mixed>  $context
     * @param  array<string,mixed>  $mailConfig
     */
    private function sendMail(string $subject, string $message, array $context, string $severity, array $mailConfig): bool
    {
        if (! ($mailConfig['enabled'] ?? false)) {
            return false;
        }

        $recipients = $this->normalizeRecipients($mailConfig['recipients'] ?? []);
        if ($recipients === []) {
            return false;
        }

        $sent = false;
        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient)->send(new DomainEventAlertMail(
                    $subject,
                    $message,
                    $context,
                    $severity
                ));
                $sent = true;
            } catch (Throwable $exception) {
                Log::warning('Failed to send domain event alert mail', [
                    'recipient' => $recipient,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * @param  array<string,mixed>  $context
     * @param  array<string,mixed>  $slackConfig
     */
    private function sendSlack(string $message, array $context, string $severity, array $slackConfig): bool
    {
        if (! ($slackConfig['enabled'] ?? false)) {
            return false;
        }

        $webhook = $slackConfig['webhook'] ?? null;
        if (! $webhook) {
            return false;
        }

        $body = [
            'text' => sprintf('[%s] %s', strtoupper($severity), $message),
        ];

        if (! empty($slackConfig['channel'])) {
            $body['channel'] = $slackConfig['channel'];
        }

        $contextJson = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (is_string($contextJson)) {
            $body['blocks'] = [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => sprintf('*[%s]* %s', strtoupper($severity), $message),
                    ],
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => sprintf('```%s```', $contextJson),
                    ],
                ],
            ];
        }

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::post($webhook, $body);
            if ($response->successful()) {
                return true;
            }

            Log::warning('Failed to send domain event alert to Slack', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Failed to call Slack webhook for domain event alert', [
                'error' => $exception->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * @return array<int,string>
     */
    private function normalizeChannels(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_iterable($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $normalized[] = strtolower($item);
            }
        }

        return $normalized;
    }

    /**
     * @return array<int,string>
     */
    private function normalizeRecipients(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_iterable($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }
}
