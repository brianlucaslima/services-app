<?php

use App\Models\Company;
use App\Models\User;
use Livewire\Livewire;

test('profile settings form pre-fills and saves user details', function () {
    // 1. Setup user and company
    $user = User::factory()->create([
        'name' => 'Brian Old',
        'email' => 'brian.old@gmail.com',
        'role' => 'management',
    ]);

    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Invoease HQ',
        'email' => 'hq@invoease.co.uk',
    ]);

    $user->update(['company_id' => $company->id]);
    $user->refresh();

    // 2. Act as user and test Livewire
    $this->actingAs($user);

    Livewire::test('settings.profile')
        ->assertSet('name', 'Brian Old')
        ->assertSet('email', 'brian.old@gmail.com')
        ->set('name', 'Brian New')
        ->set('email', 'brian.new@gmail.com')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    // 3. Assert database was updated correctly
    $user = $user->fresh();
    expect($user->name)->toBe('Brian New')
        ->and($user->email)->toBe('brian.new@gmail.com');
});

test('profile settings language change updates database, app locale, and session', function () {
    // 1. Setup user and company with initial pt_BR locale
    $user = User::factory()->create([
        'role' => 'management',
        'locale' => 'pt_BR',
    ]);

    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Invoease HQ',
        'email' => 'hq@invoease.co.uk',
    ]);

    $user->update(['company_id' => $company->id]);
    $user->refresh();

    // Initialize session locale to pt_BR
    session(['locale' => 'pt_BR']);
    app()->setLocale('pt_BR');

    // 2. Act as user and test Livewire language switch to en_GB
    $this->actingAs($user);

    Livewire::test('settings.profile')
        ->assertSet('locale', 'pt_BR')
        ->set('locale', 'en_GB')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    // 3. Assert database, session, and app locale are updated correctly
    $user = $user->fresh();
    expect($user->locale)->toBe('en_GB');
    expect(session('locale'))->toBe('en_GB');
    expect(app()->getLocale())->toBe('en_GB');
});
