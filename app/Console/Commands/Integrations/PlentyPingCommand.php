<?php

namespace App\Console\Commands\Integrations;

use App\Application\Monitoring\SystemJobLifecycleService;
use App\Domain\Integrations\Contracts\PlentyOrderGateway;
use App\Support\Exceptions\CircuitBreakerOpenException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

final class PlentyPingCommand extends Command
{
    protected $signature = 'plenty:ping {--show-body : Display the response body}';

    protected $description = 'Performs a health check against the Plenty REST API.';

    public function handle(PlentyOrderGateway $gateway, SystemJobLifecycleService $jobs): int
    {
        $job = $jobs->start('integration.plenty.ping', 'integration', 'plenty');

        try {
            $result = $gateway->ping();

            $jobs->finish($job, 'completed', $result);

            $this->info(sprintf(
                'Plenty responded with HTTP %d in %.2f ms',
                $result['status'] ?? 0,
                $result['duration_ms'] ?? 0.0
            ));

            if ($this->option('show-body') && isset($result['body'])) {
                $this->line($this->formatBody($result['body']));
            }

            return self::SUCCESS;
        } catch (CircuitBreakerOpenException $exception) {
            $jobs->finish($job, 'failed', [
                'reason' => 'circuit_open',
                'retry_after' => $exception->retryAfter(),
            ], $exception->getMessage());

            $retryAt = Carbon::createFromTimestamp($exception->retryAfter());
            $this->error(sprintf(
                'Plenty circuit breaker is open. Retry in %s (at %s).',
                $retryAt->diffForHumans(syntax: null, options: \Carbon\CarbonInterface::DIFF_ABSOLUTE),
                $retryAt->toDateTimeString()
            ));

            return self::FAILURE;
        } catch (Throwable $exception) {
            $jobs->finish($job, 'failed', [
                'reason' => 'exception',
                'message' => $exception->getMessage(),
            ], $exception->getMessage());

            $this->error('Plenty ping failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function formatBody(mixed $body): string
    {
        if (is_array($body) || is_object($body)) {
            return json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: (string) json_encode($body);
        }

        return (string) $body;
    }
}
