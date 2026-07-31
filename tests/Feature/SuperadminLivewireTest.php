<?php

use App\Models\Company;
use App\Models\User;
use Livewire\Livewire;

test('only superadmin can render the superadmin dashboard', function () {
    $user = User::factory()->create([
        'role' => 'management',
    ]);

    $superadmin = User::factory()->create([
        'role' => 'superadmin',
    ]);

    $this->actingAs($user);
    $this->get(route('superadmin'))->assertStatus(403);

    $this->actingAs($superadmin);
    $this->get(route('superadmin'))->assertStatus(200);
});

test('superadmin can login as company owner and get redirected to the company dashboard', function () {
    $superadmin = User::factory()->create([
        'role' => 'superadmin',
    ]);

    $owner = User::factory()->create([
        'role' => 'management',
    ]);

    $company = Company::create([
        'user_id' => $owner->id,
        'name' => 'Target Company',
        'email' => 'target@company.com',
    ]);

    $owner->update(['company_id' => $company->id]);

    $this->actingAs($superadmin);

    Livewire::test('superadmin')
        ->call('loginAsCompanyOwner', $company->id)
        ->assertRedirect(route('dashboard'));

    // Assert that the active session is now logged in as the owner
    expect(auth()->id())->toBe($owner->id);
});
