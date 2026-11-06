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
        Schema::table('tickets', function (Blueprint $table) {
            $table->date('packaging_requested_on')->nullable()->after('status');
            $table->date('packaging_uploaded_on')->nullable()->after('packaging_requested_on');
            $table->string('nie_number', 50)->nullable()->unique()->after('packaging_uploaded_on');
            $table->date('nie_issued_on')->nullable()->after('nie_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['nie_number']);
            $table->dropColumn(['packaging_requested_on', 'packaging_uploaded_on', 'nie_number', 'nie_issued_on']);
        });
    }
};
