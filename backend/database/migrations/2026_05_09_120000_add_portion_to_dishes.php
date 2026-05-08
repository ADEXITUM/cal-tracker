<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            $table->boolean('is_piece')->default(false);
            $table->decimal('piece_grams', 7, 2)->nullable();
            $table->string('piece_label', 24)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            $table->dropColumn(['is_piece', 'piece_grams', 'piece_label']);
        });
    }
};
