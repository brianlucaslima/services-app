<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('company settings form pre-fills and saves company details', function () {
    // 1. Setup user and company
    $user = User::factory()->create([
        'role' => 'management',
    ]);

    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Original Clean',
        'email' => 'contact@original.com',
        'phone' => '111',
        'address' => 'Old Address',
        'payment_name' => 'Original Clean Ltd',
        'payment_account_number' => '11111111',
        'payment_sort_code' => '11-11-11',
        'default_invoice_message' => 'Old Notes',
        'primary_color' => '#000000',
        'invoice_start_number' => 0,
    ]);

    $user->update(['company_id' => $company->id]);
    $user->refresh();

    // 2. Act as user and test Livewire
    $this->actingAs($user);

    Livewire::test('settings.company')
        ->assertSet('name', 'Original Clean')
        ->assertSet('email', 'contact@original.com')
        ->assertSet('address', 'Old Address')
        ->assertSet('payment_name', 'Original Clean Ltd')
        ->set('name', 'Updated Clean')
        ->set('email', 'contact@updated.com')
        ->set('address', 'New Address')
        ->set('payment_name', 'Updated Clean Ltd')
        ->call('save')
        ->assertHasNoErrors();

    // 3. Assert database was updated correctly
    $company = $company->fresh();
    expect($company->name)->toBe('Updated Clean')
        ->and($company->email)->toBe('contact@updated.com')
        ->and($company->address)->toBe('New Address')
        ->and($company->payment_name)->toBe('Updated Clean Ltd');
});

test('company settings can upload a new logo', function () {
    Storage::fake('public');

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
    $user->refresh();

    // 2. Act as user and test Livewire file upload
    $this->actingAs($user);

    $file = UploadedFile::fake()->image('company-logo.png');

    Livewire::test('settings.company')
        ->set('logo', $file)
        ->call('save')
        ->assertHasNoErrors();

    // 3. Assert file was stored and logo column was updated
    $company = $company->fresh();
    expect($company->logo)->not->toBeNull();
    Storage::disk('public')->assertExists($company->logo);
});

test('saving company settings without uploading a logo preserves the existing logo', function () {
    // 1. Setup user and company with an existing logo
    $user = User::factory()->create([
        'role' => 'management',
    ]);

    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Invoease HQ',
        'email' => 'hq@invoease.co.uk',
        'logo' => 'logos/existing-logo.png', // already has a logo!
    ]);

    $user->update(['company_id' => $company->id]);
    $user->refresh();

    // 2. Act as user and test Livewire
    $this->actingAs($user);

    Livewire::test('settings.company')
        ->assertSet('logo', null) // no new file uploaded
        ->set('name', 'Invoease HQ Updated')
        ->call('save')
        ->assertHasNoErrors();

    // 3. Assert that the company logo is preserved (NOT deleted or nullified!)
    $company = $company->fresh();
    expect($company->logo)->toBe('logos/existing-logo.png');
});

test('collaborator cannot access company settings', function () {
    // 1. Setup collaborator and company
    $owner = User::factory()->create();
    $company = Company::create([
        'user_id' => $owner->id,
        'name' => 'Invoease HQ',
        'email' => 'hq@invoease.co.uk',
    ]);
    $owner->update(['company_id' => $company->id]);

    $collab = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'collaborator',
    ]);

    // 2. Act as collaborator and try to visit route /settings/company
    $this->actingAs($collab);

    $response = $this->get(route('company.edit'));

    // 3. Assert 403 Forbidden
    $response->assertStatus(403);
});
