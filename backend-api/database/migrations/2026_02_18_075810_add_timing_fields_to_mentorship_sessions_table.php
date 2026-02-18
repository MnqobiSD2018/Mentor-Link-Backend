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
        Schema::table('mentorship_sessions', function (Blueprint $table) {
            // Track when session actually started (for timer calculation)
            $table->timestamp('started_at')->nullable();
            
            // Track when session ended
            $table->timestamp('ended_at')->nullable();
            
            // Link session to a conversation (for chat sessions)
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mentorship_sessions', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropColumn(['started_at', 'ended_at', 'conversation_id']);
        });
    }
};
