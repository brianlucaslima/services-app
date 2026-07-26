<?php

use App\Brain\Services\Workflows\SaveServiceTypeWorkflow;
use App\Models\Company;
use App\Models\ServiceType;
use App\Models\User;

test('save service type workflow registers new service type under company', function () {
    // 1. Setup company
    $owner = User::factory()->create();
    $company = Company::create([
        'user_id' => $owner->id,
        'name' => 'Test Company',
        'email' => 'test@example.com',
    ]);

    $owner->update(['company_id' => $company->id]);

    // 2. Run the workflow to register a new service type
    $payload = SaveServiceTypeWorkflow::run([
        'companyId' => $company->id,
        'editingId' => null,
        'name' => 'Deep Cleaning',
    ]);

    // 3. Assert service type was created with correct values
    expect($payload->resolvedTypeId)->not->toBeNull();

    $type = ServiceType::find($payload->resolvedTypeId);
    expect($type)->not->toBeNull()
        ->and($type->company_id)->toBe($company->id)
        ->and($type->name)->toBe('Deep Cleaning');
});

test('save service type workflow updates existing service type', function () {
    // 1. Setup company and service type
    $owner = User::factory()->create();
    $company = Company::create([
        'user_id' => $owner->id,
        'name' => 'Test Company',
        'email' => 'test@example.com',
    ]);

    $owner->update(['company_id' => $company->id]);

    $type = ServiceType::create([
        'company_id' => $company->id,
        'name' => 'Carpet Wash',
    ]);

    // 2. Run the workflow to update the service type
    SaveServiceTypeWorkflow::run([
        'companyId' => $company->id,
        'editingId' => $type->id,
        'name' => 'Premium Carpet Wash',
    ]);

    // 3. Assert values were updated correctly
    $type = $type->fresh();
    expect($type->name)->toBe('Premium Carpet Wash');
});
