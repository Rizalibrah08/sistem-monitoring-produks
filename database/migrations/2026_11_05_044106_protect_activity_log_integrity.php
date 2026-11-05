<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->string('actor_name')->nullable();
            $table->string('actor_role', 32)->nullable();
            $table->string('source', 32)->default('system');
            $table->string('ticket_code')->nullable()->index();
            $table->text('reason')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->char('prev_hash', 64)->unique();
            $table->char('hash', 64)->unique();
            $table->index(['created_at', 'id']);
            $table->index(['event', 'created_at']);
        });

        Schema::create('audit_log_locks', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
        });

        DB::table('audit_log_locks')->insert(['id' => 1]);

        foreach (['update', 'delete'] as $operation) {
            $trigger = 'activity_log_prevent_'.$operation;

            if (DB::getDriverName() === 'sqlite') {
                DB::unprepared("CREATE TRIGGER {$trigger} BEFORE {$operation} ON activity_log BEGIN SELECT RAISE(ABORT, 'Audit log is append-only'); END");
            } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::unprepared("CREATE TRIGGER {$trigger} BEFORE {$operation} ON activity_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log is append-only'");
            } else {
                throw new RuntimeException('Audit append-only memerlukan MySQL, MariaDB, atau SQLite untuk pengujian.');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS activity_log_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS activity_log_prevent_delete');

        Schema::dropIfExists('audit_log_locks');

        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropIndex(['created_at', 'id']);
            $table->dropIndex(['event', 'created_at']);
            $table->dropIndex(['ticket_code']);
            $table->dropUnique(['prev_hash']);
            $table->dropUnique(['hash']);
            $table->dropColumn(['actor_name', 'actor_role', 'source', 'ticket_code', 'reason', 'ip_address', 'user_agent', 'prev_hash', 'hash']);
        });
    }
};
