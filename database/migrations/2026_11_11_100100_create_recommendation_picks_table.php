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
        Schema::create('recommendation_picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recommendation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('library_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('rank');
            $table->double('score');
            $table->json('contributions');
            $table->text('explanation');

            $table->unique(['recommendation_run_id', 'rank']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendation_picks');
    }
};
