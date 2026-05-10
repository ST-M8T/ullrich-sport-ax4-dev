<?php

namespace Tests\Feature\Configuration;

use App\Application\Configuration\SystemSettingService;
use App\Infrastructure\Persistence\Configuration\Eloquent\SystemSecretVersionModel;
use App\Infrastructure\Persistence\Configuration\Eloquent\SystemSettingModel;
use App\Support\Security\SecurityContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SystemSecretRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_secret_rotation_records_versions_and_encryption(): void
    {
        /** @var SystemSettingService $service */
        $service = $this->app->make(SystemSettingService::class);

        $context = SecurityContext::system('test-suite');

        $service->set('tracking_api_secret', 'initial-secret', 'secret', null, $context);

        $stored = SystemSettingModel::query()->find('tracking_api_secret');
        $this->assertNotNull($stored);
        $this->assertNotSame('initial-secret', $stored->setting_value);

        $this->assertSame(1, SystemSecretVersionModel::query()->count());

        $service->set('tracking_api_secret', 'rotated-secret', 'secret', null, $context);

        $this->assertSame(2, SystemSecretVersionModel::query()->count());

        /** @var SystemSecretVersionModel $latest */
        $latest = SystemSecretVersionModel::query()->orderByDesc('version')->first();
        $this->assertNotNull($latest);
        $this->assertEquals(2, $latest->version);
        $this->assertNotNull($latest->encrypted_value);

        /** @var SystemSecretVersionModel $previous */
        $previous = SystemSecretVersionModel::query()->where('version', 1)->first();
        $this->assertNotNull($previous);
        $this->assertNotNull($previous->deactivated_at);

        $this->assertSame('rotated-secret', $service->get('tracking_api_secret'));
    }
}
