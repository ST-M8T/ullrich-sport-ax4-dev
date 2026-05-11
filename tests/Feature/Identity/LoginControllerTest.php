<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_for_guests(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Anmeldung');
    }

    public function test_authenticated_user_is_redirected_from_login_page(): void
    {
        $user = UserModel::factory()->create([
            'role' => 'admin',
            'password_hash' => bcrypt('Password!123456'),
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $this->actingAs($user);

        $response = $this->get('/login');

        $response->assertRedirect(route('dispatch-lists'));
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = UserModel::factory()->create([
            'username' => 'adminuser',
            'role' => 'admin',
            'password_hash' => bcrypt('Password!123456'),
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $response = $this->post(route('login.perform'), [
            'username' => 'adminuser',
            'password' => 'Password!123456',
        ]);

        $response->assertRedirect(route('dispatch-lists'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_using_email_instead_of_username(): void
    {
        UserModel::factory()->create([
            'username' => 'mail-login-user',
            'email' => 'mail-login@example.test',
            'role' => 'admin',
            'password_hash' => bcrypt('Password!123456'),
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $response = $this->post(route('login.perform'), [
            'username' => 'mail-login@example.test',
            'password' => 'Password!123456',
        ]);

        $response->assertRedirect(route('dispatch-lists'));
        $this->assertAuthenticated();
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        UserModel::factory()->create([
            'username' => 'invalidtest',
            'role' => 'admin',
            'password_hash' => bcrypt('Password!123456'),
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $response = $this->from('/login')->post(route('login.perform'), [
            'username' => 'invalidtest',
            'password' => 'WrongPassword',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('username', null, 'login');
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = UserModel::factory()->create([
            'role' => 'admin',
            'password_hash' => bcrypt('Password!123456'),
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('info');
        $this->assertGuest();
    }
}
