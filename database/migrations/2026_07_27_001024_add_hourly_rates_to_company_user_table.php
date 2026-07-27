<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->decimal('hourly_rate_house', 8, 2)->default(0.00)->after('hourly_rate');
            $table->decimal('hourly_rate_office', 8, 2)->default(0.00)->after('hourly_rate_house');
        });

        // Copy existing hourly_rate to hourly_rate_house and hourly_rate_office
        DB::table('company_user')->update([
            'hourly_rate_house' => DB::raw('hourly_rate'),
            'hourly_rate_office' => DB::raw('hourly_rate'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->dropColumn(['hourly_rate_house', 'hourly_rate_office']);
        });
    }
};
