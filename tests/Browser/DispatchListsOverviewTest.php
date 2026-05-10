<?php

namespace Tests\Browser;

use App\Infrastructure\Persistence\Dispatch\Eloquent\DispatchListModel;
use App\Infrastructure\Persistence\Dispatch\Eloquent\DispatchMetricsModel;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class DispatchListsOverviewTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_dispatch_lists_table_renders_list_entries(): void
    {
        $admin = UserModel::factory()->create([
            'role' => 'admin',
            'password_hash' => bcrypt('Secret#12345A'),
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $list = DispatchListModel::factory()->create([
            'reference' => 'REF-123',
            'title' => 'Morning Run',
            'status' => 'open',
        ]);

        DispatchMetricsModel::factory()->create([
            'dispatch_list_id' => $list->getKey(),
            'total_orders' => 12,
            'total_packages' => 30,
            'total_items' => 48,
            'total_truck_slots' => 5,
            'metrics' => ['lanes' => 4],
        ]);

        $this->browse(function (Browser $browser) use ($admin, $list): void {
            // UI ist deutsch (Ubiquitous Language §4): "Kommissionierlisten" statt "Dispatch Lists",
            // "SCHLIESSEN"/"EXPORT" als Action-Buttons (UPPERCASE im Markup).
            $browser->loginAs($admin)
                ->visitRoute('dispatch-lists')
                ->assertPathIs('/admin/dispatch/lists')
                ->assertSee('Kommissionierlisten')
                ->assertSee('REF-123')
                ->assertSee('Morning Run')
                ->assertSee('open')
                ->assertSee('SCHLIESSEN')
                ->assertSee('EXPORT')
                ->assertSourceHas('dispatch-close-'.$list->getKey());
        });
    }
}
