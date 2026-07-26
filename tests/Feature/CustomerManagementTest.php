<?php

use App\Models\Company;
use App\Models\Customer as CustomerModel;
use App\Models\User;
use Livewire\Livewire;

test('a customer can be created with the required fields', function () {
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

    Livewire::test('customer-form')
        ->set('name', 'Maria Silva')
        ->set('phone', '11999998888')
        ->set('email', 'maria@example.com')
        ->call('save');

    $customer = CustomerModel::latest()->first();

    expect($customer)->not->toBeNull()
        ->and($customer->name)->toBe('Maria Silva')
        ->and($customer->phone)->toBe('11999998888')
        ->and($customer->email)->toBe('maria@example.com');
});

test('created customers are listed in the component view', function () {
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

    CustomerModel::factory()->create([
        'company_id' => $company->id,
        'name' => 'João Pereira',
        'phone' => '1188887777',
        'email' => 'joao@example.com',
    ]);

    Livewire::test('customer')
        ->assertSee('João Pereira')
        ->assertSee('1188887777')
        ->assertSee('joao@example.com');
});

test('name is required to create a customer', function () {
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

    Livewire::test('customer-form')
        ->set('name', '')
        ->set('phone', '11999998888')
        ->set('email', 'maria@example.com')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});
