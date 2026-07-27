<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('collaborators can be listed for management role', function () {
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

    $collab = User::factory()->create([
        'company_id' => $company->id,
        'name' => 'John Collab',
        'email' => 'john@example.com',
        'role' => 'collaborator',
    ]);

    // 2. Act as user and test Livewire
    $this->actingAs($user);

    Livewire::test('collaborators')
        ->assertSee('John Collab')
        ->assertSee('john@example.com');
});

test('collaborators can be searched by name or email', function () {
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

    $collab1 = User::factory()->create([
        'company_id' => $company->id,
        'name' => 'John Collab',
        'email' => 'john@example.com',
    ]);

    $collab2 = User::factory()->create([
        'company_id' => $company->id,
        'name' => 'Mary Smith',
        'email' => 'mary@example.com',
    ]);

    // 2. Act as user and test Livewire search
    $this->actingAs($user);

    Livewire::test('collaborators')
        ->set('search', 'John')
        ->assertSee('John Collab')
        ->assertDontSee('Mary Smith')
        ->set('search', 'mary@example.com')
        ->assertSee('Mary Smith')
        ->assertDontSee('John Collab');
});

test('new collaborator can be registered', function () {
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

    // 2. Act as user and test Livewire registration
    $this->actingAs($user);

    Livewire::test('collaborators')
        ->set('name', 'George Lucas')
        ->set('email', 'george@lucas.co.uk')
        ->set('password', 'secret123')
        ->set('role', 'collaborator')
        ->set('hourly_rate_house', 15.00)
        ->set('hourly_rate_office', 20.00)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('George Lucas');

    $newCollab = User::where('email', 'george@lucas.co.uk')->first();
    expect($newCollab)->not->toBeNull()
        ->and($newCollab->name)->toBe('George Lucas')
        ->and($newCollab->role)->toBe('collaborator')
        ->and((float) $newCollab->hourly_rate_house)->toBe(15.00)
        ->and((float) $newCollab->hourly_rate_office)->toBe(20.00)
        ->and(Hash::check('secret123', $newCollab->password))->toBeTrue();
});

test('collaborator can be edited', function () {
    // 1. Setup user, company and collaborator
    $user = User::factory()->create([
        'role' => 'management',
    ]);

    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Invoease HQ',
        'email' => 'hq@invoease.co.uk',
    ]);

    $user->update(['company_id' => $company->id]);

    $collab = User::create([
        'company_id' => $company->id,
        'name' => 'Steven Spielberg',
        'email' => 'steven@spielberg.com',
        'password' => Hash::make('password123'),
        'role' => 'collaborator',
        'hourly_rate_house' => 12.00,
        'hourly_rate_office' => 15.00,
    ]);

    // 2. Act as user and test Livewire editing
    $this->actingAs($user);

    Livewire::test('collaborators')
        ->call('openEditModal', $collab->id)
        ->assertSet('userId', $collab->id)
        ->assertSet('name', 'Steven Spielberg')
        ->assertSet('email', 'steven@spielberg.com')
        ->set('name', 'Steven Spielberg II')
        ->set('hourly_rate_house', 18.50)
        ->set('hourly_rate_office', 22.00)
        ->set('password', '') // keep same password
        ->call('save')
        ->assertHasNoErrors();

    $collab = $collab->fresh();
    expect($collab->name)->toBe('Steven Spielberg II')
        ->and((float) $collab->hourly_rate_house)->toBe(18.50)
        ->and((float) $collab->hourly_rate_office)->toBe(22.00)
        ->and(Hash::check('password123', $collab->password))->toBeTrue(); // password untouched
});

test('collaborator cannot delete themselves, but can delete others', function () {
    // 1. Setup user, company and collaborator
    $user = User::factory()->create([
        'role' => 'management',
    ]);

    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Invoease HQ',
        'email' => 'hq@invoease.co.uk',
    ]);

    $user->update(['company_id' => $company->id]);

    $collab = User::create([
        'company_id' => $company->id,
        'name' => 'James Cameron',
        'email' => 'james@cameron.com',
        'password' => Hash::make('password123'),
        'role' => 'collaborator',
        'hourly_rate_house' => 10.00,
        'hourly_rate_office' => 10.00,
    ]);

    // 2. Act as user and test Livewire deleting
    $this->actingAs($user);

    // Try to delete oneself (should fail and prevent deletion)
    Livewire::test('collaborators')
        ->call('delete', $user->id);

    expect(User::find($user->id))->not->toBeNull();

    // Delete other collaborator (should succeed)
    Livewire::test('collaborators')
        ->call('delete', $collab->id)
        ->assertHasNoErrors();

    expect(User::find($collab->id))->toBeNull();
});
