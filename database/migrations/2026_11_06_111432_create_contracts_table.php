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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained()->restrictOnDelete();
            $table->string('number', 30)->unique();
            $table->unsignedBigInteger('service_value');
            $table->unsignedTinyInteger('dp_percent');
            $table->unsignedSmallInteger('payment_due_days');
            $table->string('status', 20);
            $table->foreignId('document_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('template_version_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('word_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->string('generation_error')->nullable();
            $table->string('signed_path')->nullable();
            $table->string('signed_original_name')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
