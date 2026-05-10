<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('dispatch_scan_sequences');
        Schema::dropIfExists('dispatch_list_sequences');
    }

    public function down(): void
    {
        Schema::create('dispatch_list_sequences', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('dispatch_scan_sequences', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
    }
};
