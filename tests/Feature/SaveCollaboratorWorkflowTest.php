<?php

use App\Brain\Collaborators\Workflows\SaveCollaboratorWorkflow;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('save collaborator workflow registers new collaborator and hashes password', function () {
    // 1. Setup company
    $owner = User::factory()->create();
    $company = Company::create([
        'user_id' => $owner->id,
        'name' => 'Test Company',
        'email' => 'test@example.com',
    ]);

    $owner->update(['company_id' => $company->id]);

    // 2. Run the workflow to register a new collaborator
    $houseCalendar = $company->calendars()->where('slug', 'house')->first();
    $officeCalendar = $company->calendars()->where('slug', 'office')->first();

    $payload = SaveCollaboratorWorkflow::run([
        'companyId' => $company->id,
        'userId' => null,
        'name' => 'John Collaborator',
        'email' => 'john.collab@example.com',
        'password' => 'secret123',
        'role' => 'collaborator',
        'rates' => [
            $houseCalendar->id => 15.50,
            $officeCalendar->id => 18.50,
        ],
    ]);

    // 3. Assert collaborator was created with correct values
    expect($payload->resolvedUserId)->not->toBeNull();

    $user = User::find($payload->resolvedUserId);
    expect($user)->not->toBeNull()
        ->and($user->company_id)->toBe($company->id)
        ->and($user->name)->toBe('John Collaborator')
        ->and($user->email)->toBe('john.collab@example.com')
        ->and($user->role)->toBe('collaborator')
        ->and((float) $user->hourly_rate_house)->toBe(15.50)
        ->and((float) $user->hourly_rate_office)->toBe(18.50)
        ->and(Hash::check('secret123', $user->password))->toBeTrue();
});

test('save collaborator workflow updates existing collaborator', function () {
    // 1. Setup company and collaborator
    $owner = User::factory()->create();
    $company = Company::create([
        'user_id' => $owner->id,
        'name' => 'Test Company',
        'email' => 'test@example.com',
    ]);

    $owner->update(['company_id' => $company->id]);

    $collab = User::create([
        'company_id' => $company->id,
        'name' => 'Old Name',
        'email' => 'old.email@example.com',
        'password' => Hash::make('password'),
        'role' => 'collaborator',
        'hourly_rate_house' => 10.00,
        'hourly_rate_office' => 12.00,
    ]);

    // 2. Run the workflow to update the collaborator
    $houseCalendar = $company->calendars()->where('slug', 'house')->first();
    $officeCalendar = $company->calendars()->where('slug', 'office')->first();

    SaveCollaboratorWorkflow::run([
        'companyId' => $company->id,
        'userId' => $collab->id,
        'name' => 'New Name',
        'email' => 'new.email@example.com',
        'password' => null, // keep same password
        'role' => 'management',
        'rates' => [
            $houseCalendar->id => 20.00,
            $officeCalendar->id => 25.00,
        ],
    ]);

    // 3. Assert values were updated correctly
    $collab = $collab->fresh();
    expect($collab->name)->toBe('New Name')
        ->and($collab->email)->toBe('new.email@example.com')
        ->and($collab->role)->toBe('management')
        ->and((float) $collab->hourly_rate_house)->toBe(20.00)
        ->and((float) $collab->hourly_rate_office)->toBe(25.00)
        ->and(Hash::check('password', $collab->password))->toBeTrue(); // password untouched!
});
