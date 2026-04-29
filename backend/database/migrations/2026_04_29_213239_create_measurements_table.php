<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('day_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('measured_at');
            $table->decimal('weight_kg', 5, 2);
            $table->decimal('body_fat_pct', 4, 1)->nullable();
            $table->decimal('muscle_mass_kg', 5, 2)->nullable();
            $table->decimal('body_water_pct', 4, 1)->nullable();
            $table->smallInteger('visceral_fat_level')->nullable();
            $table->decimal('bone_mass_kg', 4, 2)->nullable();
            $table->decimal('protein_pct', 4, 1)->nullable();
            $table->smallInteger('heart_rate_bpm')->nullable();
            $table->string('source', 20)->default('manual');
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurements');
    }
};
