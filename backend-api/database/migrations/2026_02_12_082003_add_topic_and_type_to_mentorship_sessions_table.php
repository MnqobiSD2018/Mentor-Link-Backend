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
            $table->string('topic')->nullable()->after('mentee_id');
            $table->enum('type', ['video', 'chat'])->default('video')->after('topic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mentorship_sessions', function (Blueprint $table) {
            $table->dropColumn(['topic', 'type']);
        });
    }
};
