<?php

namespace App\Support;

use App\Support\Exceptions\CircuitBreakerOpenException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Throwable;

final class CircuitBreaker
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly string $service,
        private readonly int $failureThreshold = 5,
        private readonly int $cooldownSeconds = 60
    ) {}

    /**
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     *
     * @throws CircuitBreakerOpenException
     */
    public function call(callable $callback)
    {
        if ($this->isOpen()) {
            $state = $this->state();

            throw new CircuitBreakerOpenException(
                $this->service,
                (int) ($state['opened_at'] ?? time())
            );
        }

        try {
            $result = $callback();
        } catch (Throwable $exception) {
            $this->recordFailure();
            throw $exception;
        }

        $this->reset();

        return $result;
    }

    private function isOpen(): bool
    {
        $state = $this->state();

        if (! isset($state['opened_at'])) {
            return false;
        }

        $openedAt = (int) $state['opened_at'];

        if ($openedAt <= 0) {
            return false;
        }

        if ($openedAt > time()) {
            return true;
        }

        $this->reset();

        return false;
    }

    private function recordFailure(): void
    {
        $state = $this->state();
        $failures = (int) ($state['failures'] ?? 0) + 1;
        $openedAt = null;

        if ($failures >= $this->failureThreshold) {
            $openedAt = time() + max(1, $this->cooldownSeconds);
        }

        $this->store([
            'failures' => $failures,
            'opened_at' => $openedAt,
        ]);
    }

    private function reset(): void
    {
        $this->cache->forget($this->key());
    }

    /**
     * @return array{failures:int,opened_at:?int}
     */
    private function state(): array
    {
        $state = $this->cache->get($this->key(), [
            'failures' => 0,
            'opened_at' => null,
        ]);

        if (! is_array($state)) {
            return [
                'failures' => 0,
                'opened_at' => null,
            ];
        }

        return [
            'failures' => (int) ($state['failures'] ?? 0),
            'opened_at' => $state['opened_at'] !== null ? (int) $state['opened_at'] : null,
        ];
    }

    /**
     * @param  array{failures:int,opened_at:?int}  $state
     */
    private function store(array $state): void
    {
        $ttl = $this->cooldownSeconds > 0 ? $this->cooldownSeconds : 60;

        $this->cache->put($this->key(), $state, $ttl);
    }

    private function key(): string
    {
        return sprintf('circuit_breaker:%s', $this->service);
    }
}
