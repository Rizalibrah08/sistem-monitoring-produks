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
        Schema::create('priority_scores', function (Blueprint $table) {
            $table->id();
            $table->string('queue', 20);
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->decimal('c1', 10, 4);
            $table->decimal('c2', 10, 4);
            $table->decimal('c3', 10, 4);
            $table->decimal('c4', 10, 4);
            $table->decimal('score', 8, 6);
            $table->unsignedInteger('rank');
            $table->timestamp('computed_at');

            $table->unique(['queue', 'ticket_id']);
            $table->index(['queue', 'rank']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('priority_scores');
    }
};
