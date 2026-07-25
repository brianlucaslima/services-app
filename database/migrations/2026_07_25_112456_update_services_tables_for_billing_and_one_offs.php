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
        Schema::table('service_schedules', function (Blueprint $table) {
            $table->string('description')->nullable();
        });

        Schema::table('service_instances', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('service_address_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->decimal('duration_hours', 8, 2)->default(0);
            $table->decimal('hourly_rate', 8, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_schedules', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::table('service_instances', function (Blueprint $table) {
            $table->dropColumn(['customer_id', 'service_address_id', 'description', 'duration_hours', 'hourly_rate']);
        });
    }
};
