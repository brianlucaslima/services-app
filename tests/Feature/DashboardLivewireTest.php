<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceAddress;
use App\Models\ServiceInstance;
use App\Models\User;
use Livewire\Livewire;

test('dashboard component displays management metrics and lists for management role', function () {
    // 1. Create management user and company
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
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'is_active' => true,
    ]);

    $address = ServiceAddress::create([
        'customer_id' => $customer->id,
        'label' => 'Main Office',
        'address' => '123 Business Rd',
        'is_active' => true,
        'type' => 'office',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
    ]);

    $collab = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'collaborator',
        'hourly_rate' => 15.00,
    ]);

    // Create a completed service instance
    $instance = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'service_address_id' => $address->id,
        'description' => 'Office Cleaning',
        'date' => now(),
        'time' => '10:00:00',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'status' => 'completed',
        'payout_status' => 'unpaid',
    ]);
    $instance->users()->sync([$collab->id]);

    // 2. Act as management user and test Livewire component
    $this->actingAs($user);

    Livewire::test('dashboard')
        ->assertSee(__('Monthly Revenue'))
        ->assertSee(__('Pending Payouts'))
        ->assertSee(__('Active Customers'))
        ->assertSee(__('Completed Services'))
        ->assertSee('John Doe')
        ->assertSee('Main Office')
        ->assertSee(__('Top Collaborators'))
        ->assertSee($collab->name);
});

test('dashboard component displays collaborator metrics and lists for collaborator role', function () {
    // 1. Create collaborator user and company
    $owner = User::factory()->create();
    $company = Company::create([
        'user_id' => $owner->id,
        'name' => 'Invoease HQ',
        'email' => 'hq@invoease.co.uk',
    ]);

    $owner->update(['company_id' => $company->id]);

    $collab = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'collaborator',
        'hourly_rate' => 15.00,
    ]);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'is_active' => true,
    ]);

    $address = ServiceAddress::create([
        'customer_id' => $customer->id,
        'label' => 'Main Office',
        'address' => '123 Business Rd',
        'is_active' => true,
        'type' => 'office',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
    ]);

    // Create a completed service instance
    $instance = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'service_address_id' => $address->id,
        'description' => 'Office Cleaning',
        'date' => now(),
        'time' => '10:00:00',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'status' => 'completed',
        'payout_status' => 'unpaid',
    ]);
    $instance->users()->sync([$collab->id]);

    // 2. Act as collaborator user and test Livewire component
    $this->actingAs($collab);

    Livewire::test('dashboard')
        ->assertSee(__('Your Hours Work'))
        ->assertSee(__('Your Earnings'))
        ->assertSee(__('Your Pending Payout'))
        ->assertSee(__('Your Schedules'))
        ->assertSee('Main Office')
        ->assertDontSee(__('Monthly Revenue'))
        ->assertDontSee(__('Top Collaborators'));
});
