<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Contracts;

interface PlentyOrderGateway
{
    /**
     * @param  array<int|string>  $statusCodes
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    public function fetchOrdersByStatus(array $statusCodes, array $filters = []): array;

    /**
     * @return array<string,mixed>|null
     */
    public function fetchOrder(int $orderId): ?array;

    public function updateOrderStatus(int $orderId, string $statusCode): void;

    /**
     * @return array{status:int,duration_ms:float,body:mixed}
     */
    public function ping(): array;
}
