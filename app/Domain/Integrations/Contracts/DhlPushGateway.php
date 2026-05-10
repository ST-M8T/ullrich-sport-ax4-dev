<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Contracts;

interface DhlPushGateway
{
    /**
     * @param  array<string,mixed>  $subscriptionRequest
     * @return array<string,mixed>
     */
    public function createSubscription(array $subscriptionRequest): array;

    /**
     * @return array<string,mixed>
     */
    public function getSubscription(string $id): array;

    /**
     * @return array<string,mixed>
     */
    public function activateSubscription(string $id, string $secret): array;

    public function removeSubscription(string $id, string $secret): void;

    /**
     * @return array<string,mixed>
     */
    public function listSubscriptions(): array;
}
