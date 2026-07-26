<?php

use App\Models\Company;
use App\Models\ServiceType;
use App\Models\User;
use Livewire\Livewire;

test('service types can be listed', function () {
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

    $type = ServiceType::create([
        'company_id' => $company->id,
        'name' => 'Regular House Cleaning',
    ]);

    // 2. Act as user and test Livewire
    $this->actingAs($user);

    Livewire::test('service-types')
        ->assertSee('Regular House Cleaning');
});

test('service type can be created with valid name', function () {
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

    // 2. Act as user and test Livewire creating a service type
    $this->actingAs($user);

    Livewire::test('service-types')
        ->set('name', 'Deep Office Cleaning')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Deep Office Cleaning');

    expect(ServiceType::where('company_id', $company->id)->where('name', 'Deep Office Cleaning')->exists())->toBeTrue();
});

test('service type can be edited', function () {
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

    $type = ServiceType::create([
        'company_id' => $company->id,
        'name' => 'Gardening',
    ]);

    // 2. Act as user and test Livewire editing
    $this->actingAs($user);

    Livewire::test('service-types')
        ->call('edit', $type->id)
        ->assertSet('editingId', $type->id)
        ->assertSet('name', 'Gardening')
        ->set('name', 'Premium Gardening')
        ->call('save')
        ->assertHasNoErrors();

    expect($type->fresh()->name)->toBe('Premium Gardening');
});

test('service type name is required and unique within company', function () {
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

    $type = ServiceType::create([
        'company_id' => $company->id,
        'name' => 'Window Wash',
    ]);

    // 2. Act as user and test Livewire validation
    $this->actingAs($user);

    // Test required name
    Livewire::test('service-types')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);

    // Test unique name within company
    Livewire::test('service-types')
        ->set('name', 'Window Wash')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

test('service type can be deleted', function () {
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

    $type = ServiceType::create([
        'company_id' => $company->id,
        'name' => 'Ironing',
    ]);

    // 2. Act as user and test Livewire deleting
    $this->actingAs($user);

    Livewire::test('service-types')
        ->call('delete', $type->id)
        ->assertHasNoErrors()
        ->assertDontSee('Ironing');

    expect(ServiceType::find($type->id))->toBeNull();
});
