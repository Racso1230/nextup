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
        Schema::create('pick_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('library_entry_id')->constrained()->cascadeOnDelete();
            // playing, not_tonight or never (App\Enums\FeedbackKind). Every click is a row; the latest counts.
            $table->string('kind', 20);
            // Rows are never edited, so there is no updated_at.
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'library_entry_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pick_feedback');
    }
};
