<?php

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Configuration\Eloquent\SystemSettingModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SystemSettingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_setting_value(): void
    {
        SystemSettingModel::factory()->create([
            'setting_key' => 'app.locale',
            'setting_value' => 'de',
            'value_type' => 'string',
            'updated_by_user_id' => null,
        ]);

        $response = $this->getJson('/api/v1/settings/app.locale');

        $response->assertOk();
        $response->assertJson([
            'key' => 'app.locale',
            'value' => 'de',
        ]);
    }

    public function test_show_returns_not_found_for_missing_setting(): void
    {
        $this->getJson('/api/v1/settings/missing.setting')->assertStatus(404);
    }
}
