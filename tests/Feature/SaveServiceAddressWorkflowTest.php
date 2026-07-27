<?php

use App\Brain\Customers\Workflows\SaveServiceAddressWorkflow;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceAddress;
use App\Models\ServiceType;
use App\Models\User;

test('save service address workflow creates address, saves schedules, and syncs team', function () {
    // 1. Setup data
    $user = User::factory()->create();
    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Test Company',
        'email' => 'test@example.com',
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

    $scheduleData = [
        'id' => null,
        'service_type_id' => $type->id,
        'description' => 'Test Schedule',
        'recurrence_type' => 'weekly',
        'days_of_week' => [1],
        'day_of_month' => null,
        'start_date' => now()->format('Y-m-d'),
        'start_time' => '09:00:00',
        'user_ids' => [$collab->id],
    ];

    $officeCalendar = $company->calendars()->where('slug', 'office')->first();

    // 2. Run the workflow
    $payload = SaveServiceAddressWorkflow::run([
        'customerId' => $customer->id,
        'addressId' => null,
        'label' => 'HQ Office',
        'address' => '456 Business St',
        'city' => 'London',
        'zipCode' => 'EC1A 1BB',
        'startDate' => now()->format('Y-m-d'),
        'endDate' => null,
        'durationHours' => 4.00,
        'hourlyRate' => 22.50,
        'calendarId' => $officeCalendar->id,
        'schedules' => [$scheduleData],
    ]);

    // 3. Assert address was created with correct values
    expect($payload->resolvedAddressId)->not->toBeNull();

    $address = ServiceAddress::with('schedules.users')->find($payload->resolvedAddressId);
    expect($address)->not->toBeNull()
        ->and($address->label)->toBe('HQ Office')
        ->and($address->address)->toBe('456 Business St')
        ->and($address->city)->toBe('London')
        ->and($address->zip_code)->toBe('EC1A 1BB')
        ->and($address->duration_hours)->toBe('4.00')
        ->and($address->hourly_rate)->toBe('22.50')
        ->and($address->calendar_id)->toBe($officeCalendar->id)
        ->and($address->type)->toBe('office');

    // 4. Assert schedule was created and team synced
    expect($address->schedules->count())->toBe(1);

    $schedule = $address->schedules->first();
    expect($schedule->description)->toBe('Test Schedule')
        ->and($schedule->recurrence_type)->toBe('weekly')
        ->and($schedule->users->count())->toBe(1)
        ->and($schedule->users->first()->id)->toBe($collab->id);
});
