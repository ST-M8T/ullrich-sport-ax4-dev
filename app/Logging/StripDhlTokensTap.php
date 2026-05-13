<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

/**
 * Monolog tap for the `dhl-catalog` channel.
 *
 * Engineering-Handbuch §30 (Logging) — no tokens, no credentials in logs.
 * Scrubs anything that *could* be an authentication value from both the log
 * message and the structured context payload.
 */
final class StripDhlTokensTap
{
    /**
     * Field names whose value gets fully redacted regardless of content.
     *
     * @var list<string>
     */
    private const REDACTED_KEYS = [
        'authorization',
        'access_token',
        'refresh_token',
        'token',
        'bearer',
        'api_key',
        'api_secret',
        'dhl_api_token',
        'password',
        'secret',
    ];

    private const REDACTION = '***REDACTED***';

    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(function (LogRecord $record): LogRecord {
                $context = $this->scrub($record->context);
                $extra = $this->scrub($record->extra);
                $message = $this->scrubMessage($record->message);

                return $record->with(message: $message, context: $context, extra: $extra);
            });
        }
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function scrub(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $data[$key] = self::REDACTION;

                continue;
            }
            if (is_array($value)) {
                $data[$key] = $this->scrub($value);

                continue;
            }
            if (is_string($value)) {
                $data[$key] = $this->scrubMessage($value);
            }
        }

        return $data;
    }

    private function isSensitiveKey(string $key): bool
    {
        $needle = strtolower($key);
        foreach (self::REDACTED_KEYS as $candidate) {
            if (str_contains($needle, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function scrubMessage(string $message): string
    {
        // Bearer tokens
        $message = (string) preg_replace(
            '/(Bearer\s+)([A-Za-z0-9\-_\.=]{8,})/i',
            '$1' . self::REDACTION,
            $message,
        );

        // Generic "token=…"
        $message = (string) preg_replace(
            '/((?:access_token|refresh_token|token|api_key|api_secret)["\']?\s*[:=]\s*["\']?)([A-Za-z0-9\-_\.=]{8,})/i',
            '$1' . self::REDACTION,
            $message,
        );

        return $message;
    }
}
