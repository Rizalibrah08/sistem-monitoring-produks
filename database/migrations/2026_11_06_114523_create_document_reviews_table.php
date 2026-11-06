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
        Schema::create('document_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_document_id')->constrained()->restrictOnDelete();
            $table->string('level', 20);
            $table->string('decision', 20);
            $table->text('note')->nullable();
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['document_version_id', 'level', 'decision']);
            $table->index(['ticket_document_id', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_reviews');
    }
};
