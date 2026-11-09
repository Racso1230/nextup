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
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedInteger('igdb_id')->nullable()->unique();
            $table->string('slug')->nullable();
            $table->date('first_release_date')->nullable();
            $table->string('cover_image_id')->nullable();
            // Seconds. Null means unknown, never zero.
            $table->unsignedInteger('ttb_hastily')->nullable();
            $table->unsignedInteger('ttb_normally')->nullable();
            $table->unsignedInteger('ttb_completely')->nullable();
            $table->json('similar_igdb_ids')->nullable();
            $table->string('match_status')->default('pending');
            $table->unsignedInteger('match_attempts')->default(0);
            $table->timestamp('next_match_at')->nullable();
            $table->timestamp('enriched_at')->nullable();

            $table->index(['match_status', 'next_match_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['match_status', 'next_match_at']);
            $table->dropUnique(['igdb_id']);
            $table->dropColumn([
                'igdb_id',
                'slug',
                'first_release_date',
                'cover_image_id',
                'ttb_hastily',
                'ttb_normally',
                'ttb_completely',
                'similar_igdb_ids',
                'match_status',
                'match_attempts',
                'next_match_at',
                'enriched_at',
            ]);
        });
    }
};
