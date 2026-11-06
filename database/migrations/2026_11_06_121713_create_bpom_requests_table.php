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
        Schema::create('bpom_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('bpom_submission_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->text('note');
            $table->date('requested_on');
            $table->date('deadline');
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['bpom_submission_id', 'sequence']);
            $table->index(['responded_at', 'deadline']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bpom_requests');
    }
};
