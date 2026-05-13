<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PROJ-4 Migration B — data migration.
 *
 * Maps the legacy free-text `dhl_product_id` column onto the new catalog FK
 * column `dhl_product_code` and rewrites `dhl_default_service_codes`
 * (string-array) into the typed `dhl_default_service_parameters` JSON shape.
 *
 * Engineering-Handbuch:
 *   §10–§13 — DB facade only, no Eloquent.
 *   §15    — purely structural transformation, no domain validation here.
 *   §24    — idempotent: re-running the migration produces the identical
 *            end state thanks to the unique key on `profile_id` in the
 *            report table and the "skip when already migrated" guard.
 *   §27    — chunked, per-record fault tolerance, fail-fast precondition.
 *   §67    — explicit precondition check (empty catalog → hard fail).
 */
return new class extends Migration
{
    private const CHUNK_SIZE = 1000;

    public function up(): void
    {
        // §67 Fail-Fast: the catalog must be populated before this migration
        // can produce meaningful matches. We refuse to run on an empty catalog
        // *unless* there is also no profile data to migrate — that way fresh
        // installations (CI / `migrate:fresh` without seeding) stay green.
        $catalogEmpty = ! Schema::hasTable('dhl_products') || DB::table('dhl_products')->count() === 0;
        $hasProfilesWithDhlCode = DB::table('fulfillment_freight_profiles')
            ->whereNotNull('dhl_product_id')
            ->where('dhl_product_id', '<>', '')
            ->exists();

        if ($catalogEmpty && $hasProfilesWithDhlCode) {
            throw new \RuntimeException(
                'PROJ-4 data migration aborted: table `dhl_products` is empty but '
                . 'freight profiles reference DHL product codes. '
                . 'Bitte zuerst `php artisan dhl:catalog:bootstrap` oder den '
                . '`DhlCatalogSeeder` ausführen und die Migration anschließend erneut starten.'
            );
        }

        if ($catalogEmpty) {
            // Nothing to map — early return keeps fresh installs working.
            return;
        }

        // Build an in-memory index of catalog codes once (case-sensitive after
        // normalisation, which uppercases everything). At >100k codes this
        // would need a different strategy — current catalog is < 1k codes.
        $catalogCodes = DB::table('dhl_products')->pluck('code')->all();
        $catalogIndex = [];
        foreach ($catalogCodes as $code) {
            $catalogIndex[strtoupper((string) $code)] = (string) $code;
        }

        $now = now();

        DB::table('fulfillment_freight_profiles')
            ->orderBy('shipping_profile_id')
            ->chunkById(self::CHUNK_SIZE, function ($profiles) use ($catalogIndex, $now): void {
                foreach ($profiles as $profile) {
                    $this->migrateProfile($profile, $catalogIndex, $now);
                }
            }, 'shipping_profile_id');
    }

    public function down(): void
    {
        // §17/§27: data-migration rollbacks are best-effort. We clear the new
        // columns and report rows so the schema-level rollback (Migration A)
        // can drop the columns cleanly.
        if (Schema::hasTable('fulfillment_freight_profiles')) {
            DB::table('fulfillment_freight_profiles')->update([
                'dhl_product_code' => null,
                'dhl_default_service_parameters' => null,
            ]);
        }

        if (Schema::hasTable('dhl_freight_profile_migration_report')) {
            DB::table('dhl_freight_profile_migration_report')->delete();
        }
    }

    /**
     * @param  object  $profile  row from `fulfillment_freight_profiles`
     * @param  array<string,string>  $catalogIndex  uppercase-code → canonical code
     */
    private function migrateProfile(object $profile, array $catalogIndex, \DateTimeInterface $now): void
    {
        $profileId = (int) $profile->shipping_profile_id;
        $original = $profile->dhl_product_id ?? null;

        // 1) Idempotency-skip: if a code is already set we still upsert the
        //    report (so re-runs converge) but skip the lookup work.
        $alreadyMigrated = ! empty($profile->dhl_product_code);

        [$status, $normalized, $matchedCode] = $this->resolveStatus(
            $original,
            $catalogIndex,
            $alreadyMigrated ? (string) $profile->dhl_product_code : null
        );

        $updates = [];

        if ($status === 'matched' && ! $alreadyMigrated) {
            $updates['dhl_product_code'] = $matchedCode;
        }

        // 2) Service-parameters transformation: only when target column is
        //    still empty (idempotent re-run safety).
        if (empty($profile->dhl_default_service_parameters) && ! empty($profile->dhl_default_service_codes)) {
            $rewritten = $this->rewriteServiceCodes($profile->dhl_default_service_codes);
            if ($rewritten !== null) {
                $updates['dhl_default_service_parameters'] = $rewritten;
            }
        }

        if ($updates !== []) {
            DB::table('fulfillment_freight_profiles')
                ->where('shipping_profile_id', $profileId)
                ->update($updates);
        }

        // 3) Report upsert (unique key on profile_id keeps re-runs idempotent).
        DB::table('dhl_freight_profile_migration_report')->upsert(
            [[
                'profile_id' => $profileId,
                'original_value' => $original !== null ? (string) $original : null,
                'normalized_value' => $normalized,
                'migration_status' => $status,
                'matched_code' => $matchedCode,
                'migrated_at' => $now,
            ]],
            ['profile_id'],
            ['original_value', 'normalized_value', 'migration_status', 'matched_code', 'migrated_at'],
        );
    }

    /**
     * @param  array<string,string>  $catalogIndex
     * @return array{0:string,1:?string,2:?string}  status, normalized, matchedCode
     */
    private function resolveStatus(?string $original, array $catalogIndex, ?string $existingCode): array
    {
        if ($existingCode !== null && $existingCode !== '') {
            // Already migrated — keep status `matched` so the report reflects
            // the final state on re-run.
            return ['matched', strtoupper((string) $original), $existingCode];
        }

        if ($original === null || trim($original) === '') {
            return ['already_null', null, null];
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', trim($original)) ?? '');

        if ($normalized === '') {
            return ['already_null', null, null];
        }

        if (isset($catalogIndex[$normalized])) {
            return ['matched', $normalized, $catalogIndex[$normalized]];
        }

        return ['unmatched', $normalized, null];
    }

    /**
     * Rewrites the legacy `["NOT","PIN"]` array shape into the new
     * `[{"code":"NOT","parameters":null}, …]` form. Returns the JSON-encoded
     * payload or null when nothing usable could be derived.
     */
    private function rewriteServiceCodes(mixed $raw): ?string
    {
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($decoded) || $decoded === []) {
            return null;
        }

        $payload = [];
        foreach ($decoded as $entry) {
            if (is_string($entry) && $entry !== '') {
                $payload[] = ['code' => $entry, 'parameters' => null];
                continue;
            }
            // Tolerate pre-existing structured rows so the migration is safe
            // to re-run after manual touch-ups.
            if (is_array($entry) && isset($entry['code']) && is_string($entry['code'])) {
                $payload[] = [
                    'code' => $entry['code'],
                    'parameters' => $entry['parameters'] ?? null,
                ];
            }
        }

        return $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
};
