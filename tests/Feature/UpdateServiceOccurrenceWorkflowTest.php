<?php

use App\Brain\Agenda\Workflows\UpdateServiceOccurrenceWorkflow;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceAddress;
use App\Models\ServiceInstance;
use App\Models\ServiceSchedule;
use App\Models\User;

test('update service occurrence workflow creates completed service instance and syncs team', function () {
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

    $address = ServiceAddress::create([
        'customer_id' => $customer->id,
        'label' => 'Main Office',
        'address' => '123 Business Rd',
        'is_active' => true,
        'type' => 'office',
        'duration_hours' => 3.00,
        'hourly_rate' => 20.00,
    ]);

    $schedule = ServiceSchedule::create([
        'service_address_id' => $address->id,
        'recurrence_type' => 'weekly',
        'start_date' => now()->format('Y-m-d'),
        'start_time' => '10:00:00',
        'is_active' => true,
    ]);

    $collab = User::factory()->create([
        'company_id' => $company->id,
    ]);
    $schedule->users()->sync([$collab->id]);

    // 2. Run the workflow to complete the service
    $payload = UpdateServiceOccurrenceWorkflow::run([
        'scheduleId' => $schedule->id,
        'originalDate' => now()->format('Y-m-d'),
        'companyId' => $company->id,
        'status' => 'completed',
        'notes' => 'Finished successfully!',
    ]);

    // 3. Assert instance was created with correct values
    expect($payload->instanceId)->not->toBeNull();

    $instance = ServiceInstance::with('users')->find($payload->instanceId);
    expect($instance)->not->toBeNull()
        ->and($instance->service_schedule_id)->toBe($schedule->id)
        ->and($instance->status)->toBe('completed')
        ->and($instance->notes)->toBe('Finished successfully!')
        ->and($instance->duration_hours)->toBe('3.00')
        ->and($instance->hourly_rate)->toBe('20.00');

    // 4. Assert team members were successfully synced
    expect($instance->users->count())->toBe(1)
        ->and($instance->users->first()->id)->toBe($collab->id);
});
