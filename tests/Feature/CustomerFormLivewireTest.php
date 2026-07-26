<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Livewire\Livewire;

test('customer form can be visited in create mode', function () {
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

    // 2. Act as user and test Livewire
    $this->actingAs($user);

    Livewire::test('customer-form')
        ->assertSee(__('New customer'))
        ->assertSet('customerId', null);
});

test('customer form creates new customer with valid data', function () {
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

    // 2. Act as user and test Livewire creation
    $this->actingAs($user);

    Livewire::test('customer-form')
        ->set('name', 'Jack Nicholson')
        ->set('phone', '123456789')
        ->set('email', 'jack@nicholson.com')
        ->set('address', 'Hollywood Hills')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('customers'));

    $customer = Customer::where('company_id', $company->id)->where('name', 'Jack Nicholson')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->phone)->toBe('123456789')
        ->and($customer->email)->toBe('jack@nicholson.com')
        ->and($customer->address)->toBe('Hollywood Hills')
        ->and((bool) $customer->is_active)->toBeTrue();
});

test('customer form validates required and format rules', function () {
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

    // 2. Act as user and test Livewire validation
    $this->actingAs($user);

    // Required name
    Livewire::test('customer-form')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);

    // Invalid email format
    Livewire::test('customer-form')
        ->set('name', 'Valid Name')
        ->set('email', 'invalid-email-format')
        ->call('save')
        ->assertHasErrors(['email' => 'email']);
});

test('customer form can load existing customer and update details', function () {
    // 1. Setup user, company, and existing customer
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
        'name' => 'Dustin Hoffman',
        'email' => 'dustin@hoffman.com',
        'phone' => '99999',
        'address' => 'London St',
        'is_active' => true,
    ]);

    // 2. Act as user and test Livewire editing
    $this->actingAs($user);

    Livewire::test('customer-form', ['id' => $customer->id])
        ->assertSee(__('Edit customer'))
        ->assertSet('customerId', $customer->id)
        ->assertSet('name', 'Dustin Hoffman')
        ->assertSet('email', 'dustin@hoffman.com')
        ->set('name', 'Dustin Hoffman Jr.')
        ->set('phone', '88888')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('customers'));

    $customer = $customer->fresh();
    expect($customer->name)->toBe('Dustin Hoffman Jr.')
        ->and($customer->phone)->toBe('88888');
});
