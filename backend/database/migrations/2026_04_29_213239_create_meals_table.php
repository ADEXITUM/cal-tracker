<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('day_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dish_id')->nullable()->nullOnDelete()->constrained();
            $table->enum('slot', ['breakfast', 'lunch', 'snack', 'dinner', 'other']);
            $table->timestampTz('eaten_at');
            // Either dish+grams or ad-hoc name
            $table->decimal('grams', 6, 1)->nullable();
            $table->string('name', 120)->nullable();
            // Snapshot КБЖУ — always filled
            $table->decimal('kcal', 7, 2);
            $table->decimal('protein_g', 6, 2);
            $table->decimal('fat_g', 6, 2);
            $table->decimal('carbs_g', 6, 2);
            $table->timestamps();

            $table->index('user_id');
            $table->index('day_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};
