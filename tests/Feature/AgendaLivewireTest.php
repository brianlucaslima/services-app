<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
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

test('agenda allows editing completed service collaborators, duration and rate, and unmarking as completed', function () {
    // 1. Setup company, user, customer, address, and schedule
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

    $collab1 = User::factory()->create(['company_id' => $company->id]);
    $collab2 = User::factory()->create(['company_id' => $company->id]);
    $schedule->users()->sync([$collab1->id]);

    $this->actingAs($user);

    // 2. Open completion, customize collaborators, duration and rate, then complete
    Livewire::test('agenda')
        ->set('viewDate', $monday->format('Y-m-d'))
        ->call('openCompletion', $schedule->id, $monday->format('Y-m-d'))
        ->assertSet('completionHours', '02:00')
        ->assertSet('completionRate', 20.00)
        ->assertSet('completionUserIds', [$collab1->id])
        ->set('completionHours', '03:30')
        ->set('completionRate', 25.00)
        ->set('completionUserIds', [$collab2->id])
        ->set('notes', 'Modified execution details')
        ->call('saveCompletion')
        ->assertHasNoErrors();

    // 3. Verify that the completed ServiceInstance has updated values
    $instance = ServiceInstance::where('service_schedule_id', $schedule->id)
        ->whereDate('original_date', $monday->format('Y-m-d'))
        ->first();

    expect($instance)->not->toBeNull()
        ->and($instance->status)->toBe('completed')
        ->and((float) $instance->duration_hours)->toBe(3.50)
        ->and((float) $instance->hourly_rate)->toBe(25.00)
        ->and($instance->notes)->toBe('Modified execution details');

    expect($instance->users->pluck('id')->toArray())
        ->toContain($collab2->id)
        ->not->toContain($collab1->id);

    // 4. Open completion again to verify loaded data
    Livewire::test('agenda')
        ->set('viewDate', $monday->format('Y-m-d'))
        ->call('openCompletion', $schedule->id, $monday->format('Y-m-d'))
        ->assertSet('completionHours', '03:30')
        ->assertSet('completionRate', 25.00)
        ->assertSet('completionUserIds', [$collab2->id])
        ->assertSet('hasCompletedInstance', true)

        // 5. Unmark as completed
        ->call('uncompleteService')
        ->assertHasNoErrors();

    // 6. Verify that the ServiceInstance has been deleted from database
    $instance = ServiceInstance::where('service_schedule_id', $schedule->id)
        ->whereDate('original_date', $monday->format('Y-m-d'))
        ->first();

    expect($instance)->toBeNull();
});

test('agenda correctly excludes unit-based services from hours sum and supports direct unmarking', function () {
    // 1. Setup company, user, customer, and two addresses
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

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Al Pacino',
        'email' => 'al@pacino.com',
    ]);

    // Hourly address
    $addressHourly = ServiceAddress::create([
        'customer_id' => $customer->id,
        'label' => 'Hourly Office',
        'address' => '123 Business Rd',
        'is_active' => true,
        'type' => 'office',
        'duration_hours' => 2.50,
        'hourly_rate' => 20.00,
        'start_date' => now()->startOfWeek()->format('Y-m-d'),
        'billing_type' => 'hourly',
    ]);

    // Unit address
    $addressUnit = ServiceAddress::create([
        'customer_id' => $customer->id,
        'label' => 'Unit House',
        'address' => '456 Home St',
        'is_active' => true,
        'type' => 'house',
        'duration_hours' => 5.00, // 5 units
        'hourly_rate' => 50.00, // 50.00 per unit
        'start_date' => now()->startOfWeek()->format('Y-m-d'),
        'billing_type' => 'unit',
    ]);

    $monday = now()->startOfWeek();

    // Hourly schedule
    $scheduleHourly = ServiceSchedule::create([
        'service_address_id' => $addressHourly->id,
        'recurrence_type' => 'weekly',
        'start_date' => $monday->format('Y-m-d'),
        'start_time' => '10:00:00',
        'is_active' => true,
        'days_of_week' => [1],
    ]);

    // Unit schedule
    $scheduleUnit = ServiceSchedule::create([
        'service_address_id' => $addressUnit->id,
        'recurrence_type' => 'weekly',
        'start_date' => $monday->format('Y-m-d'),
        'start_time' => '14:00:00',
        'is_active' => true,
        'days_of_week' => [1],
    ]);

    $this->actingAs($user);

    // 2. Test Livewire component hours calculation
    Livewire::test('agenda')
        ->set('viewDate', $monday->format('Y-m-d'))
        ->assertSet('weeklyHours', 2.50) // ONLY the hourly service is counted (2.50h). Unit service (5 units) is excluded!
        ->assertHasNoErrors();

    // 3. Mark unit service completed first
    $instance = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'service_schedule_id' => $scheduleUnit->id,
        'service_address_id' => $addressUnit->id,
        'original_date' => $monday->format('Y-m-d'),
        'date' => $monday->format('Y-m-d'),
        'time' => '14:00:00',
        'duration_hours' => 5.00,
        'hourly_rate' => 50.00,
        'status' => 'completed',
        'billing_type' => 'unit',
    ]);

    expect(ServiceInstance::where('service_schedule_id', $scheduleUnit->id)->exists())->toBeTrue();

    // 4. Test directUncomplete action
    Livewire::test('agenda')
        ->set('viewDate', $monday->format('Y-m-d'))
        ->call('directUncomplete', $scheduleUnit->id, $monday->format('Y-m-d'))
        ->assertHasNoErrors();

    // 5. Verify it was deleted
    expect(ServiceInstance::where('service_schedule_id', $scheduleUnit->id)->exists())->toBeFalse();
});

test('agenda allows editing independent schedule-less manual services', function () {
    // 1. Setup company, user, customer, and address
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

    // Create a schedule-less service instance (manual work)
    $instance = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'service_address_id' => $address->id,
        'original_date' => $monday->format('Y-m-d'),
        'date' => $monday->format('Y-m-d'),
        'time' => '10:00:00',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'status' => 'completed',
        'billing_type' => 'hourly',
    ]);

    $this->actingAs($user);

    // 2. Test Livewire component can open, edit and save
    Livewire::test('agenda')
        ->set('viewDate', $monday->format('Y-m-d'))
        ->call('openCompletion', null, $monday->format('Y-m-d'), $instance->id)
        ->assertSet('completionHours', '02:00')
        ->assertSet('completionRate', 20.00)
        ->assertSet('hasCompletedInstance', true)
        ->set('completionHours', '04:00')
        ->set('notes', 'Edited manual service')
        ->call('saveCompletion')
        ->assertHasNoErrors();

    expect($instance->fresh()->duration_hours)->toBe('4.00')
        ->and($instance->fresh()->notes)->toBe('Edited manual service');

    // 3. Test directUncomplete works for independent instances
    Livewire::test('agenda')
        ->set('viewDate', $monday->format('Y-m-d'))
        ->call('directUncomplete', null, $monday->format('Y-m-d'), $instance->id)
        ->assertHasNoErrors();

    expect(ServiceInstance::find($instance->id))->toBeNull();
});

test('agenda correctly displays Billed badge for invoiced services', function () {
    // 1. Setup company, user, customer, address and instance
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

    $instance = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'service_address_id' => $address->id,
        'original_date' => $monday->format('Y-m-d'),
        'date' => $monday->format('Y-m-d'),
        'time' => '10:00:00',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'status' => 'completed',
        'billing_type' => 'hourly',
    ]);

    // Create an Invoice and link via InvoiceItem
    $invoice = Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'number' => '0001',
        'date' => now(),
        'status' => 'draft',
        'total_amount' => 40.00,
    ]);

    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'service_instance_id' => $instance->id,
        'description' => 'Cleaning Work',
        'quantity' => 2.00,
        'unit_price' => 20.00,
        'amount' => 40.00,
    ]);

    $this->actingAs($user);

    // 2. Assert component sees 'Billed' badge
    Livewire::test('agenda')
        ->set('viewDate', $monday->format('Y-m-d'))
        ->assertSee('Billed');
});
