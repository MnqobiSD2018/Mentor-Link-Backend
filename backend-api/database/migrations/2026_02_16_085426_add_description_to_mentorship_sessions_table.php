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
            $table->text('description')->nullable()->after('topic');
        });

        // Note: SQLite doesn't enforce ENUM constraints, so 'rejected' status 
        // can be used directly. For MySQL, you would need:
        // DB::statement("ALTER TABLE mentorship_sessions MODIFY COLUMN status ENUM('pending', 'confirmed', 'completed', 'cancelled', 'rejected') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mentorship_sessions', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
