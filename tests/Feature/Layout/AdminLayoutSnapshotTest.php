<?php

namespace Tests\Feature\Layout;

use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MatchesHtmlSnapshot;
use Tests\TestCase;

class AdminLayoutSnapshotTest extends TestCase
{
    use MatchesHtmlSnapshot;
    use RefreshDatabase;

    public function test_layout_renders_components_snapshot(): void
    {
        $user = UserModel::query()->create([
            'username' => 'admin-example',
            'display_name' => 'Admin Example',
            'email' => 'admin@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'admin',
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $this->actingAs($user);

        $html = view('tests.layout-sample', [
            'identityUser' => $user->toIdentityUser(),
            'messages' => [
                ['text' => 'Systeminformation gespeichert.', 'type' => 'info'],
                ['text' => 'Bitte überprüfen Sie die Filtereinstellungen.', 'type' => 'warning'],
            ],
            'success' => 'Konfiguration gespeichert.',
            'showSpinner' => true,
            'spinnerMessage' => 'System Jobs werden geladen...',
        ])->render();

        $this->assertMatchesHtmlSnapshot('layout-admin', $html);
    }
}
