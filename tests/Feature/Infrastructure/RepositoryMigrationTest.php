<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Domain\Configuration\Contracts\SystemSettingRepository;
use App\Domain\Dispatch\Contracts\DispatchListRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentSenderProfileRepository;
use App\Domain\Identity\Contracts\UserRepository;
use App\Domain\Monitoring\Contracts\AuditLogRepository;
use App\Domain\Tracking\Contracts\TrackingJobRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature Test für die Persistenz-Schichtung.
 *
 * Verlangt, dass alle Repository-Implementierungen unter
 * `App\Infrastructure\Persistence\<Context>\Eloquent\` liegen.
 * Der frühere Zwischenstand `App\Domains\<Context>\Repositories\Eloquent\`
 * wurde aufgelöst (siehe docs/SYSTEM_CLEANUP_BACKLOG.md, C-07).
 */
final class RepositoryMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const PERSISTENCE_NAMESPACE_PATTERN = 'App\\Infrastructure\\Persistence\\';

    private const ELOQUENT_NAMESPACE_FRAGMENT = '\\Eloquent\\';

    private const FORBIDDEN_LEGACY_NAMESPACES = [
        'App\\Domains\\',
        'App\\Infrastructure\\Persistence\\Eloquent\\',
    ];

    public function test_configuration_repositories_can_be_resolved(): void
    {
        $this->assertRepositoryUsesPersistenceNamespace(
            SystemSettingRepository::class,
            'Configuration'
        );
    }

    public function test_identity_repositories_can_be_resolved(): void
    {
        $this->assertRepositoryUsesPersistenceNamespace(
            UserRepository::class,
            'Identity'
        );
    }

    public function test_fulfillment_repositories_can_be_resolved(): void
    {
        $this->assertRepositoryUsesPersistenceNamespace(
            FulfillmentSenderProfileRepository::class,
            'Fulfillment'
        );
    }

    public function test_dispatch_repositories_can_be_resolved(): void
    {
        $this->assertRepositoryUsesPersistenceNamespace(
            DispatchListRepository::class,
            'Dispatch'
        );
    }

    public function test_tracking_repositories_can_be_resolved(): void
    {
        $this->assertRepositoryUsesPersistenceNamespace(
            TrackingJobRepository::class,
            'Tracking'
        );
    }

    public function test_monitoring_repositories_can_be_resolved(): void
    {
        $this->assertRepositoryUsesPersistenceNamespace(
            AuditLogRepository::class,
            'Monitoring'
        );
    }

    public function test_all_repository_namespaces_use_new_structure(): void
    {
        $repositories = [
            SystemSettingRepository::class,
            UserRepository::class,
            FulfillmentSenderProfileRepository::class,
            DispatchListRepository::class,
            TrackingJobRepository::class,
            AuditLogRepository::class,
        ];

        foreach ($repositories as $interface) {
            $implementation = $this->app->make($interface);
            $className = get_class($implementation);

            foreach (self::FORBIDDEN_LEGACY_NAMESPACES as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $className,
                    "Repository {$className} darf nicht im veralteten Namespace {$forbidden} liegen."
                );
            }

            $this->assertStringContainsString(
                self::PERSISTENCE_NAMESPACE_PATTERN,
                $className,
                "Repository {$className} sollte unter App\\Infrastructure\\Persistence\\ liegen."
            );

            $this->assertStringContainsString(
                self::ELOQUENT_NAMESPACE_FRAGMENT,
                $className,
                "Repository {$className} sollte unter ...\\Eloquent\\ liegen."
            );
        }
    }

    private function assertRepositoryUsesPersistenceNamespace(string $interface, string $context): void
    {
        $repository = $this->app->make($interface);

        $this->assertInstanceOf($interface, $repository);
        $this->assertStringContainsString(
            self::PERSISTENCE_NAMESPACE_PATTERN.$context.self::ELOQUENT_NAMESPACE_FRAGMENT,
            get_class($repository),
            "Repository für {$interface} sollte unter App\\Infrastructure\\Persistence\\{$context}\\Eloquent\\ liegen."
        );
    }
}
