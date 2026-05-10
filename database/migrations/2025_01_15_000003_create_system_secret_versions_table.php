<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_secret_versions', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key');
            $table->unsignedInteger('version');
            $table->longText('encrypted_value')->nullable();
            $table->foreignId('rotated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('rotated_at');
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->unique(['setting_key', 'version']);
            $table->index('setting_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_secret_versions');
    }
};
