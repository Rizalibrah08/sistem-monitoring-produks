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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->string('number', 30)->unique();
            $table->string('term', 20);
            $table->unsignedTinyInteger('percent');
            $table->unsignedBigInteger('amount');
            $table->date('due_date');
            $table->string('status', 30);
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_type', 50)->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'term']);
            $table->index(['status', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
