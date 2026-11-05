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
        Schema::create('ticket_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->string('phase', 20);
            $table->string('requirement', 20);
            $table->foreignId('document_rule_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 30);
            $table->timestamps();

            $table->index(['ticket_id', 'phase']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_documents');
    }
};
