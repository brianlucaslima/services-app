<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceAddress;
use App\Models\ServiceInstance;
use App\Models\User;
use Livewire\Livewire;

test('collaborator reports consolidated summary can be listed', function () {
    // 1. Setup company and users
    $user = User::factory()->create([
        'role' => 'management',
    ]);
    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Invoease HQ',
        'email' => 'hq@invoease.co.uk',
    ]);
    $user->update(['company_id' => $company->id]);
    $user->refresh();

    $collab = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'collaborator',
        'hourly_rate_house' => 15.00,
        'hourly_rate_office' => 15.00,
    ]);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $address = ServiceAddress::create([
        'customer_id' => $customer->id,
        'label' => 'Main Office',
        'address' => '123 Business Rd',
        'is_active' => true,
        'type' => 'office',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'start_date' => now()->format('Y-m-d'),
    ]);

    // Create a completed service instance
    $instance = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'service_address_id' => $address->id,
        'description' => 'Office Cleaning',
        'date' => now()->startOfWeek()->addDays(2)->format('Y-m-d'), // Wednesday (within range)
        'time' => '10:00:00',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'status' => 'completed',
        'payout_status' => 'unpaid',
    ]);
    $instance->users()->sync([$collab->id]);

    // 2. Act as user and test Livewire
    $this->actingAs($user);

    Livewire::test('collaborator-reports')
        ->assertSee($collab->name)
        ->assertSee('2.00h')
        ->assertSee('£30.00'); // 2 hours * 15.00/h = 30.00
});

test('collaborator reports loads detail services when a user is selected', function () {
    // 1. Setup data
    $user = User::factory()->create([
        'role' => 'management',
    ]);
    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Invoease HQ',
        'email' => 'hq@invoease.co.uk',
    ]);
    $user->update(['company_id' => $company->id]);
    $user->refresh();

    $collab = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'collaborator',
        'hourly_rate_house' => 15.00,
        'hourly_rate_office' => 15.00,
    ]);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $address = ServiceAddress::create([
        'customer_id' => $customer->id,
        'label' => 'Main Office',
        'address' => '123 Business Rd',
        'is_active' => true,
        'type' => 'office',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'start_date' => now()->format('Y-m-d'),
    ]);

    $instance = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'service_address_id' => $address->id,
        'description' => 'Office Cleaning Special',
        'date' => now()->startOfWeek()->addDays(2)->format('Y-m-d'), // Wednesday (within range)
        'time' => '10:00:00',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'status' => 'completed',
        'payout_status' => 'unpaid',
    ]);
    $instance->users()->sync([$collab->id]);

    // 2. Act as user and test Livewire detail view
    $this->actingAs($user);

    Livewire::test('collaborator-reports')
        ->call('selectCollaborator', $collab->id)
        ->assertSee('Office Cleaning Special')
        ->assertSee('£30.00');
});

test('collaborator reports allows marking selected unpaid payouts as paid', function () {
    // 1. Setup data
    $user = User::factory()->create([
        'role' => 'management',
    ]);
    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Invoease HQ',
        'email' => 'hq@invoease.co.uk',
    ]);
    $user->update(['company_id' => $company->id]);
    $user->refresh();

    $collab = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'collaborator',
        'hourly_rate_house' => 15.00,
        'hourly_rate_office' => 15.00,
    ]);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $address = ServiceAddress::create([
        'customer_id' => $customer->id,
        'label' => 'Main Office',
        'address' => '123 Business Rd',
        'is_active' => true,
        'type' => 'office',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'start_date' => now()->format('Y-m-d'),
    ]);

    $instance = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'service_address_id' => $address->id,
        'description' => 'Office Cleaning',
        'date' => now()->startOfWeek()->addDays(2)->format('Y-m-d'), // Wednesday (within range)
        'time' => '10:00:00',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'status' => 'completed',
        'payout_status' => 'unpaid',
    ]);
    $instance->users()->sync([$collab->id]);

    // 2. Act as user and test Livewire marking paid
    $this->actingAs($user);

    Livewire::test('collaborator-reports')
        ->call('selectCollaborator', $collab->id)
        ->set('selectedInstanceIds', [$instance->id])
        ->call('markSelectedAsPaid')
        ->assertHasNoErrors()
        ->assertSet('selectedInstanceIds', []);

    expect($instance->fresh()->payout_status)->toBe('paid')
        ->and($instance->fresh()->payout_date->format('Y-m-d'))->toBe(now()->format('Y-m-d'));
});
