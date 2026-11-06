<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda pengingat yang sudah dikirim agar scheduler per jam tidak mengirim ulang (FR-36, FR-89).
     */
    public function up(): void
    {
        Schema::table('ticket_stage_logs', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('delay_cause');
        });

        Schema::table('bpom_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('reminded_days')->nullable()->after('responded_at');
        });

        Schema::table('bpom_submissions', function (Blueprint $table) {
            $table->unsignedTinyInteger('reminded_days')->nullable()->after('registration_deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_stage_logs', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });

        Schema::table('bpom_requests', function (Blueprint $table) {
            $table->dropColumn('reminded_days');
        });

        Schema::table('bpom_submissions', function (Blueprint $table) {
            $table->dropColumn('reminded_days');
        });
    }
};
