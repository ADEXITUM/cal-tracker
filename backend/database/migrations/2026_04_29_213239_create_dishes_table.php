<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dishes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('kcal_per_100g', 6, 2);
            $table->decimal('protein_per_100g', 5, 2);
            $table->decimal('fat_per_100g', 5, 2);
            $table->decimal('carbs_per_100g', 5, 2);
            $table->integer('usage_count')->default(0);
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'usage_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dishes');
    }
};
