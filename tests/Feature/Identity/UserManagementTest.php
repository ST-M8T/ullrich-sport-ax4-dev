<?php

namespace Tests\Feature\Identity;

use App\Application\Identity\AuthenticationService;
use App\Application\Identity\UserAccountService;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_user_via_form(): void
    {
        $this->authenticateIdentityAdmin();

        $response = $this->post(route('identity-users.store'), [
            'username' => 'jdoe',
            'display_name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'admin',
            'password' => 'Secr3t!234567',
            'password_confirmation' => 'Secr3t!234567',
            'must_change_password' => '1',
            'disabled' => '0',
        ]);

        $createdId = UserModel::query()->where('username', 'jdoe')->value('id');

        $this->assertNotNull($createdId);
        $response->assertRedirect(route('configuration-settings', [
            'tab' => 'verwaltung',
            'verwaltung_tab' => 'identity-users',
        ]));

        $this->assertDatabaseHas('users', [
            'id' => $createdId,
            'username' => 'jdoe',
            'display_name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'admin',
            'must_change_password' => 1,
            'disabled' => 0,
        ]);
    }

    public function test_admin_can_update_user_details_and_role(): void
    {
        /** @var UserAccountService $accounts */
        $accounts = $this->app->make(UserAccountService::class);

        $this->authenticateIdentityAdmin();

        $user = $accounts->createUser(
            username: 'agent',
            plainPassword: 'Agent#123456',
            role: 'viewer',
            displayName: 'Agent Smith',
            email: 'agent@example.com',
            requirePasswordChange: false,
            disabled: false,
        );

        $response = $this->put(route('identity-users.update', ['user' => $user->id()->toInt()]), [
            'username' => 'agent',
            'display_name' => 'Agent Updated',
            'email' => 'agent.updated@example.com',
            'role' => 'support',
            'must_change_password' => '0',
            'disabled' => '1',
        ]);

        $response->assertRedirect(route('configuration-settings', [
            'tab' => 'verwaltung',
            'verwaltung_tab' => 'identity-users',
        ]));

        $this->assertDatabaseHas('users', [
            'id' => $user->id()->toInt(),
            'display_name' => 'Agent Updated',
            'email' => 'agent.updated@example.com',
            'role' => 'support',
            'must_change_password' => 0,
            'disabled' => 1,
        ]);
    }

    public function test_admin_can_reset_password_and_require_change(): void
    {
        /** @var UserAccountService $accounts */
        $accounts = $this->app->make(UserAccountService::class);

        $this->authenticateIdentityAdmin();

        $user = $accounts->createUser(
            username: 'resetme',
            plainPassword: 'Initial!23456',
            role: 'viewer',
            displayName: null,
            email: 'reset@example.com',
            requirePasswordChange: false,
            disabled: false,
        );

        $response = $this->post(route('identity-users.reset-password', ['user' => $user->id()->toInt()]), [
            'new_password' => 'NewPass!23456',
            'new_password_confirmation' => 'NewPass!23456',
            'require_password_change' => '1',
        ]);

        $response->assertRedirect(route('identity-users.show', ['user' => $user->id()->toInt()]));

        /** @var UserModel $model */
        $model = UserModel::query()->findOrFail($user->id()->toInt());

        $this->assertTrue($model->must_change_password);
        $this->assertNotEquals($user->passwordHash()->toString(), $model->password_hash);
    }

    public function test_login_attempts_are_visible_on_user_detail_page(): void
    {
        /** @var UserAccountService $accounts */
        $accounts = $this->app->make(UserAccountService::class);

        $this->authenticateIdentityAdmin();

        $user = $accounts->createUser(
            username: 'audited',
            plainPassword: 'Audit#123456',
            role: 'viewer',
            displayName: 'Audited User',
            email: 'audit@example.com',
            requirePasswordChange: false,
            disabled: false,
        );

        /** @var AuthenticationService $auth */
        $auth = $this->app->make(AuthenticationService::class);

        $auth->attempt('audited', 'wrong-password', '127.0.0.1', 'PHPUnit');
        $auth->attempt('audited', 'Audit#123456', '127.0.0.1', 'PHPUnit');

        $response = $this->get(route('identity-users.show', ['user' => $user->id()->toInt()]));

        $response
            ->assertStatus(200)
            ->assertSee('Fehlgeschlagen')
            ->assertSee('Erfolgreich')
            ->assertSee('invalid_credentials');
    }

    public function test_admin_can_toggle_user_status_via_quick_action(): void
    {
        /** @var UserAccountService $accounts */
        $accounts = $this->app->make(UserAccountService::class);

        $this->authenticateIdentityAdmin();

        $user = $accounts->createUser(
            username: 'toggleme',
            plainPassword: 'Toggle#123456',
            role: 'viewer',
            displayName: 'Toggle User',
            email: 'toggle@example.com',
            requirePasswordChange: false,
            disabled: false,
        );

        $disableResponse = $this->post(route('identity-users.update-status', ['user' => $user->id()->toInt()]), [
            'disabled' => '1',
        ]);

        $disableResponse->assertRedirect(route('identity-users.show', ['user' => $user->id()->toInt()]));

        $this->assertDatabaseHas('users', [
            'id' => $user->id()->toInt(),
            'disabled' => 1,
        ]);

        $enableResponse = $this->post(route('identity-users.update-status', ['user' => $user->id()->toInt()]), [
            'disabled' => '0',
        ]);

        $enableResponse->assertRedirect(route('identity-users.show', ['user' => $user->id()->toInt()]));

        $this->assertDatabaseHas('users', [
            'id' => $user->id()->toInt(),
            'disabled' => 0,
        ]);
    }

    private function authenticateIdentityAdmin(): void
    {
        $user = UserModel::query()->create([
            'username' => 'identity-admin',
            'display_name' => 'Identity Admin',
            'email' => 'identity@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'identity',
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $this->actingAs($user);
    }
}
