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
        Schema::table('ticket_documents', function (Blueprint $table) {
            $table->foreignId('material_id')->nullable()->after('document_type_id')->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_id');
        });
    }
};
