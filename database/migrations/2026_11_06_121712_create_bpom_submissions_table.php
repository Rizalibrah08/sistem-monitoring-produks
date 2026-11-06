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
        Schema::create('bpom_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->string('phase', 20);
            $table->date('submitted_on');
            $table->unsignedBigInteger('pnbp');
            $table->string('status', 20);
            $table->date('result_on')->nullable();
            $table->string('result_path')->nullable();
            $table->string('result_original_name')->nullable();
            $table->date('registration_deadline')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['ticket_id', 'phase', 'status']);
        });

        Schema::create('bpom_submission_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bpom_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_document_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_version_id')->constrained()->restrictOnDelete();

            $table->unique(['bpom_submission_id', 'ticket_document_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bpom_submission_documents');
        Schema::dropIfExists('bpom_submissions');
    }
};
