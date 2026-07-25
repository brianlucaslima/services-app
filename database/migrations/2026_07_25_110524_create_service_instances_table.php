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
        Schema::create('service_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_schedule_id')->constrained()->cascadeOnDelete();
            $table->date('original_date');
            $table->date('date');
            $table->time('time');
            $table->string('status')->default('scheduled'); // scheduled, skipped, completed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['service_schedule_id', 'original_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_instances');
    }
};
