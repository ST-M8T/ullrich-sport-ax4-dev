<?php

namespace App\Infrastructure\Integrations\Plenty;

use App\Domain\Integrations\Contracts\PlentyOrderGateway;
use App\Support\CircuitBreaker;
use App\Support\Exceptions\CircuitBreakerOpenException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;
use Throwable;

final class PlentyRestGateway implements PlentyOrderGateway
{
    /**
     * @param  array<string,mixed>  $options
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly LoggerInterface $logger,
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly array $options = []
    ) {}

    public function fetchOrdersByStatus(array $statusCodes, array $filters = []): array
    {
        $statusCodes = array_values($statusCodes);

        $response = $this->request('plenty.fetch_orders_by_status', function () use ($statusCodes, $filters): Response {
            return $this->client()->post('/rest/orders/search', [
                'status' => $statusCodes,
                'filters' => $filters,
            ]);
        });

        return $response->json() ?? [];
    }

    public function fetchOrder(int $orderId): ?array
    {
        $response = $this->request('plenty.fetch_order', function () use ($orderId): Response {
            return $this->client()->get('/rest/orders/'.$orderId);
        });

        if ($response->status() === 404) {
            return null;
        }

        return $response->json();
    }

    public function updateOrderStatus(int $orderId, string $statusCode): void
    {
        $this->request('plenty.update_order_status', function () use ($orderId, $statusCode): Response {
            return $this->client()->put('/rest/orders/'.$orderId, [
                'statusId' => $statusCode,
            ]);
        });
    }

    public function ping(): array
    {
        $method = strtoupper((string) Arr::get($this->options, 'ping.method', 'GET'));
        $path = (string) Arr::get($this->options, 'ping.path', '/');
        $duration = 0.0;

        $response = $this->request('plenty.ping', function () use ($method, $path): Response {
            return $this->client()->send($method, $path);
        }, $duration);

        return [
            'status' => $response->status(),
            'duration_ms' => $duration,
            'body' => $this->safeBody($response),
        ];
    }

    private function client(): PendingRequest
    {
        $request = $this->http
            ->withOptions([
                'verify' => Arr::get($this->options, 'verify', true),
            ])
            ->baseUrl($this->baseUrl)
            ->withBasicAuth($this->username, $this->password)
            ->acceptJson()
            ->asJson()
            ->timeout((float) Arr::get($this->options, 'timeout', 10.0))
            ->connectTimeout((float) Arr::get($this->options, 'connect_timeout', 5.0));

        $retryTimes = (int) Arr::get($this->options, 'retry.times', 0);
        if ($retryTimes > 0) {
            $sleep = (int) Arr::get($this->options, 'retry.sleep', 200);
            $request = $request->retry($retryTimes, $sleep, function ($exception): bool {
                return $this->shouldRetry($exception);
            });
        }

        return $request;
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
            $this->logger->warning('[Plenty] Circuit breaker open', [
                'operation' => $operation,
                'service' => 'plenty',
                'retry_after' => $exception->retryAfter(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $durationMs = (microtime(true) - $startedAt) * 1000;
            $status = $exception instanceof RequestException && $exception->response
                ? $exception->response->status()
                : null;

            $this->logger->error('[Plenty] Request failed', [
                'operation' => $operation,
                'service' => 'plenty',
                'status' => $status,
                'duration_ms' => $durationMs,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $durationMs = (microtime(true) - $startedAt) * 1000;

        $this->logger->info('[Plenty] Request succeeded', [
            'operation' => $operation,
            'service' => 'plenty',
            'status' => $response->status(),
            'duration_ms' => $durationMs,
        ]);

        return $response;
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
