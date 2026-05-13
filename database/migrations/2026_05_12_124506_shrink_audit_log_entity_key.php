<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Spec alignment: `dhl_catalog_audit_log.entity_key` is varchar(64), not (128).
 *
 * Idempotent: re-running the migration is a no-op once the column is at the
 * target width. SQLite has no real ALTER COLUMN TYPE — but its TEXT-affinity
 * VARCHAR doesn't enforce the declared length anyway, so we simply skip on
 * SQLite with a debug log.
 *
 * Engineering-Handbuch §24 (Idempotenz).
 */
return new class extends Migration
{
    private const TABLE = 'dhl_catalog_audit_log';

    private const COLUMN = 'entity_key';

    private const TARGET_LENGTH = 64;

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $driver = DB::getDriverName();

        try {
            match ($driver) {
                'pgsql' => DB::statement(sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s TYPE varchar(%d)',
                    self::TABLE,
                    self::COLUMN,
                    self::TARGET_LENGTH,
                )),
                'mysql', 'mariadb' => Schema::table(self::TABLE, function (Blueprint $t): void {
                    $t->string(self::COLUMN, self::TARGET_LENGTH)->change();
                }),
                'sqlite' => Log::debug('entity_key shrink skipped on SQLite (no enforced length).'),
                default => Log::warning('entity_key shrink skipped: unsupported driver', [
                    'driver' => $driver,
                ]),
            };
        } catch (\Throwable $e) {
            Log::warning('entity_key shrink could not be applied', [
                'driver' => $driver,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $driver = DB::getDriverName();

        try {
            match ($driver) {
                'pgsql' => DB::statement(sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s TYPE varchar(128)',
                    self::TABLE,
                    self::COLUMN,
                )),
                'mysql', 'mariadb' => Schema::table(self::TABLE, function (Blueprint $t): void {
                    $t->string(self::COLUMN, 128)->change();
                }),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning('entity_key shrink rollback failed', [
                'driver' => $driver,
                'error' => $e->getMessage(),
            ]);
        }
    }
};
