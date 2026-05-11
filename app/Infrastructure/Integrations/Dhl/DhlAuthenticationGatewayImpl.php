<?php

namespace App\Infrastructure\Integrations\Dhl;

use App\Domain\Integrations\Contracts\DhlAuthenticationGateway;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

final class DhlAuthenticationGatewayImpl implements DhlAuthenticationGateway
{
    private const CACHE_KEY = 'dhl.auth.token';

    /**
     * @param  array<string,mixed>  $options
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheRepository $cache,
        private readonly LoggerInterface $logger,
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly array $options = []
    ) {}

    public function getToken(string $responseType = 'access_token'): array
    {
        $this->assertConfiguration();

        $cached = $this->cache->get($this->cacheKey($responseType));
        if (is_array($cached) && isset($cached['access_token'])) {
            return $cached;
        }

        $response = $this->request(function (): Response {
            return $this->client()->post($this->path(), [
                'grant_type' => 'client_credentials',
            ]);
        });

        $payload = $response->json() ?? [];

        if (! isset($payload['access_token'])) {
            return $payload;
        }

        $ttl = $this->resolveTtl((int) ($payload['expires_in'] ?? 0));
        $this->cache->put($this->cacheKey($responseType), $payload, $ttl);

        return $payload;
    }

    private function assertConfiguration(): void
    {
        if (trim($this->baseUrl) === '') {
            throw new InvalidArgumentException('DHL Auth base URL is not configured.');
        }

        if (trim($this->path()) === '') {
            throw new InvalidArgumentException('DHL Auth token path is not configured.');
        }

        if (trim($this->username) === '') {
            throw new InvalidArgumentException('DHL Auth client ID is not configured.');
        }

        if (trim($this->password) === '') {
            throw new InvalidArgumentException('DHL Auth client secret is not configured.');
        }
    }

    private function request(callable $callback): Response
    {
        try {
            $response = $callback();
            $response->throw();

            return $response;
        } catch (Throwable $exception) {
            $this->logger->error('[DHL Auth] Request failed', [
                'service' => 'dhl.auth',
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function client(): PendingRequest
    {
        $request = $this->http
            ->withOptions([
                'verify' => Arr::get($this->options, 'verify', true),
            ])
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->asForm()
            ->withBasicAuth($this->username, $this->password)
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

    private function path(): string
    {
        return (string) Arr::get($this->options, 'path', '/auth/v1/token');
    }

    private function resolveTtl(int $expiresIn): int
    {
        $explicitTtl = (int) Arr::get($this->options, 'token_cache_ttl', 0);
        if ($explicitTtl > 0) {
            return $explicitTtl;
        }

        if ($expiresIn > 60) {
            return $expiresIn - 30;
        }

        return 90;
    }

    private function cacheKey(string $responseType): string
    {
        return sprintf('%s:%s', self::CACHE_KEY, $responseType);
    }
}
