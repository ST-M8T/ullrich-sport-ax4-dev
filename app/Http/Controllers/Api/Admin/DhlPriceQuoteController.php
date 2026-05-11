<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Application\Fulfillment\Integrations\Dhl\Services\DhlPriceQuoteService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Http\Controllers\Api\Admin\Concerns\InteractsWithJsonApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DhlPriceQuoteController
{
    use InteractsWithJsonApiResponses;

    public function __construct(
        private readonly DhlPriceQuoteService $priceQuoteService,
    ) {}

    /**
     * GET /api/admin/dhl/price-quote
     *
     * Returns a price quote for a given order, product, and optional additional services.
     */
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'min:1'],
            'product_id' => ['nullable', 'string', 'max:64'],
            'additional_services' => ['nullable', 'array'],
            'additional_services.*' => ['string', 'max:64'],
        ]);

        try {
            $orderId = Identifier::fromInt((int) $validated['order_id']);
            $options = [
                'additional_services' => $validated['additional_services'] ?? [],
            ];

            $result = ($this->priceQuoteService)->getPriceQuote(
                $orderId,
                $validated['product_id'] ?? null,
                $options
            );

            if ($result->success === false) {
                return $this->jsonApiError(422, 'Unprocessable Entity', $result->error ?? 'Price quote failed');
            }

            return $this->jsonApiResponse([
                'data' => [
                    'type' => 'dhl-price-quote',
                    'id' => null,
                    'attributes' => [
                        'total_price' => $result->totalPrice,
                        'currency' => $result->currency,
                        'breakdown' => $result->breakdown,
                    ],
                ],
            ]);
        } catch (\Throwable $exception) {
            return $this->jsonApiError(500, 'Internal Server Error', $exception->getMessage());
        }
    }
}