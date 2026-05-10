<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Services;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlPriceQuoteResponseDto;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentSenderProfileRepository;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Integrations\Contracts\DhlFreightGateway;
use App\Domain\Shared\ValueObjects\Identifier;
use Psr\Log\LoggerInterface;
use Throwable;

final class DhlPriceQuoteService
{
    public function __construct(
        private readonly DhlFreightGateway $gateway,
        private readonly ShipmentOrderRepository $orderRepository,
        private readonly FulfillmentSenderProfileRepository $senderRepository,
        private readonly DhlPayloadMapper $mapper,
        private readonly LoggerInterface $logger,
    ) {
        // Dependencies injected by the container.
    }

    /**
     * @param  array<string,mixed>  $options
     */
    public function getPriceQuote(
        Identifier $orderId,
        ?string $productId = null,
        array $options = [],
    ): DhlPriceQuoteResult {
        $order = $this->orderRepository->getById($orderId);
        if ($order === null) {
            throw new \RuntimeException('Shipment order not found.');
        }

        if ($order->senderProfileId() === null) {
            throw new \RuntimeException('Shipment order has no sender profile.');
        }

        $senderProfile = $this->senderRepository->getById($order->senderProfileId());
        if ($senderProfile === null) {
            throw new \RuntimeException('Sender profile not found.');
        }

        try {
            $payload = $this->mapper->mapToPriceQuoteRequest($order, $senderProfile, $productId, $options);

            $this->logger->info('DHL price quote request', [
                'order_id' => $order->id()->toInt(),
                'product_id' => $productId,
            ]);

            $response = $this->gateway->getPriceQuote($payload);
            $responseDto = new DhlPriceQuoteResponseDto($response);

            if ($responseDto->isSuccess() === false) {
                $errorMessage = $responseDto->errorMessage() ?? 'Unknown error';
                $this->logger->error('DHL price quote failed', [
                    'order_id' => $order->id()->toInt(),
                    'error' => $errorMessage,
                ]);

                return new DhlPriceQuoteResult(
                    success: false,
                    totalPrice: null,
                    currency: null,
                    breakdown: [],
                    error: $errorMessage,
                );
            }

            $this->logger->info('DHL price quote successful', [
                'order_id' => $order->id()->toInt(),
                'price' => $responseDto->totalPrice(),
                'currency' => $responseDto->currency(),
            ]);

            return new DhlPriceQuoteResult(
                success: true,
                totalPrice: $responseDto->totalPrice(),
                currency: $responseDto->currency(),
                breakdown: $responseDto->breakdown(),
                error: null,
            );
        } catch (Throwable $exception) {
            $this->logger->error('DHL price quote exception', [
                'order_id' => $orderId->toInt(),
                'exception' => $exception->getMessage(),
            ]);

            throw new \RuntimeException('DHL price quote failed: '.$exception->getMessage(), 0, $exception);
        }
    }
}

final class DhlPriceQuoteResult
{
    /**
     * @param  array<string,mixed>  $breakdown
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?float $totalPrice,
        public readonly ?string $currency,
        public readonly array $breakdown,
        public readonly ?string $error,
    ) {
        // Lightweight response DTO.
    }
}
