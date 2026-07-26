<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceAddress;
use App\Models\ServiceInstance;
use App\Models\ServiceSchedule;
use App\Models\User;
use Livewire\Livewire;

test('agenda can be rendered and displays scheduled services', function () {
    // 1. Setup user, company, customer, address, and schedule
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
        'name' => 'Al Pacino',
        'email' => 'al@pacino.com',
    ]);

    $address = ServiceAddress::create([
        'customer_id' => $customer->id,
        'label' => 'Main Office',
        'address' => '123 Business Rd',
        'is_active' => true,
        'type' => 'office',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'start_date' => now()->startOfWeek()->format('Y-m-d'),
    ]);

    // Create a schedule on a specific day of this week (let's say Monday)
    $monday = now()->startOfWeek();
    $schedule = ServiceSchedule::create([
        'service_address_id' => $address->id,
        'recurrence_type' => 'weekly',
        'start_date' => $monday->format('Y-m-d'),
        'start_time' => '10:00:00',
        'is_active' => true,
        'days_of_week' => [1],
    ]);

    $collab = User::factory()->create([
        'company_id' => $company->id,
    ]);
    $schedule->users()->sync([$collab->id]);

    // 2. Act as user and test Livewire
    $this->actingAs($user);

    Livewire::test('agenda')
        ->set('viewDate', $monday->format('Y-m-d'))
        ->assertSee('Al Pacino')
        ->assertSee('Main Office');
});

test('agenda allows marking service as completed with notes', function () {
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

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Al Pacino',
        'email' => 'al@pacino.com',
    ]);

    $address = ServiceAddress::create([
        'customer_id' => $customer->id,
        'label' => 'Main Office',
        'address' => '123 Business Rd',
        'is_active' => true,
        'type' => 'office',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'start_date' => now()->startOfWeek()->format('Y-m-d'),
    ]);

    $monday = now()->startOfWeek();
    $schedule = ServiceSchedule::create([
        'service_address_id' => $address->id,
        'recurrence_type' => 'weekly',
        'start_date' => $monday->format('Y-m-d'),
        'start_time' => '10:00:00',
        'is_active' => true,
    ]);

    // 2. Act as user and test completion modal
    $this->actingAs($user);

    Livewire::test('agenda')
        ->set('viewDate', $monday->format('Y-m-d'))
        ->call('openCompletion', $schedule->id, $monday->format('Y-m-d'))
        ->assertSet('selectedScheduleId', $schedule->id)
        ->assertSet('selectedOriginalDate', $monday->format('Y-m-d'))
        ->set('notes', 'Completed office cleaning beautifully')
        ->call('saveCompletion')
        ->assertHasNoErrors();

    // 3. Verify that the completed ServiceInstance was saved with our workflow
    $instance = ServiceInstance::where('service_schedule_id', $schedule->id)
        ->whereDate('original_date', $monday->format('Y-m-d'))
        ->first();

    expect($instance)->not->toBeNull()
        ->and($instance->status)->toBe('completed')
        ->and($instance->notes)->toBe('Completed office cleaning beautifully');
});

test('agenda allows rescheduling a service', function () {
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

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Al Pacino',
        'email' => 'al@pacino.com',
    ]);

    $address = ServiceAddress::create([
        'customer_id' => $customer->id,
        'label' => 'Main Office',
        'address' => '123 Business Rd',
        'is_active' => true,
        'type' => 'office',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'start_date' => now()->startOfWeek()->format('Y-m-d'),
    ]);

    $monday = now()->startOfWeek();
    $schedule = ServiceSchedule::create([
        'service_address_id' => $address->id,
        'recurrence_type' => 'weekly',
        'start_date' => $monday->format('Y-m-d'),
        'start_time' => '10:00:00',
        'is_active' => true,
    ]);

    // 2. Act as user and test rescheduling
    $this->actingAs($user);

    $nextDay = $monday->copy()->addDay(); // Tuesday

    Livewire::test('agenda')
        ->set('viewDate', $monday->format('Y-m-d'))
        ->call('openReschedule', $schedule->id, $monday->format('Y-m-d'), '10:00:00')
        ->assertSet('selectedScheduleId', $schedule->id)
        ->set('rescheduleMode', 'move')
        ->set('newDate', $nextDay->format('Y-m-d'))
        ->set('newTime', '14:00:00')
        ->call('saveReschedule')
        ->assertHasNoErrors();

    // 3. Verify that the rescheduled ServiceInstance was saved on the new date
    $instance = ServiceInstance::where('service_schedule_id', $schedule->id)
        ->whereDate('original_date', $monday->format('Y-m-d'))
        ->first();

    expect($instance)->not->toBeNull()
        ->and($instance->status)->toBe('scheduled')
        ->and($instance->date->format('Y-m-d'))->toBe($nextDay->format('Y-m-d'))
        ->and($instance->time)->toBe('14:00:00');
});

test('agenda allows skipping a service occurrence', function () {
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

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Al Pacino',
        'email' => 'al@pacino.com',
    ]);

    $address = ServiceAddress::create([
        'customer_id' => $customer->id,
        'label' => 'Main Office',
        'address' => '123 Business Rd',
        'is_active' => true,
        'type' => 'office',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'start_date' => now()->startOfWeek()->format('Y-m-d'),
    ]);

    $monday = now()->startOfWeek();
    $schedule = ServiceSchedule::create([
        'service_address_id' => $address->id,
        'recurrence_type' => 'weekly',
        'start_date' => $monday->format('Y-m-d'),
        'start_time' => '10:00:00',
        'is_active' => true,
    ]);

    // 2. Act as user and test skipping
    $this->actingAs($user);

    Livewire::test('agenda')
        ->set('viewDate', $monday->format('Y-m-d'))
        ->call('skipOccurrence', $schedule->id, $monday->format('Y-m-d'))
        ->assertHasNoErrors();

    // 3. Verify that the skipped ServiceInstance was saved in the database
    $instance = ServiceInstance::where('service_schedule_id', $schedule->id)
        ->whereDate('original_date', $monday->format('Y-m-d'))
        ->first();

    expect($instance)->not->toBeNull()
        ->and($instance->status)->toBe('skipped');
});
