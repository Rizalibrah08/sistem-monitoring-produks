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
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 100)->unique();
            $table->string('phase', 20);
            $table->unsignedSmallInteger('sort_order');
            $table->unsignedSmallInteger('sla_days')->nullable();
            $table->boolean('waits_for_external')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['sort_order', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stages');
    }
};
