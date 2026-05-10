<?php

namespace App\Infrastructure\Integrations\Dhl;

use App\Domain\Integrations\Contracts\DhlPushGateway;
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

final class DhlPushGatewayImpl implements DhlPushGateway
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

    public function createSubscription(array $subscriptionRequest): array
    {
        $response = $this->request('dhl.push.create', fn (): Response => $this->client()->post(
            $this->path('subscription'),
            $subscriptionRequest
        ));

        return $response->json() ?? [];
    }

    public function getSubscription(string $id): array
    {
        $response = $this->request('dhl.push.get', fn (): Response => $this->client()->get(
            $this->path('subscription_with_id', ['id' => $id])
        ));

        return $response->json() ?? [];
    }

    public function activateSubscription(string $id, string $secret): array
    {
        $response = $this->request('dhl.push.activate', fn (): Response => $this->client()->post(
            $this->path('subscription_with_id', ['id' => $id]),
            ['secret' => $secret]
        ));

        return $response->json() ?? [];
    }

    public function removeSubscription(string $id, string $secret): void
    {
        $this->request('dhl.push.remove', fn (): Response => $this->client()->delete(
            $this->path('subscription_with_id', ['id' => $id]),
            ['secret' => $secret]
        ));
    }

    public function listSubscriptions(): array
    {
        $response = $this->request('dhl.push.list', fn (): Response => $this->client()->get(
            $this->path('subscriptions')
        ));

        return $response->json() ?? [];
    }

    private function client(): PendingRequest
    {
        $request = $this->http
            ->withOptions([
                'verify' => Arr::get($this->options, 'verify', true),
            ])
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                (string) Arr::get($this->options, 'api_key_header', 'DHL-API-Key') => $this->apiKey,
            ])
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

    /**
     * @param  callable():Response  $callback
     */
    private function request(string $operation, callable $callback): Response
    {
        $startedAt = microtime(true);

        try {
            $response = $this->circuitBreaker->call(function () use ($callback): Response {
                $response = $callback();
                $response->throw();

                return $response;
            });
        } catch (CircuitBreakerOpenException $exception) {
            $this->logger->warning('[DHL Push] Circuit breaker open', [
                'operation' => $operation,
                'service' => 'dhl.push',
                'retry_after' => $exception->retryAfter(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $durationMs = (microtime(true) - $startedAt) * 1000;
            $status = $exception instanceof RequestException && $exception->response
                ? $exception->response->status()
                : null;

            $this->logger->error('[DHL Push] Request failed', [
                'operation' => $operation,
                'service' => 'dhl.push',
                'status' => $status,
                'duration_ms' => $durationMs,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $durationMs = (microtime(true) - $startedAt) * 1000;

        $this->logger->info('[DHL Push] Request succeeded', [
            'operation' => $operation,
            'service' => 'dhl.push',
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
        foreach ($replacements as $search => $value) {
            $path = str_replace('{'.$search.'}', urlencode((string) $value), $path);
        }

        return $path;
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
}
