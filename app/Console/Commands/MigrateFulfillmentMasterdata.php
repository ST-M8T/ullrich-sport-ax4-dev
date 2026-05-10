<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateFulfillmentMasterdata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fulfillment:migrate-masterdata {--dry-run : Preview the migration without persisting any data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrates source master data into the new fulfillment schema';

    private array $summary = [
        'packaging' => ['processed' => 0, 'created' => 0, 'updated' => 0],
        'assembly_options' => ['processed' => 0, 'created' => 0, 'updated' => 0],
        'variations' => ['processed' => 0, 'created' => 0, 'updated' => 0],
        'senders' => ['processed' => 0, 'created' => 0, 'updated' => 0],
        'sender_rules' => ['processed' => 0, 'created' => 0, 'updated' => 0],
        'freight_profiles' => ['processed' => 0, 'created' => 0, 'updated' => 0],
        'warnings' => [],
    ];

    private bool $dryRun = false;

    private array $packagingNameToId = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        if ($this->dryRun) {
            $this->info('Running in dry-run mode – no data will be written.');
        }

        if ($this->dryRun) {
            $this->migrate();
        } else {
            DB::connection()->transaction(function () {
                $this->migrate();
            });
        }

        $this->renderSummary();

        if (! empty($this->summary['warnings'])) {
            $this->warn('Warnings encountered during migration:');
            foreach ($this->summary['warnings'] as $warning) {
                $this->line('- '.$warning);
            }
        }

        return Command::SUCCESS;
    }

    private function migrate(): void
    {
        // Erstelle Lookup für Typ -> Name aus Source-Daten
        $sourceTypToName = DB::connection('ax4_source')->table('tischmasse')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->typ => $row->name ?: 'Typ '.$row->typ])
            ->toArray();

        $packagingMap = $this->migratePackaging();
        $assemblyMap = $this->migrateAssemblyOptions($packagingMap);
        $this->migrateVariationProfiles($packagingMap, $assemblyMap, $sourceTypToName);
        $senderMap = $this->migrateSenderProfiles();
        $this->migrateSenderRules($senderMap);
        $this->migrateFreightProfiles();
    }

    /**
     * @return array<string,int> source packaging code => fulfillment_packaging_profiles.id
     */
    private function migratePackaging(): array
    {
        $sourcePackaging = DB::connection('ax4_source')->table('tischmasse')->orderBy('typ')->get();
        $palletRules = DB::connection('ax4_source')->table('paletten_kapazitaet')->get()->keyBy('typ');

        $mapping = [];
        $nameToIdMapping = []; // package_name => id

        foreach ($sourcePackaging as $row) {
            $sourceCode = (string) $row->typ;
            $name = $row->name ?: 'Typ '.$sourceCode;
            $dimensions = [
                'length_mm' => $this->centimeterToMillimeter($row->laenge),
                'width_mm' => $this->centimeterToMillimeter($row->breite),
                'height_mm' => $this->centimeterToMillimeter($row->hoehe),
            ];

            $rule = $palletRules->get($sourceCode);

            $payload = array_merge([
                'package_name' => $name,
                'packaging_code' => $sourceCode,
                'truck_slot_units' => $rule ? max(1, (int) $rule->stellplaetze) : max(1, (int) ($row->stellplaetze ?? 1)),
                'max_units_per_pallet_same_recipient' => $rule ? max(1, (int) $rule->anzahl) : 1,
                'max_units_per_pallet_mixed_recipient' => 1,
                'max_stackable_pallets_same_recipient' => 1,
                'max_stackable_pallets_mixed_recipient' => $rule ? max(1, (int) $rule->on_top_per_base + 1) : 1,
                'notes' => $this->buildPalletNotes($rule),
            ], $dimensions);

            $this->summary['packaging']['processed']++;

            $id = null;
            if ($this->dryRun) {
                $mapping[$sourceCode] = -1; // placeholder
                $nameToIdMapping[$name] = -1; // placeholder

                continue;
            }

            $existing = DB::table('fulfillment_packaging_profiles')
                ->where('packaging_code', $sourceCode)
                ->first();

            if ($existing) {
                DB::table('fulfillment_packaging_profiles')
                    ->where('id', $existing->id)
                    ->update($payload);

                $this->summary['packaging']['updated']++;
                $id = (int) $existing->id;
            } else {
                $id = DB::table('fulfillment_packaging_profiles')->insertGetId($payload + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->summary['packaging']['created']++;
            }

            $mapping[$sourceCode] = $id;
            $nameToIdMapping[$name] = $id;
        }

        // Speichere das Name-Mapping für späteren Zugriff in Variations
        $this->packagingNameToId = $nameToIdMapping;

        return $mapping;
    }

    /**
     * @param  array<string,int>  $packagingMap
     * @return array<int,int> assembly_item_id => fulfillment_assembly_options.id
     */
    private function migrateAssemblyOptions(array $packagingMap): array
    {
        $assemblyRows = DB::connection('ax4_source')->table('tische')
            ->select([
                'item_id',
                'typ',
                'name',
                'vormontage_artikel_id',
                'vormontage_typ',
                'gewicht_vormontiert',
            ])
            ->whereNotNull('vormontage_artikel_id')
            ->where('vormontage_artikel_id', '!=', '')
            ->get();

        $mapping = [];

        foreach ($assemblyRows as $row) {
            $assemblyItemId = (int) $row->vormontage_artikel_id;
            if ($assemblyItemId <= 0) {
                continue;
            }

            $this->summary['assembly_options']['processed']++;

            $sourcePackagingCode = (string) ($row->vormontage_typ ?: $row->typ ?? '');
            $packagingId = $packagingMap[$sourcePackagingCode] ?? null;

            if (! $packagingId || $packagingId === -1) {
                $this->summary['warnings'][] = sprintf(
                    'Assembly for item %d references packaging type "%s" which was not migrated.',
                    $row->item_id,
                    $sourcePackagingCode
                );

                continue;
            }

            $payload = [
                'assembly_item_id' => $assemblyItemId,
                'assembly_packaging_id' => $packagingId,
                'assembly_weight_kg' => $this->normalizeWeight($row->gewicht_vormontiert),
                'description' => $row->name ? ('Vormontage '.$row->name) : null,
                'updated_at' => now(),
            ];

            if ($this->dryRun) {
                $mapping[$assemblyItemId] = -1;

                continue;
            }

            $existing = DB::table('fulfillment_assembly_options')
                ->where('assembly_item_id', $assemblyItemId)
                ->first();

            if ($existing) {
                DB::table('fulfillment_assembly_options')
                    ->where('id', $existing->id)
                    ->update($payload);
                $this->summary['assembly_options']['updated']++;
                $mapping[$assemblyItemId] = (int) $existing->id;
            } else {
                $id = DB::table('fulfillment_assembly_options')->insertGetId($payload + [
                    'created_at' => now(),
                ]);
                $this->summary['assembly_options']['created']++;
                $mapping[$assemblyItemId] = $id;
            }
        }

        return $mapping;
    }

    /**
     * @param  array<string,int>  $packagingMap
     * @param  array<int,int>  $assemblyMap
     * @param  array<string,string>  $sourceTypToName  Typ => Name Mapping
     */
    private function migrateVariationProfiles(array $packagingMap, array $assemblyMap, array $sourceTypToName): void
    {
        $variationRows = DB::connection('ax4_source')->table('tische')->get();
        $variationIds = DB::connection('ax4_source')->table('variations')
            ->select('item_id', 'variation_id')
            ->get()
            ->groupBy('item_id')
            ->map(fn ($rows) => $rows->pluck('variation_id')->all());

        foreach ($variationRows as $row) {
            $ids = $variationIds->get($row->item_id, [null]);

            foreach ($ids as $variationId) {
                $this->summary['variations']['processed']++;
                $mode = (string) ($row->assembly_moeglichkeiten ?? '');
                $defaultState = $this->mapDefaultState($mode);

                [$packagingCode, $weight] = $this->resolveDefaultPackaging($row, $defaultState);

                // Versuche zuerst über Code zu finden
                $packagingId = $packagingMap[$packagingCode] ?? null;

                // Falls nicht gefunden, versuche über Typ->Name->ID zu mappen
                if ((! $packagingId || $packagingId === -1) && isset($sourceTypToName[$packagingCode])) {
                    $packageName = $sourceTypToName[$packagingCode];
                    $packagingId = $this->packagingNameToId[$packageName] ?? null;
                }

                if (! $packagingId || $packagingId === -1) {
                    $this->summary['warnings'][] = sprintf(
                        'Variation item %d could not resolve packaging type "%s".',
                        $row->item_id,
                        $packagingCode
                    );

                    continue;
                }

                $assemblyItemId = (int) ($row->vormontage_artikel_id ?: 0);
                $assemblyOptionId = $assemblyItemId > 0 ? ($assemblyMap[$assemblyItemId] ?? null) : null;

                $payload = [
                    'item_id' => (int) $row->item_id,
                    'variation_id' => $variationId ? (int) $variationId : null,
                    'variation_name' => $row->name ?? null,
                    'default_state' => $defaultState,
                    'default_packaging_id' => $packagingId,
                    'default_weight_kg' => $weight,
                    'assembly_option_id' => $assemblyOptionId,
                    'updated_at' => now(),
                ];

                if ($this->dryRun) {
                    continue;
                }

                $query = DB::table('fulfillment_variation_profiles')
                    ->where('item_id', $payload['item_id']);

                if ($payload['variation_id'] !== null) {
                    $query->where('variation_id', $payload['variation_id']);
                } else {
                    $query->whereNull('variation_id');
                }

                $existing = $query->first();

                if ($existing) {
                    DB::table('fulfillment_variation_profiles')
                        ->where('id', $existing->id)
                        ->update($payload);
                    $this->summary['variations']['updated']++;
                } else {
                    DB::table('fulfillment_variation_profiles')->insert($payload + [
                        'created_at' => now(),
                    ]);
                    $this->summary['variations']['created']++;
                }
            }
        }
    }

    /**
     * @return array<string,int> sender code => profile id
     */
    private function migrateSenderProfiles(): array
    {
        $rows = DB::connection('ax4_source')->table('neutrals')->get();
        $mapping = [];

        foreach ($rows as $row) {
            $this->summary['senders']['processed']++;

            $streetParts = $this->splitStreet((string) ($row->f_street ?? ''));

            $payload = [
                'sender_code' => $row->code,
                'display_name' => $row->label ?? $row->code,
                'company_name' => $row->c_company ?? $row->label ?? $row->code,
                'contact_person' => $row->d_contact ?? null,
                'email' => null,
                'phone' => null,
                'street_name' => $streetParts['street'] ?? '',
                'street_number' => $streetParts['number'] ?? null,
                'address_addition' => $row->e_additional ?? null,
                'postal_code' => $row->h_postal_code ?? '',
                'city' => $row->i_city ?? '',
                'country_iso2' => Str::upper((string) ($row->g_country_iso2 ?? 'DE')),
                'updated_at' => now(),
            ];

            if ($payload['street_name'] === '') {
                $this->summary['warnings'][] = sprintf(
                    'Sender "%s" has no street information – please review manually.',
                    $row->code
                );
            }

            if ($this->dryRun) {
                $mapping[$row->code] = -1;

                continue;
            }

            $existing = DB::table('fulfillment_sender_profiles')
                ->where('sender_code', $row->code)
                ->first();

            if ($existing) {
                DB::table('fulfillment_sender_profiles')
                    ->where('id', $existing->id)
                    ->update($payload);
                $this->summary['senders']['updated']++;
                $mapping[$row->code] = (int) $existing->id;
            } else {
                $id = DB::table('fulfillment_sender_profiles')->insertGetId($payload + [
                    'created_at' => now(),
                ]);
                $this->summary['senders']['created']++;
                $mapping[$row->code] = $id;
            }
        }

        return $mapping;
    }

    /**
     * @param  array<string,int>  $senderMap
     */
    private function migrateSenderRules(array $senderMap): void
    {
        $rows = DB::connection('ax4_source')->table('neutral_rules')->get();

        foreach ($rows as $row) {
            $this->summary['sender_rules']['processed']++;

            $senderId = $senderMap[$row->neutral_code] ?? null;
            if (! $senderId || $senderId === -1) {
                // Sender-Profil fehlt - erstelle es zuerst
                if ($this->dryRun) {
                    $senderId = -1;
                } else {
                    $senderId = $this->createMissingSenderProfile($row->neutral_code);
                    $this->summary['warnings'][] = sprintf(
                        'Created missing sender profile "%s" (ID: %d) for sender rule.',
                        $row->neutral_code,
                        $senderId
                    );
                }
            }

            $payload = [
                'priority' => (int) ($row->priority ?? 100),
                'rule_type' => $row->match_type ?? 'unknown',
                'match_value' => (string) ($row->match_value ?? ''),
                'target_sender_id' => $senderId,
                'is_active' => (bool) ($row->active ?? true),
                'description' => null,
                'updated_at' => now(),
            ];

            if ($this->dryRun) {
                continue;
            }

            $existing = DB::table('fulfillment_sender_rules')
                ->where('rule_type', $payload['rule_type'])
                ->where('match_value', $payload['match_value'])
                ->first();

            if ($existing) {
                DB::table('fulfillment_sender_rules')
                    ->where('id', $existing->id)
                    ->update($payload);
                $this->summary['sender_rules']['updated']++;
            } else {
                DB::table('fulfillment_sender_rules')->insert($payload + [
                    'created_at' => now(),
                ]);
                $this->summary['sender_rules']['created']++;
            }
        }
    }

    /**
     * Erstellt ein fehlendes Sender-Profil basierend auf dem Sender-Code.
     *
     * @return int Die ID des erstellten oder gefundenen Sender-Profils
     */
    private function createMissingSenderProfile(string $senderCode): int
    {
        // Prüfe, ob bereits ein Profil mit diesem Code existiert
        $existing = DB::table('fulfillment_sender_profiles')
            ->where('sender_code', $senderCode)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        // Erstelle ein Minimal-Profil
        $payload = [
            'sender_code' => $senderCode,
            'display_name' => $senderCode,
            'company_name' => $senderCode,
            'contact_person' => null,
            'email' => null,
            'phone' => null,
            'street_name' => '',
            'street_number' => null,
            'address_addition' => null,
            'postal_code' => '',
            'city' => '',
            'country_iso2' => 'DE',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table('fulfillment_sender_profiles')->insertGetId($payload);
        $this->summary['senders']['created']++;

        return $id;
    }

    private function migrateFreightProfiles(): void
    {
        $rows = DB::connection('ax4_source')->table('shipping_methods')->get();

        foreach ($rows as $row) {
            $this->summary['freight_profiles']['processed']++;

            if ($this->dryRun) {
                continue;
            }

            $exists = DB::table('fulfillment_freight_profiles')
                ->where('shipping_profile_id', $row->id)
                ->exists();

            if ($exists) {
                DB::table('fulfillment_freight_profiles')
                    ->where('shipping_profile_id', $row->id)
                    ->update([
                        'label' => $row->label,
                        'created_at' => DB::raw('created_at'), // keep original timestamp
                    ]);
                $this->summary['freight_profiles']['updated']++;
            } else {
                DB::table('fulfillment_freight_profiles')->insert([
                    'shipping_profile_id' => $row->id,
                    'label' => $row->label,
                    'created_at' => now(),
                ]);
                $this->summary['freight_profiles']['created']++;
            }
        }
    }

    private function renderSummary(): void
    {
        $sections = Arr::except($this->summary, ['warnings']);
        $this->info('');
        $this->info('Fulfillment masterdata migration summary:');

        foreach ($sections as $section => $data) {
            $this->line(sprintf(
                '- %s: processed %d, created %d, updated %d',
                Str::title(str_replace('_', ' ', $section)),
                $data['processed'],
                $data['created'],
                $data['updated']
            ));
        }
    }

    private function centimeterToMillimeter($value): int
    {
        $intValue = (int) round((float) $value);

        return max(0, $intValue * 10);
    }

    private function normalizeWeight($value): ?float
    {
        if ($value === null) {
            return null;
        }

        $numeric = (float) $value;
        if ($numeric <= 0) {
            return null;
        }

        return round($numeric, 2);
    }

    /**
     * @param  object|null  $rule
     */
    private function buildPalletNotes($rule): ?string
    {
        if (! $rule) {
            return null;
        }

        $parts = [];
        if (! empty($rule->stack_group)) {
            $parts[] = 'Stack group: '.$rule->stack_group;
        }
        if (! empty($rule->is_base)) {
            $parts[] = 'Base palette';
        }
        if (isset($rule->per_slot)) {
            $parts[] = 'Per slot: '.(int) $rule->per_slot;
        }

        return empty($parts) ? null : implode(' · ', $parts);
    }

    private function splitStreet(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return ['street' => '', 'number' => null];
        }

        if (preg_match('/^(.+?)\s+(\d[\w\-\/]*)$/u', $value, $matches)) {
            return [
                'street' => trim($matches[1]),
                'number' => trim($matches[2]),
            ];
        }

        return ['street' => $value, 'number' => null];
    }

    private function mapDefaultState(string $mode): string
    {
        return match ($mode) {
            'standard_vormontiert', 'nur_vormontiert' => 'assembled',
            default => 'kit',
        };
    }

    /**
     * Determine default packaging code and weight for a variation.
     *
     * @return array{0:string,1:?float}
     */
    private function resolveDefaultPackaging(object $row, string $defaultState): array
    {
        if ($defaultState === 'assembled') {
            $code = (string) ($row->vormontage_typ ?: $row->typ ?? '');
            $weight = $this->normalizeWeight($row->gewicht_vormontiert);

            return [$code, $weight];
        }

        $code = (string) ($row->bausatz_typ ?: $row->typ ?? '');
        $weight = $this->normalizeWeight($row->gewicht_bausatz);

        return [$code, $weight];
    }
}
