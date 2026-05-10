<?php

namespace App\Application\Monitoring\Metrics;

interface MetricsRecorder
{
    /**
     * Increment a counter metric.
     *
     * @param  array<string,string|int|float>  $tags
     */
    public function increment(string $metric, float $value = 1.0, array $tags = []): void;

    /**
     * Record a timing metric (in milliseconds).
     *
     * @param  array<string,string|int|float>  $tags
     */
    public function timing(string $metric, float $milliseconds, array $tags = []): void;

    /**
     * Record the current value of a gauge metric.
     *
     * @param  array<string,string|int|float>  $tags
     */
    public function gauge(string $metric, float $value, array $tags = []): void;
}
