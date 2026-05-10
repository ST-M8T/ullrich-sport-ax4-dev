<?php

declare(strict_types=1);

namespace App\Application\Monitoring;

use App\Domain\Monitoring\SystemJobEntry;

final class SystemJobPolicyService
{
    /**
     * @var array<string,array<string,mixed>>
     */
    private array $policiesByJobType = [];

    /**
     * @var array<string,array<string,mixed>>
     */
    private array $policiesByJobName = [];

    /**
     * @param  array<int|string,array<string,mixed>>  $jobPolicies
     */
    public function __construct(array $jobPolicies = [])
    {
        $this->normalizePolicies($jobPolicies);
    }

    /**
     * @param  array<int|string,array<string,mixed>>  $policies
     */
    public function register(array $policies): void
    {
        $this->normalizePolicies($policies);
    }

    /**
     * @return array<string, mixed>
     */
    public function policyForEntry(SystemJobEntry $entry): array
    {
        $payload = $entry->payload();

        $jobType = null;
        if (isset($payload['job_type']) && is_string($payload['job_type'])) {
            $jobType = trim($payload['job_type']);
        } elseif (isset($payload['tracking_job_type']) && is_string($payload['tracking_job_type'])) {
            $jobType = trim($payload['tracking_job_type']);
        }

        if ($jobType && isset($this->policiesByJobType[$jobType])) {
            return $this->policiesByJobType[$jobType];
        }

        $jobName = $entry->jobName();
        if (isset($this->policiesByJobName[$jobName])) {
            return $this->policiesByJobName[$jobName];
        }

        return [];
    }

    /**
     * @param  array<int|string,array<string,mixed>>  $policies
     */
    private function normalizePolicies(array $policies): void
    {
        foreach ($policies as $key => $policy) {
            if (! is_array($policy)) {
                continue;
            }

            $jobType = $policy['job_type'] ?? $policy['type'] ?? (is_string($key) ? $key : null);
            if (is_string($jobType)) {
                $jobType = trim($jobType);
                if ($jobType !== '') {
                    $this->policiesByJobType[$jobType] = $policy;
                    if (! isset($policy['job_name']) && ! isset($policy['system_job']) && ! isset($this->policiesByJobName[$jobType])) {
                        $this->policiesByJobName[$jobType] = $policy;
                    }
                }
            }

            foreach (['job_name', 'system_job', 'name'] as $nameKey) {
                if (isset($policy[$nameKey]) && is_string($policy[$nameKey])) {
                    $jobName = trim($policy[$nameKey]);
                    if ($jobName !== '') {
                        $this->policiesByJobName[$jobName] = $policy;
                    }
                }
            }
        }
    }
}
