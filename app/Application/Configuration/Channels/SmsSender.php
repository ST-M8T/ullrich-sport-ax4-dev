<?php

namespace App\Application\Configuration\Channels;

interface SmsSender
{
    /**
     * @param  array<string,mixed>  $context
     */
    public function send(string $recipient, string $message, array $context = []): bool;
}
