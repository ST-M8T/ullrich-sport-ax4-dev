<?php

namespace App\Application\Monitoring\Metrics;

final class NullMetricsRecorder implements MetricsRecorder
{
    public function increment(string $metric, float $value = 1.0, array $tags = []): void
    {
        // Metrics disabled.
    }

    public function timing(string $metric, float $milliseconds, array $tags = []): void
    {
        // Metrics disabled.
    }

    public function gauge(string $metric, float $value, array $tags = []): void
    {
        // Metrics disabled.
    }
}
