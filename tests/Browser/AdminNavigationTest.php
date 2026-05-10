<?php

namespace Tests\Browser;

use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Database\Seeders\DomainDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class AdminNavigationTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_admin_navigation_displays_core_sections(): void
    {
        $this->artisan('db:seed', ['--class' => DomainDemoSeeder::class]);

        $admin = UserModel::factory()->create([
            'role' => 'admin',
            'password_hash' => bcrypt('Secret#12345A'),
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $this->browse(function (Browser $browser) use ($admin): void {
            // Aktuelle Navigation (siehe docs/SYSTEM_MENU_ROLE_MATRIX.md):
            // Hauptnav für Admin enthält Aufträge, Sendungen, Kommissionierlisten,
            // CSV-Export, Systemeinstellungen. Setup ist im Settings-Tab.
            $browser->loginAs($admin)
                ->visitRoute('admin-setup')
                ->assertPathIs('/admin/setup')
                ->assertSee('System-Setup & Monitoring')
                ->assertSee('Aufträge')
                ->assertSee('Kommissionierlisten')
                ->assertSee('Systemeinstellungen')
                ->assertSee('Logout');
        });
    }
}
