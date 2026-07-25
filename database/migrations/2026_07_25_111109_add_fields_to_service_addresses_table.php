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
        Schema::table('service_addresses', function (Blueprint $table) {
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('duration_hours', 8, 2)->default(0);
            $table->decimal('hourly_rate', 8, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_addresses', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date', 'duration_hours', 'hourly_rate']);
        });
    }
};
