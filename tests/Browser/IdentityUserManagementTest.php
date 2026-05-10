<?php

namespace Tests\Browser;

use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class IdentityUserManagementTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_admin_can_create_user_through_interface(): void
    {
        $admin = UserModel::factory()->create([
            'role' => 'admin',
            'password_hash' => bcrypt('Secret#12345A'),
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visitRoute('identity-users.create')
                ->assertSee('Neuen Benutzer anlegen')
                ->type('username', 'opsoperator')
                ->type('display_name', 'OPS Operator')
                ->type('email', 'ops@example.test')
                ->select('role', 'viewer')
                ->type('password', 'ValidPass#1234')
                ->type('password_confirmation', 'ValidPass#1234')
                ->press('Benutzer erstellen')
                ->waitForText('Benutzer wurde erstellt.')
                ->assertSee('opsoperator')
                ->assertSee('viewer');
        });

        $this->assertDatabaseHas('users', [
            'username' => 'opsoperator',
            'role' => 'viewer',
        ]);
    }
}
