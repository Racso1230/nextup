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
        Schema::create('playtime_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_entry_id')->constrained()->cascadeOnDelete();
            $table->date('captured_on');
            $table->unsignedInteger('minutes_total');
            $table->timestamps();

            // One row per entry per day, so re-importing on the same day updates rather than adds.
            $table->unique(['library_entry_id', 'captured_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playtime_snapshots');
    }
};
