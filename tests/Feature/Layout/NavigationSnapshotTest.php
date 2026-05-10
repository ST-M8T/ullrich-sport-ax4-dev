<?php

namespace Tests\Feature\Layout;

use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MatchesHtmlSnapshot;
use Tests\TestCase;

class NavigationSnapshotTest extends TestCase
{
    use MatchesHtmlSnapshot;
    use RefreshDatabase;

    public function test_admin_navigation_matches_snapshot(): void
    {
        $user = UserModel::query()->create([
            'username' => 'admin',
            'display_name' => 'Admin',
            'email' => 'admin@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'admin',
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $this->actingAs($user);

        $html = view('components.navigation', [
            'currentSection' => 'monitoring-system-jobs',
            'identityUser' => $user->toIdentityUser(),
        ])->render();

        $this->assertMatchesHtmlSnapshot('navigation-admin', $html);
    }

    public function test_viewer_navigation_matches_snapshot(): void
    {
        $user = UserModel::query()->create([
            'username' => 'viewer',
            'display_name' => 'Viewer',
            'email' => 'viewer@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'viewer',
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $this->actingAs($user);

        $html = view('components.navigation', [
            'currentSection' => 'fulfillment-orders',
            'identityUser' => $user->toIdentityUser(),
        ])->render();

        $this->assertMatchesHtmlSnapshot('navigation-viewer', $html);
    }
}
