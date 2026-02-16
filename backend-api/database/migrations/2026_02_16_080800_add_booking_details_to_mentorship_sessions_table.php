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
            $table->date('date')->nullable()->after('mentee_id');
            $table->string('time')->nullable()->after('date');
            $table->decimal('price', 8, 2)->default(0)->after('duration');
            $table->string('meeting_link')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mentorship_sessions', function (Blueprint $table) {
            $table->dropColumn(['date', 'time', 'price', 'meeting_link']);
        });
    }
};
