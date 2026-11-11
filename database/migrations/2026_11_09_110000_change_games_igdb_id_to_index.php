<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IGDB can map several Steam appids to one game, so igdb_id is looked up often but not unique.
     * See docs/decisions.md.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropUnique(['igdb_id']);
            $table->index('igdb_id');
        });
    }

    /**
     * Fails if two games already share an igdb_id: those rows must be resolved by hand first.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['igdb_id']);
            $table->unique('igdb_id');
        });
    }
};
