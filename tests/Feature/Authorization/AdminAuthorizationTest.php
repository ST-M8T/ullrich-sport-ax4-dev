<?php

namespace Tests\Feature\Authorization;

use App\Application\Identity\UserAccountService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_area(): void
    {
        $response = $this->get('/admin/setup');
        $response->assertRedirect(route('login'));
    }

    public function test_user_without_permission_gets_forbidden(): void
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

        $response = $this->get('/admin/logs');
        $response->assertStatus(403);
    }

    public function test_role_change_updates_permissions(): void
    {
        $user = UserModel::query()->create([
            'username' => 'operator',
            'display_name' => 'Operator',
            'email' => 'operator@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'viewer',
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $this->actingAs($user);

        $this->get('/admin/identity/users')->assertStatus(403);

        /** @var UserAccountService $accounts */
        $accounts = app(UserAccountService::class);
        $accounts->updateUser(Identifier::fromInt($user->getKey()), [
            'role' => 'admin',
        ]);

        $user->refresh();
        $this->actingAs($user);

        $this->get('/admin/identity/users')->assertOk();
    }

    public function test_gate_resolves_permissions_for_role(): void
    {
        $user = UserModel::query()->create([
            'username' => 'supporter',
            'display_name' => 'Supporter',
            'email' => 'support@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'support',
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $this->actingAs($user);

        $this->assertTrue(Gate::allows('monitoring.audit_logs.view'));
        $this->assertTrue(Gate::allows('admin.logs.view'));
        $this->assertFalse(Gate::allows('identity.users.manage'));
    }
}
