<?php

namespace App\Infrastructure\Monitoring\Metrics;

use App\Application\Monitoring\Metrics\MetricsRecorder;

final class StatsDMetricsRecorder implements MetricsRecorder
{
    /**
     * @param array{
     *     enabled: bool,
     *     host: string,
     *     port: int,
     *     prefix?: string,
     *     timeout?: float,
     *     global_tags?: array<int,string>
     * } $config
     */
    public function __construct(private readonly array $config) {}

    public function increment(string $metric, float $value = 1.0, array $tags = []): void
    {
        $this->send($metric, $value, 'c', $tags);
    }

    public function timing(string $metric, float $milliseconds, array $tags = []): void
    {
        $this->send($metric, $milliseconds, 'ms', $tags);
    }

    public function gauge(string $metric, float $value, array $tags = []): void
    {
        $this->send($metric, $value, 'g', $tags);
    }

    /**
     * @param  array<string,string|int|float>  $tags
     */
    private function send(string $metric, float $value, string $type, array $tags = []): void
    {
        if (! ($this->config['enabled'] ?? false)) {
            return;
        }

        $metricName = $this->prefixMetric($metric);
        if ($metricName === null) {
            return;
        }

        $payload = sprintf('%s:%s|%s', $metricName, $this->formatValue($value), $type);

        $tagString = $this->formatTags($tags);
        if ($tagString !== '') {
            $payload .= '|#'.$tagString;
        }

        $this->write($payload);
    }

    private function prefixMetric(string $metric): ?string
    {
        $metric = trim($metric);
        if ($metric === '') {
            return null;
        }

        $prefix = $this->config['prefix'] ?? '';

        if ($prefix === '') {
            return $metric;
        }

        $prefix = rtrim((string) $prefix, '.');

        return $prefix === '' ? $metric : "{$prefix}.{$metric}";
    }

    private function formatValue(float $value): string
    {
        if (abs($value - (int) $value) < 0.0001) {
            return (string) (int) round($value);
        }

        return number_format($value, 4, '.', '');
    }

    /**
     * @param  array<string,string|int|float>  $tags
     */
    private function formatTags(array $tags): string
    {
        $globalTags = $this->config['global_tags'] ?? [];
        $tagPairs = [];

        foreach ($globalTags as $tag) {
            $tag = trim((string) $tag);
            if ($tag !== '') {
                $tagPairs[] = $tag;
            }
        }

        foreach ($tags as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }

            $tagPairs[] = sprintf('%s:%s', $key, $this->sanitizeTagValue($value));
        }

        return implode(',', $tagPairs);
    }

    private function sanitizeTagValue(string|int|float $value): string
    {
        if (is_float($value)) {
            return number_format($value, 4, '.', '');
        }

        return trim((string) $value);
    }

    private function write(string $payload): void
    {
        $host = $this->config['host'] ?? '127.0.0.1';
        $port = (int) ($this->config['port'] ?? 8125);

        $resource = @fsockopen(sprintf('udp://%s', $host), $port);
        if (! is_resource($resource)) {
            return;
        }

        $timeout = (float) ($this->config['timeout'] ?? 0.0);
        if ($timeout > 0) {
            $microseconds = (int) max(1, $timeout * 1_000_000);
            stream_set_timeout($resource, 0, $microseconds);
        }

        @fwrite($resource, $payload);
        @fclose($resource);
    }
}
