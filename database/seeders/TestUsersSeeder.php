<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Erstellt stabile, idempotente Test-User pro Rolle fuer Browser- und Akzeptanztests.
 *
 * Konvention:
 *  - Username/Email: <role>@test.ax4.local
 *  - Passwort (Klartext): "password"
 *  - 2FA: nicht modelliert in UserModel (kein Feld vorhanden) -> default off.
 *
 * Die Rolle 'noaccess' wird bewusst NICHT geseedet: Sie hat keinerlei
 * Backend-Berechtigungen (kein admin.access) und ist daher fuer
 * UI-/Navigation-Browser-Tests ohne Nutzen. Wer einen noaccess-User fuer
 * spezifische Negativ-Tests braucht, legt ihn lokal im Test an
 * (UserModel::factory()->create(['role' => 'noaccess'])).
 */
final class TestUsersSeeder extends Seeder
{
    /**
     * Test-Klartext-Passwort. Bewusst zentral & dokumentiert (siehe tests/Browser/README.md).
     */
    public const TEST_PASSWORD = 'password';

    /**
     * Rollen, fuer die ein Test-User angelegt wird.
     *
     * @var array<string,string>
     */
    private const ROLES = [
        'admin'         => 'Admin Test',
        'leiter'        => 'Leiter Test',
        'operations'    => 'Operations Test',
        'support'       => 'Support Test',
        'configuration' => 'Configuration Test',
        'identity'      => 'Identity Test',
        'viewer'        => 'Viewer Test',
    ];

    public function run(): void
    {
        $passwordHash = Hash::make(self::TEST_PASSWORD);

        foreach (self::ROLES as $role => $displayName) {
            $username = $role.'@test.ax4.local';

            UserModel::query()->firstOrCreate(
                ['username' => $username],
                [
                    'display_name'         => $displayName,
                    'email'                => $username,
                    'password_hash'        => $passwordHash,
                    'role'                 => $role,
                    'must_change_password' => false,
                    'disabled'             => false,
                ],
            );
        }
    }
}
