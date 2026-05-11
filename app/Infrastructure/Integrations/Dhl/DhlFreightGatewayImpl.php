<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\Dhl;

use App\Domain\Integrations\Contracts\DhlAuthenticationGateway;
use App\Domain\Integrations\Contracts\DhlFreightGateway;
use App\Support\CircuitBreaker;
use App\Support\Exceptions\CircuitBreakerOpenException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

final class DhlFreightGatewayImpl implements DhlFreightGateway
{
    /**
     * @param  array<string,mixed>  $options
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly LoggerInterface $logger,
        private readonly DhlAuthenticationGateway $authGateway,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $apiSecret = '',
        private readonly array $options = []
    ) {
        // Gateway simply stores configuration for later requests.
    }

    public function getTimetable(array $payload): array
    {
        $response = $this->request('dhl.freight.timetable', fn (): Response => $this->client()->post(
            $this->path('timetable'),
            $payload
        ));

        return $response->json() ?? [];
    }

    public function listProducts(array $filters = []): array
    {
        $response = $this->request('dhl.freight.products', function () use ($filters): Response {
            return $this->client()->get($this->path('products'), $filters);
        });

        return $response->json() ?? [];
    }

    public function listAdditionalServices(string $productId, array $filters = []): array
    {
        $response = $this->request('dhl.freight.additional_services', function () use ($productId, $filters): Response {
            return $this->client()->get($this->path('additional_services', ['productId' => $productId]), $filters);
        });

        return $response->json() ?? [];
    }

    public function validateAdditionalServices(string $productId, array $services, array $filters = []): array
    {
        $response = $this->request('dhl.freight.additional_services_validation', function () use ($productId, $services, $filters): Response {
            return $this->client()->post(
                $this->path('additional_services_validation', ['productId' => $productId]),
                ['services' => $services] + $filters
            );
        });

        return $response->json() ?? [];
    }

    public function bookShipment(array $payload): array
    {
        $response = $this->request('dhl.freight.shipment_booking', function () use ($payload): Response {
            return $this->client()->post($this->path('shipments'), $payload);
        });

        return $response->json() ?? [];
    }

    public function getPriceQuote(array $quoteModel): array
    {
        $response = $this->request('dhl.freight.price_quote', fn (): Response => $this->client()->post(
            $this->path('price_quote'),
            $quoteModel
        ));

        return $response->json() ?? [];
    }

    public function printLabel(string $shipmentId, array $options = []): array
    {
        $response = $this->request('dhl.freight.print', function () use ($shipmentId, $options): Response {
            return $this->client()->post(
                $this->path('label', ['shipmentId' => $shipmentId]),
                ['shipmentId' => $shipmentId] + $options
            );
        });

        return $response->json() ?? [];
    }

    public function printDocuments(array $shipment, array $options = []): array
    {
        $response = $this->request('dhl.freight.print_documents', fn (): Response => $this->client()->post(
            $this->path('print_documents'),
            $shipment + $options
        ));

        return $response->json() ?? [];
    }

    public function printMultipleDocuments(array $shipments, array $options = []): array
    {
        $response = $this->request('dhl.freight.print_multiple_documents', fn (): Response => $this->client()->post(
            $this->path('print_multiple_documents'),
            ['shipments' => $shipments] + $options
        ));

        return $response->json() ?? [];
    }

    public function ping(): array
    {
        $method = strtoupper((string) Arr::get($this->options, 'ping.method', 'GET'));
        $path = (string) Arr::get($this->options, 'ping.path', '/');
        $duration = 0.0;

        $response = $this->request('dhl.freight.ping', function () use ($method, $path): Response {
            return $this->client()->send($method, $path);
        }, $duration);

        return [
            'status' => $response->status(),
            'duration_ms' => $duration,
            'body' => $this->safeBody($response),
        ];
    }

    public function cancelShipment(string $shipmentId, string $reason): array
    {
        // DHL Freight API has no standard cancellation endpoint.
        // Booking is a one-way POST to /sendtransportinstruction with no built-in undo.
        // We perform local cancellation marking only.
        // If DHL introduces a proper cancel API in the future, this method should call it.
        $this->logger->info('[DHL Freight] Cancellation requested (local marking only)', [
            'shipment_id' => $shipmentId,
            'reason' => $reason,
            'service' => 'dhl.freight',
        ]);

        return [
            'success' => true,
            'cancelled_at' => (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            'confirmation_number' => null,
            'error' => null,
        ];
    }

    private function client(): PendingRequest
    {
        $this->assertConfiguration();

        $request = $this->http
            ->withOptions([
                'verify' => Arr::get($this->options, 'verify', true),
            ])
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout((float) Arr::get($this->options, 'timeout', 10.0))
            ->connectTimeout((float) Arr::get($this->options, 'connect_timeout', 5.0));

        $request = $this->authenticate($request);

        $retryTimes = (int) Arr::get($this->options, 'retry.times', 0);
        if ($retryTimes > 0) {
            $sleep = (int) Arr::get($this->options, 'retry.sleep', 200);
            $request = $request->retry($retryTimes, $sleep, function ($exception): bool {
                return $this->shouldRetry($exception);
            });
        }

        return $request;
    }

    private function authenticate(PendingRequest $request): PendingRequest
    {
        $auth = (string) Arr::get($this->options, 'auth', 'bearer');

        if ($auth === 'bearer') {
            $token = $this->resolveToken();

            return $request->withToken($token);
        }

        if ($auth === 'basic' && $this->apiSecret !== '') {
            return $request->withBasicAuth($this->apiKey, $this->apiSecret);
        }

        if ($auth === 'header') {
            $header = (string) Arr::get($this->options, 'api_key_header', 'DHL-API-Key');
            $request = $request->withHeaders([$header => $this->apiKey]);

            $secretHeader = Arr::get($this->options, 'api_secret_header');
            if (is_string($secretHeader) && $secretHeader !== '' && $this->apiSecret !== '') {
                $request = $request->withHeaders([$secretHeader => $this->apiSecret]);
            }

            return $request;
        }

        return $request->withToken($this->apiKey);
    }

    private function assertConfiguration(): void
    {
        if (trim($this->baseUrl) === '') {
            throw new InvalidArgumentException('DHL Freight base URL is not configured.');
        }

        $auth = (string) Arr::get($this->options, 'auth', 'bearer');
        if (! in_array($auth, ['bearer', 'basic', 'header'], true)) {
            throw new InvalidArgumentException('DHL Freight auth mode is invalid.');
        }

        if ($auth === 'basic' && (trim($this->apiKey) === '' || trim($this->apiSecret) === '')) {
            throw new InvalidArgumentException('DHL Freight basic auth requires API key and API secret.');
        }

        if ($auth === 'header' && trim($this->apiKey) === '') {
            throw new InvalidArgumentException('DHL Freight header auth requires an API key.');
        }
    }

    private function shouldRetry(mixed $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            $status = $exception->response->status();
            if ($status === null) {
                return true;
            }

            return $status >= 500 || in_array($status, [408, 409, 425, 429], true);
        }

        return false;
    }

    /**
     * @param  callable():Response  $callback
     */
    private function request(string $operation, callable $callback, float &$durationMs = 0.0): Response
    {
        $startedAt = microtime(true);

        try {
            $response = $this->circuitBreaker->call(function () use ($callback): Response {
                $response = $callback();
                $response->throw();

                return $response;
            });
        } catch (CircuitBreakerOpenException $exception) {
            $this->logger->warning('[DHL Freight] Circuit breaker open', [
                'operation' => $operation,
                'service' => 'dhl.freight',
                'retry_after' => $exception->retryAfter(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $durationMs = (microtime(true) - $startedAt) * 1000;
            $status = $exception instanceof RequestException && $exception->response
                ? $exception->response->status()
                : null;

            $this->logger->error('[DHL Freight] Request failed', [
                'operation' => $operation,
                'service' => 'dhl.freight',
                'status' => $status,
                'duration_ms' => $durationMs,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $durationMs = (microtime(true) - $startedAt) * 1000;

        $this->logger->info('[DHL Freight] Request succeeded', [
            'operation' => $operation,
            'service' => 'dhl.freight',
            'status' => $response->status(),
            'duration_ms' => $durationMs,
        ]);

        return $response;
    }

    /**
     * @param  array<string, scalar>  $replacements
     */
    private function path(string $key, array $replacements = []): string
    {
        $path = (string) Arr::get($this->options, "paths.{$key}", '');
        if ($path === '') {
            throw new InvalidArgumentException("Missing path configuration for [{$key}]");
        }

        foreach ($replacements as $search => $value) {
            $path = str_replace('{'.$search.'}', urlencode((string) $value), $path);
        }

        return $path;
    }

    private function resolveToken(): string
    {
        $token = $this->authGateway->getToken()['access_token'] ?? null;

        if (is_string($token) && trim($token) !== '') {
            return $token;
        }

        throw new InvalidArgumentException('DHL Freight bearer auth requires a DHL Auth token response with access_token.');
    }

    private function safeBody(Response $response): mixed
    {
        try {
            return $response->json();
        } catch (Throwable) {
            return $response->body();
        }
    }
}
