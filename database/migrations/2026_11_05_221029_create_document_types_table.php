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
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('phase', 20);
            $table->string('scope', 20);
            $table->string('requirement', 20);
            $table->string('pic_role', 20);
            $table->string('verifier_role', 20)->nullable();
            $table->boolean('requires_expiry_date')->default(false);
            $table->unsignedSmallInteger('max_age_months')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
