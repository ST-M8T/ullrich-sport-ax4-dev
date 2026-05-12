<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_orders', function (Blueprint $table): void {
            // All columns nullable to keep legacy rows valid; the domain VO enforces
            // required-ness at booking time (Fail-Fast §67 in domain layer).
            $table->string('receiver_company_name', 50)->nullable()->after('contact_phone');
            $table->string('receiver_contact_name', 50)->nullable()->after('receiver_company_name');
            $table->string('receiver_street', 50)->nullable()->after('receiver_contact_name');
            $table->string('receiver_additional_address_info', 50)->nullable()->after('receiver_street');
            $table->string('receiver_postal_code', 10)->nullable()->after('receiver_additional_address_info');
            $table->string('receiver_city_name', 35)->nullable()->after('receiver_postal_code');
            $table->string('receiver_country_code', 2)->nullable()->after('receiver_city_name');
            $table->string('receiver_email', 254)->nullable()->after('receiver_country_code');
            $table->string('receiver_phone', 30)->nullable()->after('receiver_email');

            $table->index('receiver_country_code', 'shipment_orders_receiver_country_code_idx');
            $table->index('receiver_postal_code', 'shipment_orders_receiver_postal_code_idx');
        });

        $this->backfillFromMetadata();
    }

    public function down(): void
    {
        Schema::table('shipment_orders', function (Blueprint $table): void {
            $table->dropIndex('shipment_orders_receiver_country_code_idx');
            $table->dropIndex('shipment_orders_receiver_postal_code_idx');
            $table->dropColumn([
                'receiver_company_name',
                'receiver_contact_name',
                'receiver_street',
                'receiver_additional_address_info',
                'receiver_postal_code',
                'receiver_city_name',
                'receiver_country_code',
                'receiver_email',
                'receiver_phone',
            ]);
        });
    }

    /**
     * Idempotent backfill: copies legacy metadata['receiver'] into the new columns
     * for rows where the structured columns are still empty. Safe to re-run.
     */
    private function backfillFromMetadata(): void
    {
        DB::table('shipment_orders')
            ->whereNull('receiver_street')
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $metadata = is_string($row->metadata) ? json_decode($row->metadata, true) : null;
                    if (! is_array($metadata)) {
                        continue;
                    }
                    $receiver = $metadata['receiver'] ?? null;
                    if (! is_array($receiver)) {
                        continue;
                    }

                    $street = trim(implode(' ', array_filter([
                        isset($receiver['streetName']) ? (string) $receiver['streetName'] : null,
                        isset($receiver['streetNumber']) ? (string) $receiver['streetNumber'] : null,
                    ], static fn ($v): bool => $v !== null && $v !== '')));

                    $postalCode = isset($receiver['postalCode']) ? trim((string) $receiver['postalCode']) : '';
                    $city = isset($receiver['city']) ? trim((string) $receiver['city']) : '';
                    $country = isset($receiver['countryIso2']) ? strtoupper(trim((string) $receiver['countryIso2'])) : '';

                    // Only backfill when the minimum spec-required quartet is present.
                    if ($street === '' || $postalCode === '' || $city === '' || strlen($country) !== 2) {
                        continue;
                    }

                    DB::table('shipment_orders')
                        ->where('id', $row->id)
                        ->whereNull('receiver_street')
                        ->update([
                            'receiver_company_name' => self::truncOrNull($receiver['companyName'] ?? null, 50),
                            'receiver_contact_name' => self::truncOrNull($receiver['contactPerson'] ?? null, 50),
                            'receiver_street' => mb_substr($street, 0, 50),
                            'receiver_additional_address_info' => self::truncOrNull($receiver['additionalAddressInfo'] ?? null, 50),
                            'receiver_postal_code' => mb_substr($postalCode, 0, 10),
                            'receiver_city_name' => mb_substr($city, 0, 35),
                            'receiver_country_code' => $country,
                            'receiver_email' => self::truncOrNull($receiver['email'] ?? null, 254),
                            'receiver_phone' => self::truncOrNull($receiver['phone'] ?? null, 30),
                        ]);
                }
            });
    }

    private static function truncOrNull(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $str = trim((string) $value);

        return $str === '' ? null : mb_substr($str, 0, $max);
    }
};
