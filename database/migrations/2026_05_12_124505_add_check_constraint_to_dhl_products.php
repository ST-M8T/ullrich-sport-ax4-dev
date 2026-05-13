<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a DB-side CHECK constraint to `dhl_products` enforcing that a product
 * cannot be its own replacement (`replaced_by_code <> code`).
 *
 * Domain already enforces this invariant in `DhlProduct`; the DB-level
 * constraint is a hard safety net against direct SQL writes or migrations
 * gone wrong. Idempotent across migrate / rollback / fresh.
 *
 * Engineering-Handbuch §15 (Invarianten) + §24 (Idempotenz).
 */
return new class extends Migration
{
    private const CONSTRAINT_NAME = 'chk_dhl_products_no_self_replacement';

    public function up(): void
    {
        if (! Schema::hasTable('dhl_products')) {
            return;
        }

        $driver = DB::getDriverName();

        try {
            match ($driver) {
                'pgsql' => DB::statement(sprintf(
                    'ALTER TABLE dhl_products ADD CONSTRAINT %s '
                    . 'CHECK (replaced_by_code IS NULL OR replaced_by_code <> code)',
                    self::CONSTRAINT_NAME,
                )),
                'mysql', 'mariadb' => DB::statement(sprintf(
                    'ALTER TABLE dhl_products ADD CONSTRAINT %s '
                    . 'CHECK (replaced_by_code IS NULL OR replaced_by_code <> code)',
                    self::CONSTRAINT_NAME,
                )),
                'sqlite' => $this->applySqliteCheck(),
                default => Log::warning('dhl_products self-replacement CHECK skipped: unsupported driver', [
                    'driver' => $driver,
                ]),
            };
        } catch (\Throwable $e) {
            // Graceful skip — domain-level guard remains authoritative.
            Log::warning('dhl_products self-replacement CHECK could not be added', [
                'driver' => $driver,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('dhl_products')) {
            return;
        }

        $driver = DB::getDriverName();

        try {
            match ($driver) {
                'pgsql', 'mysql', 'mariadb' => DB::statement(sprintf(
                    'ALTER TABLE dhl_products DROP CONSTRAINT IF EXISTS %s',
                    self::CONSTRAINT_NAME,
                )),
                'sqlite' => null, // SQLite cannot drop a CHECK without table rebuild; left in place.
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning('dhl_products self-replacement CHECK could not be dropped', [
                'driver' => $driver,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * SQLite cannot ADD CONSTRAINT after table creation. We emulate the CHECK
     * with a BEFORE INSERT / BEFORE UPDATE trigger pair — same effect from the
     * application's point of view (write fails if invariant violated).
     */
    private function applySqliteCheck(): void
    {
        DB::statement(sprintf(
            'DROP TRIGGER IF EXISTS %s_insert',
            self::CONSTRAINT_NAME,
        ));
        DB::statement(sprintf(
            'DROP TRIGGER IF EXISTS %s_update',
            self::CONSTRAINT_NAME,
        ));
        DB::statement(sprintf(<<<'SQL'
            CREATE TRIGGER %s_insert
            BEFORE INSERT ON dhl_products
            FOR EACH ROW
            WHEN NEW.replaced_by_code IS NOT NULL AND NEW.replaced_by_code = NEW.code
            BEGIN
                SELECT RAISE(ABORT, 'dhl_products.replaced_by_code must not equal code');
            END
        SQL, self::CONSTRAINT_NAME));
        DB::statement(sprintf(<<<'SQL'
            CREATE TRIGGER %s_update
            BEFORE UPDATE ON dhl_products
            FOR EACH ROW
            WHEN NEW.replaced_by_code IS NOT NULL AND NEW.replaced_by_code = NEW.code
            BEGIN
                SELECT RAISE(ABORT, 'dhl_products.replaced_by_code must not equal code');
            END
        SQL, self::CONSTRAINT_NAME));
    }
};
