<?php

use App\Models\Company;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create([
        'role' => 'management',
    ]);
    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Test Company',
        'email' => 'test@example.com',
        'subscription_status' => 'active',
        'subscription_ends_at' => now()->addYear(),
    ]);
    $user->update(['company_id' => $company->id]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});
