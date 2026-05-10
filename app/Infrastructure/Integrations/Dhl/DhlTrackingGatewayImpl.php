<?php

namespace App\Infrastructure\Integrations\Dhl;

use App\Domain\Integrations\Contracts\DhlTrackingGateway;
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

final class DhlTrackingGatewayImpl implements DhlTrackingGateway
{
    /**
     * @param  array<string,mixed>  $options
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly LoggerInterface $logger,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly array $options = []
    ) {}

    public function fetchTrackingEvents(string $trackingNumber): array
    {
        $encoded = urlencode($trackingNumber);

        $response = $this->request('dhl.fetch_tracking_events', function () use ($encoded): Response {
            return $this->client()->get('/tracking/'.$encoded);
        });

        return $response->json() ?? [];
    }

    public function ping(): array
    {
        $method = strtoupper((string) Arr::get($this->options, 'ping.method', 'GET'));
        $path = (string) Arr::get($this->options, 'ping.path', '/');
        $duration = 0.0;

        $response = $this->request('dhl.ping', function () use ($method, $path): Response {
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
            ->withToken($this->apiKey)
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
            $this->logger->warning('[DHL] Circuit breaker open', [
                'operation' => $operation,
                'service' => 'dhl',
                'retry_after' => $exception->retryAfter(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $durationMs = (microtime(true) - $startedAt) * 1000;
            $status = $exception instanceof RequestException && $exception->response
                ? $exception->response->status()
                : null;

            $this->logger->error('[DHL] Request failed', [
                'operation' => $operation,
                'service' => 'dhl',
                'status' => $status,
                'duration_ms' => $durationMs,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $durationMs = (microtime(true) - $startedAt) * 1000;

        $this->logger->info('[DHL] Request succeeded', [
            'operation' => $operation,
            'service' => 'dhl',
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
