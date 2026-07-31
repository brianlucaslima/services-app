<?php

use App\Brain\Queries\GetDashboardMetricsQuery;
use App\Brain\Queries\GetTopCollaboratorsQuery;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceAddress;
use App\Models\ServiceInstance;
use App\Models\User;

test('dashboard queries return correct role based metrics and top collaborators list', function () {
    // 1. Setup data
    $user = User::factory()->create([
        'role' => 'management',
    ]);
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
        'hourly_rate_house' => 15.00,
        'hourly_rate_office' => 15.00,
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

    // 2. Test GetDashboardMetricsQuery for Manager
    $managerMetrics = GetDashboardMetricsQuery::run(
        companyId: $company->id,
        userId: $user->id,
        role: 'management'
    );

    expect($managerMetrics['activeCustomers'])->toBe(1)
        ->and($managerMetrics['completedServices'])->toBe(1)
        ->and($managerMetrics['pendingPayout'])->toBe(30.00); // 2 hours * 15.00/h = 30.00

    // 3. Test GetDashboardMetricsQuery for Collaborator
    $collabMetrics = GetDashboardMetricsQuery::run(
        companyId: $company->id,
        userId: $collab->id,
        role: 'collaborator'
    );

    expect($collabMetrics['completedHours'])->toBe(2.00)
        ->and($collabMetrics['earningsThisMonth'])->toBe(30.00)
        ->and($collabMetrics['pendingPayout'])->toBe(30.00);

    // 4. Test GetTopCollaboratorsQuery
    $topCollabs = GetTopCollaboratorsQuery::run(
        companyId: $company->id
    );

    expect(count($topCollabs))->toBeGreaterThanOrEqual(1);

    $firstCollab = $topCollabs[0];
    expect($firstCollab['user']->id)->toBe($collab->id)
        ->and($firstCollab['hours'])->toBe(2.00)
        ->and($firstCollab['payout'])->toBe(30.00);
});

test('dashboard metrics correctly excludes unit-based services from payouts and earnings', function () {
    // 1. Setup company, user, customer, unit address and collaborator
    $user = User::factory()->create([
        'role' => 'management',
    ]);
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
        'is_active' => true,
    ]);

    $address = ServiceAddress::create([
        'customer_id' => $customer->id,
        'label' => 'Main Office',
        'address' => '123 Business Rd',
        'is_active' => true,
        'type' => 'office',
        'duration_hours' => 5.00, // 5 units
        'hourly_rate' => 20.00,
        'billing_type' => 'unit',
    ]);

    $collab = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'collaborator',
        'hourly_rate_house' => 15.00,
        'hourly_rate_office' => 15.00,
    ]);

    // Create a completed unit-based service instance
    $instance = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'service_address_id' => $address->id,
        'description' => 'Office Cleaning',
        'date' => now(),
        'time' => '10:00:00',
        'duration_hours' => 5.00,
        'hourly_rate' => 20.00,
        'status' => 'completed',
        'payout_status' => 'unpaid',
        'billing_type' => 'unit',
    ]);
    $instance->users()->sync([$collab->id]);

    // 2. Test GetDashboardMetricsQuery for Manager
    $managerMetrics = GetDashboardMetricsQuery::run(
        companyId: $company->id,
        userId: $user->id,
        role: 'management'
    );

    expect($managerMetrics['pendingPayout'])->toEqual(0); // Excluded!

    // 3. Test GetDashboardMetricsQuery for Collaborator
    $collabMetrics = GetDashboardMetricsQuery::run(
        companyId: $company->id,
        userId: $collab->id,
        role: 'collaborator'
    );

    expect($collabMetrics['completedHours'])->toEqual(0) // Excluded!
        ->and($collabMetrics['earningsThisMonth'])->toEqual(0) // Excluded!
        ->and($collabMetrics['pendingPayout'])->toEqual(0); // Excluded!
});
