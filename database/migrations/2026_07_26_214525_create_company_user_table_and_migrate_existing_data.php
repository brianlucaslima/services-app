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
        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('role');
            $table->decimal('hourly_rate', 8, 2)->default(0.00);
            $table->timestamps();
        });

        // Migrate existing user data to the pivot table
        $users = DB::table('users')->whereNotNull('company_id')->get();
        foreach ($users as $user) {
            DB::table('company_user')->insert([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'role' => $user->role ?? 'collaborator',
                'hourly_rate' => $user->hourly_rate ?? 0.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Drop the redundant fields on users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['hourly_rate']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('hourly_rate', 8, 2)->default(0.00);
        });

        // Migrate back from pivot to users
        $pivots = DB::table('company_user')->get();
        foreach ($pivots as $pivot) {
            DB::table('users')->where('id', $pivot->user_id)->update([
                'hourly_rate' => $pivot->hourly_rate,
            ]);
        }

        Schema::dropIfExists('company_user');
    }
};
