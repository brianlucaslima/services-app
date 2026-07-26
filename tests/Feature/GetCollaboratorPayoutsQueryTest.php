<?php

use App\Brain\Queries\GetCollaboratorPayoutsQuery;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceAddress;
use App\Models\ServiceInstance;
use App\Models\User;

test('get collaborator payouts query returns correct summary and detail reports', function () {
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

    $collab = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'collaborator',
        'hourly_rate' => 12.00,
    ]);

    // Create a completed service instance with collab assigned
    $instance = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'service_address_id' => $address->id,
        'description' => 'Regular Office Cleaning',
        'date' => now(),
        'time' => '10:00:00',
        'duration_hours' => 4.00,
        'hourly_rate' => 20.00,
        'status' => 'completed',
        'payout_status' => 'unpaid',
    ]);
    $instance->users()->sync([$collab->id]);

    // 2. Test Overview Query
    $overview = GetCollaboratorPayoutsQuery::run(
        companyId: $company->id,
        startDate: now()->subDays(1)->format('Y-m-d'),
        endDate: now()->addDays(1)->format('Y-m-d')
    );

    expect(count($overview))->toBeGreaterThanOrEqual(1);

    $collabOverview = collect($overview)->where('id', $collab->id)->first();
    expect($collabOverview)->not->toBeNull()
        ->and($collabOverview['hours'])->toBe(4.00)
        ->and($collabOverview['payout'])->toBe(48.00); // 4 hours * 12.00/h = 48.00

    // 3. Test Detail Query
    $detail = GetCollaboratorPayoutsQuery::run(
        companyId: $company->id,
        startDate: now()->subDays(1)->format('Y-m-d'),
        endDate: now()->addDays(1)->format('Y-m-d'),
        userId: $collab->id
    );

    expect(count($detail))->toBe(1);

    $firstDetail = $detail[0];
    expect($firstDetail['total_duration'])->toBe('4.00')
        ->and($firstDetail['share_hours'])->toBe(4.00)
        ->and($firstDetail['payout'])->toBe(48.00)
        ->and($firstDetail['payout_status'])->toBe('unpaid');
});
