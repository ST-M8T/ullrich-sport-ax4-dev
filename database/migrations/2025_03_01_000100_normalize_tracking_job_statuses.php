<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tracking_jobs')
            ->where('status', 'pending')
            ->update(['status' => 'scheduled']);

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE tracking_jobs MODIFY status VARCHAR(32) DEFAULT 'scheduled'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE tracking_jobs MODIFY status VARCHAR(32) DEFAULT 'pending'");
        }
    }
};
