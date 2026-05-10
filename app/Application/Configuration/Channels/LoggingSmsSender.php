<?php

namespace App\Application\Configuration\Channels;

use Illuminate\Support\Facades\Log;

final class LoggingSmsSender implements SmsSender
{
    public function send(string $recipient, string $message, array $context = []): bool
    {
        Log::info('sms.dispatch', [
            'recipient' => $recipient,
            'message' => $message,
            'context' => $context,
        ]);

        return true;
    }
}
