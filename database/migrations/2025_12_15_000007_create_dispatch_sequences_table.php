<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_sequences', function (Blueprint $table): void {
            $table->string('sequence_name')->primary();
            $table->unsignedBigInteger('next_id')->default(1);
            $table->timestamps();
        });

        $now = Carbon::now();

        $list_max = Schema::hasTable('dispatch_lists') ? (int) (DB::table('dispatch_lists')->max('id') ?? 0) : 0;
        $scan_max = Schema::hasTable('dispatch_scans') ? (int) (DB::table('dispatch_scans')->max('id') ?? 0) : 0;

        $list_sequence_max = Schema::hasTable('dispatch_list_sequences') ? (int) (DB::table('dispatch_list_sequences')->max('id') ?? 0) : 0;
        $scan_sequence_max = Schema::hasTable('dispatch_scan_sequences') ? (int) (DB::table('dispatch_scan_sequences')->max('id') ?? 0) : 0;

        DB::table('dispatch_sequences')->insert([
            [
                'sequence_name' => 'dispatch_lists',
                'next_id' => max($list_max, $list_sequence_max) + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'sequence_name' => 'dispatch_scans',
                'next_id' => max($scan_max, $scan_sequence_max) + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::dropIfExists('dispatch_scan_sequences');
        Schema::dropIfExists('dispatch_list_sequences');
    }

    public function down(): void
    {
        $sequences = Schema::hasTable('dispatch_sequences')
            ? DB::table('dispatch_sequences')->pluck('next_id', 'sequence_name')->toArray()
            : [];

        Schema::create('dispatch_list_sequences', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('dispatch_scan_sequences', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        $list_next = (int) ($sequences['dispatch_lists'] ?? 1);
        $scan_next = (int) ($sequences['dispatch_scans'] ?? 1);

        $now = Carbon::now();

        if ($list_next > 1) {
            DB::table('dispatch_list_sequences')->insert([
                'id' => $list_next - 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::statement(sprintf(
                'ALTER TABLE %s AUTO_INCREMENT = %d',
                DB::getTablePrefix().'dispatch_list_sequences',
                $list_next,
            ));
        }

        if ($scan_next > 1) {
            DB::table('dispatch_scan_sequences')->insert([
                'id' => $scan_next - 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::statement(sprintf(
                'ALTER TABLE %s AUTO_INCREMENT = %d',
                DB::getTablePrefix().'dispatch_scan_sequences',
                $scan_next,
            ));
        }

        Schema::dropIfExists('dispatch_sequences');
    }
};
