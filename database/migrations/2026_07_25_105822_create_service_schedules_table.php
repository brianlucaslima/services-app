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
        Schema::create('service_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_address_id')->constrained()->cascadeOnDelete();
            $table->string('recurrence_type'); // once, weekly, fortnightly, monthly
            $table->json('days_of_week')->nullable(); // [1, 2, 3, 4, 5, 6, 0]
            $table->integer('day_of_month')->nullable(); // 1-31
            $table->date('start_date');
            $table->time('start_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_schedules');
    }
};
