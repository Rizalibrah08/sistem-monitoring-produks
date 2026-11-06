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
        Schema::table('ticket_stage_logs', function (Blueprint $table) {
            $table->timestamp('overdue_at')->nullable()->after('finished_at');
            $table->string('delay_cause', 10)->nullable()->after('overdue_at');

            $table->index(['finished_at', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_stage_logs', function (Blueprint $table) {
            $table->dropIndex(['finished_at', 'due_at']);
            $table->dropColumn(['overdue_at', 'delay_cause']);
        });
    }
};
