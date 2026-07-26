<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Livewire\Livewire;

test('customers can be listed and active tab is default', function () {
    // 1. Setup user and company
    $user = User::factory()->create([
        'role' => 'management',
    ]);

    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Invoease HQ',
        'email' => 'hq@invoease.co.uk',
    ]);

    $user->update(['company_id' => $company->id]);

    $activeCustomer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Robert De Niro',
        'email' => 'robert@deniro.com',
        'is_active' => true,
    ]);

    $inactiveCustomer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Al Pacino',
        'email' => 'al@pacino.com',
        'is_active' => false,
    ]);

    // 2. Act as user and test Livewire
    $this->actingAs($user);

    Livewire::test('customer')
        ->assertSet('tab', 'active')
        ->assertSee('Robert De Niro')
        ->assertDontSee('Al Pacino');
});

test('customers tab switching displays correct list', function () {
    // 1. Setup user and company
    $user = User::factory()->create([
        'role' => 'management',
    ]);

    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Invoease HQ',
        'email' => 'hq@invoease.co.uk',
    ]);

    $user->update(['company_id' => $company->id]);

    $activeCustomer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Robert De Niro',
        'email' => 'robert@deniro.com',
        'is_active' => true,
    ]);

    $inactiveCustomer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Al Pacino',
        'email' => 'al@pacino.com',
        'is_active' => false,
    ]);

    // 2. Act as user and test Livewire
    $this->actingAs($user);

    Livewire::test('customer')
        ->set('tab', 'inactive')
        ->assertSee('Al Pacino')
        ->assertDontSee('Robert De Niro');
});

test('customers can be searched by name, email or phone', function () {
    // 1. Setup user and company
    $user = User::factory()->create([
        'role' => 'management',
    ]);

    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Invoease HQ',
        'email' => 'hq@invoease.co.uk',
    ]);

    $user->update(['company_id' => $company->id]);

    $customer1 = Customer::create([
        'company_id' => $company->id,
        'name' => 'Robert De Niro',
        'email' => 'robert@deniro.com',
        'phone' => '123456',
        'is_active' => true,
    ]);

    $customer2 = Customer::create([
        'company_id' => $company->id,
        'name' => 'Brad Pitt',
        'email' => 'brad@pitt.com',
        'phone' => '789012',
        'is_active' => true,
    ]);

    // 2. Act as user and test Livewire search
    $this->actingAs($user);

    Livewire::test('customer')
        ->set('search', 'Robert')
        ->assertSee('Robert De Niro')
        ->assertDontSee('Brad Pitt')
        ->set('search', 'brad@pitt.com')
        ->assertSee('Brad Pitt')
        ->assertDontSee('Robert De Niro')
        ->set('search', '789012')
        ->assertSee('Brad Pitt')
        ->assertDontSee('Robert De Niro');
});

test('customer active status can be toggled', function () {
    // 1. Setup user and company
    $user = User::factory()->create([
        'role' => 'management',
    ]);

    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Invoease HQ',
        'email' => 'hq@invoease.co.uk',
    ]);

    $user->update(['company_id' => $company->id]);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Robert De Niro',
        'email' => 'robert@deniro.com',
        'is_active' => true,
    ]);

    // 2. Act as user and test Livewire toggle
    $this->actingAs($user);

    Livewire::test('customer')
        ->assertSee('Robert De Niro')
        ->call('toggleStatus', $customer->id)
        ->assertDontSee('Robert De Niro'); // because it became inactive and tab is active!

    expect($customer->fresh()->is_active)->toBeFalsy();
});
