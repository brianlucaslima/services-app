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
            $table->string('billing_type')->default('hourly')->after('hourly_rate');
        });

        Schema::table('service_instances', function (Blueprint $table) {
            $table->string('billing_type')->default('hourly')->after('hourly_rate');
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->string('billing_type')->default('hourly')->after('unit_price');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('billing_type')->default('hourly')->after('unit_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_addresses', function (Blueprint $table) {
            $table->dropColumn('billing_type');
        });

        Schema::table('service_instances', function (Blueprint $table) {
            $table->dropColumn('billing_type');
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropColumn('billing_type');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('billing_type');
        });
    }
};
