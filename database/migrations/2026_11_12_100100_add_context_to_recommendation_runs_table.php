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
        Schema::table('recommendation_runs', function (Blueprint $table) {
            // The session context the run was ranked with ({hours, mood, company}); null on older runs.
            $table->json('context')->nullable()->after('candidate_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recommendation_runs', function (Blueprint $table) {
            $table->dropColumn('context');
        });
    }
};
