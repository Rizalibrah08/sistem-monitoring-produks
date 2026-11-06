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
        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('attempt');
            $table->string('laboratory', 150);
            $table->date('sent_on');
            $table->date('estimated_on');
            $table->date('result_on')->nullable();
            $table->boolean('conforms')->nullable();
            $table->text('note')->nullable();
            $table->string('result_path')->nullable();
            $table->string('result_original_name')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['ticket_id', 'attempt']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_tests');
    }
};
