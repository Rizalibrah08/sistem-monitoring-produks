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
        Schema::create('ticket_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_version_id')->constrained()->restrictOnDelete();
            $table->string('trigger', 30);
            $table->string('message', 255);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['ticket_id', 'document_version_id', 'trigger']);
            $table->index(['ticket_id', 'resolved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_reviews');
    }
};
