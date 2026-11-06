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
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->string('order_id', 50)->unique();
            $table->unsignedBigInteger('gross_amount');
            $table->string('status', 20);
            $table->string('snap_token', 100)->nullable();
            $table->string('redirect_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('transaction_status', 30)->nullable();
            $table->string('payment_type', 50)->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('last_notification')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
