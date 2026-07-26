<?php

use App\Brain\Subscriptions\Workflows\ExtendSubscriptionWorkflow;
use App\Models\Company;
use App\Models\User;

test('extend subscription workflow extends subscription and updates status', function () {
    // 1. Setup company
    $user = User::factory()->create();
    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Test Company',
        'email' => 'test@example.com',
        'subscription_status' => 'trial',
        'subscription_ends_at' => now()->addDays(5),
    ]);

    // 2. Run workflow to extend by 30 days
    ExtendSubscriptionWorkflow::run([
        'companyId' => $company->id,
        'status' => 'active',
        'daysToExtend' => 30,
    ]);

    // 3. Assert values were updated correctly
    $company = $company->fresh();
    expect($company->subscription_status)->toBe('active')
        ->and($company->subscription_ends_at->isFuture())->toBeTrue()
        ->and($company->subscription_ends_at->format('Y-m-d'))->toBe(now()->addDays(35)->format('Y-m-d'));
});

test('extend subscription workflow can suspend subscription', function () {
    // 1. Setup company
    $user = User::factory()->create();
    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Test Company',
        'email' => 'test@example.com',
        'subscription_status' => 'active',
        'subscription_ends_at' => now()->addDays(30),
    ]);

    // 2. Run workflow to suspend
    ExtendSubscriptionWorkflow::run([
        'companyId' => $company->id,
        'status' => 'expired',
    ]);

    // 3. Assert values were suspended correctly
    $company = $company->fresh();
    expect($company->subscription_status)->toBe('expired')
        ->and($company->subscription_ends_at->isPast())->toBeTrue();
});
