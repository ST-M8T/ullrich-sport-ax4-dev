<?php

declare(strict_types=1);

namespace Tests\Browser\Concerns;

use Database\Seeders\TestUsersSeeder;
use Laravel\Dusk\Browser;

/**
 * Browser-Test-Helper: Login via UI-Formular fuer einen seeded Test-User pro Rolle.
 *
 * Voraussetzung: TestUsersSeeder wurde ausgefuehrt (z.B. in setUp() oder via
 * $this->artisan('db:seed', ['--class' => TestUsersSeeder::class])).
 *
 * Die Anmeldedaten stammen ausschliesslich aus TestUsersSeeder (Single Source of
 * Truth), damit Test- und Seeder-Konvention nicht auseinanderlaufen.
 */
trait LogsInWithRole
{
    /**
     * Loggt den seeded Test-User der angegebenen Rolle ueber das Login-Formular ein.
     *
     * @param  string  $role  Eine der von TestUsersSeeder unterstuetzten Rollen,
     *                        z.B. 'admin', 'leiter', 'operations', 'support',
     *                        'configuration', 'identity', 'viewer'.
     */
    protected function loginAsRole(Browser $browser, string $role): self
    {
        $browser->visit('/login')
            ->type('username', $role.'@test.ax4.local')
            ->type('password', TestUsersSeeder::TEST_PASSWORD)
            ->press('Anmelden')
            ->assertPathIsNot('/login');

        return $this;
    }
}
