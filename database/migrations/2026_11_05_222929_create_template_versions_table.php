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
        Schema::create('template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_template_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size');
            $table->char('checksum', 64);
            $table->json('placeholders');
            $table->string('notes')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['document_template_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_versions');
    }
};
