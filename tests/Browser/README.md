# Browser-Tests (Laravel Dusk)

## Test-User pro Rolle

Stabile, idempotente Test-User werden vom `Database\Seeders\TestUsersSeeder`
angelegt. Die Daten sind bewusst fuer die lokale Test-/Dev-Umgebung gedacht
und duerfen niemals in Produktion geseedet werden.

| Rolle           | Username / E-Mail                  | Passwort   | Display-Name        |
| --------------- | ---------------------------------- | ---------- | ------------------- |
| `admin`         | `admin@test.ax4.local`             | `password` | Admin Test          |
| `leiter`        | `leiter@test.ax4.local`            | `password` | Leiter Test         |
| `operations`    | `operations@test.ax4.local`        | `password` | Operations Test     |
| `support`       | `support@test.ax4.local`           | `password` | Support Test        |
| `configuration` | `configuration@test.ax4.local`     | `password` | Configuration Test  |
| `identity`      | `identity@test.ax4.local`          | `password` | Identity Test       |
| `viewer`        | `viewer@test.ax4.local`            | `password` | Viewer Test         |

Die Rolle `noaccess` wird bewusst **nicht** geseedet: Sie hat keinerlei
Backend-Berechtigungen (kein `admin.access`) und ist daher fuer
UI-/Navigation-Browser-Tests ohne Nutzen. Wer einen `noaccess`-User fuer
spezifische Negativ-Tests braucht, legt ihn lokal im Test an:

```php
UserModel::factory()->create(['role' => 'noaccess']);
```

## Seeder ausfuehren

```bash
php artisan db:seed --class=TestUsersSeeder
```

Der Seeder ist idempotent (`firstOrCreate` auf `username`), darf also beliebig
oft laufen, ohne Duplikate zu erzeugen oder bestehende User zu veraendern.

## Login-Helper

Fuer Dusk-Tests steht der Trait `Tests\Browser\Concerns\LogsInWithRole`
bereit. Beispiel:

```php
use Tests\Browser\Concerns\LogsInWithRole;

final class MyFeatureTest extends DuskTestCase
{
    use DatabaseMigrations;
    use LogsInWithRole;

    public function test_operations_user_sees_orders(): void
    {
        $this->artisan('db:seed', ['--class' => TestUsersSeeder::class]);

        $this->browse(function (Browser $browser): void {
            $this->loginAsRole($browser, 'operations')
                ->visit('/admin/orders')
                ->assertSee('Auftraege');
        });
    }
}
```

## Hinweise

- Single Source of Truth fuer das Test-Passwort:
  `TestUsersSeeder::TEST_PASSWORD`.
- Die Login-Form-Felder (`username`, `password`) und der Submit-Button
  (`Anmelden`) entsprechen `resources/views/identity/auth/login.blade.php`.
- 2FA: Im aktuellen `UserModel` nicht als Feld modelliert; Login funktioniert
  daher fuer Test-User ohne weiteren Faktor.
