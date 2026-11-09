<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('library_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('playtime_minutes')->default(0);
            $table->unsignedInteger('playtime_2weeks_minutes')->nullable();
            $table->timestamp('last_played_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'game_id']);
            $table->index(['user_id', 'last_played_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('library_entries');
    }
};
