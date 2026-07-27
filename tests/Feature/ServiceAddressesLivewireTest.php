<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceAddress;
use App\Models\ServiceSchedule;
use App\Models\ServiceType;
use App\Models\User;
use Livewire\Livewire;

test('service addresses can be listed for a customer', function () {
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
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $officeCalendar = $company->calendars()->where('slug', 'office')->first();

    $address = ServiceAddress::create([
        'customer_id' => $customer->id,
        'calendar_id' => $officeCalendar->id,
        'label' => 'Main Office',
        'address' => '123 Business Rd',
        'is_active' => true,
        'type' => 'office',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'start_date' => now()->format('Y-m-d'),
    ]);

    // 2. Act as user and test Livewire
    $this->actingAs($user);

    Livewire::test('service-addresses', ['id' => $customer->id])
        ->assertSee('Main Office')
        ->assertSee('123 Business Rd');
});

test('service address with recurring schedules can be created', function () {
    // 1. Setup user, company, customer, service type, and collaborator
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
    ]);

    $type = ServiceType::create([
        'company_id' => $company->id,
        'name' => 'General Cleaning',
    ]);

    $collab = User::factory()->create([
        'company_id' => $company->id,
    ]);

    // 2. Act as user and test Livewire creation
    $this->actingAs($user);

    $officeCalendar = $company->calendars()->where('slug', 'office')->first();

    Livewire::test('service-addresses', ['id' => $customer->id])
        ->call('openCreateModal')
        ->set('label', 'HQ Office')
        ->set('address', '456 Business St')
        ->set('city', 'London')
        ->set('zip_code', 'EC1A 1BB')
        ->set('start_date', now()->format('Y-m-d'))
        ->set('duration_hours', 4.00)
        ->set('hourly_rate', 22.50)
        ->set('calendar_id', $officeCalendar->id)
        // Add a nested schedule
        ->call('addSchedule')
        ->set('schedules.0.service_type_id', $type->id)
        ->set('schedules.0.description', 'Regular Office Clean')
        ->set('schedules.0.recurrence_type', 'weekly')
        ->set('schedules.0.start_date', now()->format('Y-m-d'))
        ->set('schedules.0.start_time', '09:00')
        ->set('schedules.0.user_ids', [$collab->id])
        ->call('save')
        ->assertHasNoErrors();

    $address = ServiceAddress::where('customer_id', $customer->id)->where('label', 'HQ Office')->first();
    expect($address)->not->toBeNull()
        ->and($address->calendar_id)->toBe($officeCalendar->id);

    $schedule = ServiceSchedule::where('service_address_id', $address->id)->first();
    expect($schedule)->not->toBeNull()
        ->and($schedule->description)->toBe('Regular Office Clean')
        ->and($schedule->recurrence_type)->toBe('weekly');

    expect($schedule->users->count())->toBe(1)
        ->and($schedule->users->first()->id)->toBe($collab->id);
});

test('service address form validates required fields', function () {
    // 1. Setup user, company, and customer
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
    ]);

    // 2. Act as user and test Livewire validation
    $this->actingAs($user);

    Livewire::test('service-addresses', ['id' => $customer->id])
        ->call('openCreateModal')
        ->set('label', '')
        ->set('address', '')
        ->call('save')
        ->assertHasErrors(['label' => 'required', 'address' => 'required']);
});

test('service address can be edited and deleted', function () {
    // 1. Setup user, company, customer, and existing address
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
    ]);

    $officeCalendar = $company->calendars()->where('slug', 'office')->first();

    $address = ServiceAddress::create([
        'customer_id' => $customer->id,
        'calendar_id' => $officeCalendar->id,
        'label' => 'Main Office',
        'address' => '123 Business Rd',
        'is_active' => true,
        'type' => 'office',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'start_date' => now()->format('Y-m-d'),
    ]);

    // 2. Act as user and test Livewire editing
    $this->actingAs($user);

    // Edit address
    Livewire::test('service-addresses', ['id' => $customer->id])
        ->call('openEditModal', $address->id)
        ->assertSet('addressId', $address->id)
        ->assertSet('label', 'Main Office')
        ->set('label', 'Main Office updated')
        ->call('save')
        ->assertHasNoErrors();

    expect($address->fresh()->label)->toBe('Main Office updated');

    // Delete address
    Livewire::test('service-addresses', ['id' => $customer->id])
        ->call('deleteAddress', $address->id)
        ->assertHasNoErrors()
        ->assertDontSee('Main Office updated');

    expect(ServiceAddress::find($address->id))->toBeNull();
});
