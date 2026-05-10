<?php

namespace Tests\Feature\Api\Admin;

use App\Infrastructure\Persistence\Configuration\Eloquent\SystemSettingModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminSystemSettingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.admin_api.token' => 'secret-token']);
    }

    public function test_list_requires_authentication(): void
    {
        $response = $this->getJson('/api/admin/system-settings');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
    }

    public function test_can_list_settings_with_json_api_structure(): void
    {
        SystemSettingModel::query()->create([
            'setting_key' => 'tracking_api_key',
            'setting_value' => 'secret',
            'value_type' => 'string',
            'updated_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/admin/system-settings');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonPath('data.0.type', 'system-settings');
        $response->assertJsonPath('data.0.attributes.key', 'tracking_api_key');
        $response->assertJsonPath('meta.count', 1);
    }

    public function test_can_create_system_setting(): void
    {
        $payload = [
            'data' => [
                'type' => 'system-settings',
                'attributes' => [
                    'key' => 'tracking_api_secret',
                    'value' => 'new-secret',
                    'value_type' => 'string',
                ],
            ],
        ];

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/admin/system-settings', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.id', 'tracking_api_secret');
        $response->assertJsonPath('data.attributes.value', 'new-secret');

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'tracking_api_secret',
            'setting_value' => 'new-secret',
        ]);
    }

    public function test_create_rejects_duplicate_keys(): void
    {
        SystemSettingModel::query()->create([
            'setting_key' => 'tracking_api_key',
            'setting_value' => 'secret',
            'value_type' => 'string',
            'updated_at' => now(),
        ]);

        $payload = [
            'data' => [
                'type' => 'system-settings',
                'attributes' => [
                    'key' => 'tracking_api_key',
                    'value' => 'other',
                    'value_type' => 'string',
                ],
            ],
        ];

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/admin/system-settings', $payload);

        $response->assertStatus(409);
        $response->assertJsonPath('errors.0.status', '409');
    }

    public function test_can_update_system_setting(): void
    {
        SystemSettingModel::query()->create([
            'setting_key' => 'tracking_api_key',
            'setting_value' => 'secret',
            'value_type' => 'string',
            'updated_at' => now(),
        ]);

        $payload = [
            'data' => [
                'type' => 'system-settings',
                'id' => 'tracking_api_key',
                'attributes' => [
                    'value' => 'updated-secret',
                    'value_type' => 'string',
                ],
            ],
        ];

        $response = $this->withHeaders($this->authHeaders())->patchJson('/api/admin/system-settings/tracking_api_key', $payload);

        $response->assertOk();
        $response->assertJsonPath('data.attributes.value', 'updated-secret');

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'tracking_api_key',
            'setting_value' => 'updated-secret',
        ]);
    }

    public function test_update_validates_payload(): void
    {
        SystemSettingModel::query()->create([
            'setting_key' => 'tracking_api_key',
            'setting_value' => 'secret',
            'value_type' => 'string',
            'updated_at' => now(),
        ]);

        $payload = [
            'data' => [
                'type' => 'system-settings',
                'id' => 'tracking_api_key',
                'attributes' => [
                    'value' => 'updated-secret',
                    'value_type' => 'unsupported',
                ],
            ],
        ];

        $response = $this->withHeaders($this->authHeaders())->patchJson('/api/admin/system-settings/tracking_api_key', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.status', '422');
        $response->assertJsonFragment(['pointer' => '/data/attributes/value_type']);
    }

    public function test_can_delete_system_setting(): void
    {
        SystemSettingModel::query()->create([
            'setting_key' => 'tracking_api_key',
            'setting_value' => 'secret',
            'value_type' => 'string',
            'updated_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders())->delete('/api/admin/system-settings/tracking_api_key');

        $response->assertNoContent();
        $this->assertDatabaseMissing('system_settings', [
            'setting_key' => 'tracking_api_key',
        ]);
    }

    /**
     * @return array<string,string>
     */
    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer secret-token'];
    }
}
