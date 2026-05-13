<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `dhl_additional_services` table.
 *
 * Stores DHL additional service definitions with JSON parameter schema
 * (Draft 2020-12 subset, see Domain\…\Catalog\ValueObjects\JsonSchema).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dhl_additional_services', function (Blueprint $table): void {
            $table->string('code', 8)->primary();
            $table->string('name', 200);
            $table->text('description')->default('');
            // Enum: pickup | delivery | notification | dangerous_goods | special
            $table->string('category', 24);
            // JSON-Schema (Draft 2020-12 whitelisted subset)
            $table->json('parameter_schema');
            $table->timestamp('deprecated_at')->nullable();
            // Enum: seed | api | manual
            $table->string('source', 8);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('deprecated_at', 'idx_dhl_services_deprecated_at');
            $table->index('category', 'idx_dhl_services_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dhl_additional_services');
    }
};
