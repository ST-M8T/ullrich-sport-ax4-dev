<?php

declare(strict_types=1);

namespace App\Application\Monitoring;

use App\Application\Monitoring\Metrics\MetricsRecorder;
use App\Domain\Monitoring\Contracts\DatabaseHealthProbe;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Throwable;

final class HealthCheckService
{
    public function __construct(
        private readonly MetricsRecorder $metrics,
        private readonly DatabaseHealthProbe $databaseProbe,
    ) {
        // Dependencies provided via the service container.
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function checks(): array
    {
        return [
            'app' => $this->checkApplication(),
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'sentry' => $this->checkSentry(),
            'metrics' => $this->checkMetrics(),
            'telescope' => $this->checkTelescope(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function checkApplication(): array
    {
        return [
            'status' => 'ok',
            'environment' => Config::get('app.env'),
            'version' => App::version(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function checkDatabase(): array
    {
        $connection = Config::get('database.default', 'sqlite');

        try {
            $this->databaseProbe->ping($connection);

            return [
                'status' => 'ok',
                'connection' => $connection,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'fail',
                'connection' => $connection,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function checkCache(): array
    {
        $store = Cache::getDefaultDriver();

        try {
            Cache::store($store)->get('_health_check');

            return [
                'status' => 'ok',
                'store' => $store,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'fail',
                'store' => $store,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function checkQueue(): array
    {
        $connection = Config::get('queue.default', 'sync');

        try {
            $queue = Queue::connection($connection);
            $queueName = method_exists($queue, 'getQueue')
                ? $queue->getQueue(null)
                : null;

            return [
                'status' => 'ok',
                'connection' => $connection,
                'queue' => $queueName,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'fail',
                'connection' => $connection,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function checkSentry(): array
    {
        $dsn = Config::get('sentry.dsn');
        $enabled = empty($dsn) === false;

        return [
            'status' => $enabled ? 'ok' : 'warn',
            'enabled' => $enabled,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function checkMetrics(): array
    {
        $enabled = (bool) Config::get('monitoring.statsd.enabled', false);

        if ($enabled === false) {
            return [
                'status' => 'skip',
                'enabled' => false,
            ];
        }

        try {
            $this->metrics->increment('health_check.ping', 1, ['source' => 'health_check']);

            return [
                'status' => 'ok',
                'enabled' => true,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'fail',
                'enabled' => true,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function checkTelescope(): array
    {
        $enabled = (bool) Config::get('monitoring.telescope.enabled', false);

        return [
            'status' => $enabled ? 'ok' : 'skip',
            'enabled' => $enabled,
        ];
    }
}
