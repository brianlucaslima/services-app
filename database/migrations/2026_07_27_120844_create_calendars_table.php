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
        // 1. Create Calendars Table
        Schema::create('calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
        });

        // 2. Create Collaborator Calendar Rates Table
        Schema::create('collaborator_calendar_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calendar_id')->constrained()->cascadeOnDelete();
            $table->decimal('hourly_rate', 8, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'calendar_id'], 'user_calendar_rate_unique');
        });

        // 3. Add calendar_id foreign key to service_addresses
        Schema::table('service_addresses', function (Blueprint $table) {
            $table->foreignId('calendar_id')->nullable()->after('type')->constrained('calendars')->nullOnDelete();
        });

        // 4. Data Migration & Preservation
        $companies = DB::table('companies')->get();
        foreach ($companies as $company) {
            // Create default 'House' and 'Office' calendars for this company
            $houseCalendarId = DB::table('calendars')->insertGetId([
                'company_id' => $company->id,
                'name' => 'House',
                'slug' => 'house',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $officeCalendarId = DB::table('calendars')->insertGetId([
                'company_id' => $company->id,
                'name' => 'Office',
                'slug' => 'office',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Migrate service_addresses from text type to calendar_id relation
            // Match the old string 'house'/'office' to the new calendar entries
            DB::table('service_addresses')
                ->whereExists(function ($query) use ($company) {
                    $query->select(DB::raw(1))
                        ->from('customers')
                        ->whereColumn('customers.id', 'service_addresses.customer_id')
                        ->where('customers.company_id', $company->id);
                })
                ->where('type', 'house')
                ->update(['calendar_id' => $houseCalendarId]);

            DB::table('service_addresses')
                ->whereExists(function ($query) use ($company) {
                    $query->select(DB::raw(1))
                        ->from('customers')
                        ->whereColumn('customers.id', 'service_addresses.customer_id')
                        ->where('customers.company_id', $company->id);
                })
                ->where('type', 'office')
                ->update(['calendar_id' => $officeCalendarId]);

            // Migrate collaborator rates from company_user pivot table to collaborator_calendar_rates
            $companyUsers = DB::table('company_user')->where('company_id', $company->id)->get();
            foreach ($companyUsers as $cu) {
                // If they have house hourly rate configured, migrate it
                DB::table('collaborator_calendar_rates')->insert([
                    'company_id' => $company->id,
                    'user_id' => $cu->user_id,
                    'calendar_id' => $houseCalendarId,
                    'hourly_rate' => $cu->hourly_rate_house ?? $cu->hourly_rate ?? 0.00,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // If they have office hourly rate configured, migrate it
                DB::table('collaborator_calendar_rates')->insert([
                    'company_id' => $company->id,
                    'user_id' => $cu->user_id,
                    'calendar_id' => $officeCalendarId,
                    'hourly_rate' => $cu->hourly_rate_office ?? $cu->hourly_rate ?? 0.00,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_addresses', function (Blueprint $table) {
            $table->dropForeign(['calendar_id']);
            $table->dropColumn('calendar_id');
        });

        Schema::dropIfExists('collaborator_calendar_rates');
        Schema::dropIfExists('calendars');
    }
};
