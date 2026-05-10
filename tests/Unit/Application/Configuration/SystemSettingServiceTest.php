<?php

namespace Tests\Unit\Application\Configuration;

use App\Application\Configuration\SecretEncryptionService;
use App\Application\Configuration\SecretRotationService;
use App\Application\Configuration\SystemSettingService;
use App\Application\Monitoring\AuditLogger;
use App\Domain\Configuration\Contracts\SecretRotationRepository;
use App\Domain\Configuration\Contracts\SystemSettingRepository;
use App\Domain\Configuration\SystemSetting;
use App\Domain\Monitoring\AuditLogEntry;
use App\Domain\Monitoring\Contracts\AuditLogRepository;
use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class SystemSettingServiceTest extends TestCase
{
    private SystemSettingRepository&MockInterface $repository;

    private SecretRotationRepository&MockInterface $secretRotationRepository;

    private AuditLogRepository&MockInterface $auditRepository;

    private SystemSettingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(SystemSettingRepository::class);
        $this->secretRotationRepository = Mockery::mock(SecretRotationRepository::class);
        $this->auditRepository = Mockery::mock(AuditLogRepository::class);

        $secretRotation = new SecretRotationService($this->secretRotationRepository);
        $secretEncryption = new SecretEncryptionService;
        $auditLogger = new AuditLogger($this->auditRepository);

        $this->service = new SystemSettingService($this->repository, $secretRotation, $auditLogger, $secretEncryption);
    }

    public function test_all_returns_normalised_array(): void
    {
        $settings = [
            SystemSetting::hydrate('app.name', 'Demo', 'string', null, new DateTimeImmutable),
            SystemSetting::hydrate('maintenance.enabled', '1', 'bool', 1, new DateTimeImmutable),
        ];

        $this->repository
            ->shouldReceive('all')
            ->once()
            ->andReturn($settings);

        $result = $this->service->all();

        $this->assertCount(2, $result);
        $this->assertSame('app.name', $result[0]->key());
        $this->assertSame('Demo', $result[0]->rawValue());
    }

    public function test_set_upserts_setting(): void
    {
        $this->repository
            ->shouldReceive('upsert')
            ->once()
            ->withArgs(function (SystemSetting $setting): bool {
                $this->assertSame('features.queue', $setting->key());
                $this->assertSame('enabled', $setting->rawValue());
                $this->assertSame('string', $setting->valueType());

                return true;
            });

        $this->auditRepository
            ->shouldReceive('append')
            ->once()
            ->withArgs(function (AuditLogEntry $entry): bool {
                $this->assertSame('configuration.setting.updated', $entry->action());
                $context = $entry->context();
                $this->assertSame('features.queue', $context['setting_key'] ?? null);

                return true;
            });

        $this->service->set('features.queue', 'enabled');
    }

    public function test_get_returns_value_or_default(): void
    {
        $setting = SystemSetting::hydrate('app.locale', 'de', 'string', null, new DateTimeImmutable);

        $this->repository
            ->shouldReceive('get')
            ->once()
            ->with('app.locale')
            ->andReturn($setting);

        $this->assertSame('de', $this->service->get('app.locale'));

        $this->repository
            ->shouldReceive('get')
            ->once()
            ->with('app.region')
            ->andReturnNull();

        $this->assertSame('EU', $this->service->get('app.region', 'EU'));
    }
}
