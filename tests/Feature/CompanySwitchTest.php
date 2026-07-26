<?php

use App\Models\Company;
use App\Models\User;

test('switching companies updates user current company id', function () {
    // 1. Create a user
    $user = User::factory()->create([
        'role' => 'management',
    ]);

    // 2. Associate user to Company 1
    $company1 = Company::create([
        'user_id' => $user->id,
        'name' => 'Company One',
        'email' => 'one@test.com',
    ]);
    $user->update(['company_id' => $company1->id]);
    $user->companies()->attach($company1->id, ['role' => 'management']);

    // 3. Associate user to Company 2
    $company2 = Company::create([
        'user_id' => $user->id,
        'name' => 'Company Two',
        'email' => 'two@test.com',
    ]);
    $user->companies()->attach($company2->id, ['role' => 'management']);

    $user->refresh();

    // 4. Act as user and perform switch request
    $this->actingAs($user);

    $response = $this->get(route('company.switch', ['id' => $company2->id]));

    // 5. Assert redirection and updated company ID
    $response->assertRedirect(route('dashboard'));
    expect($user->fresh()->company_id)->toBe($company2->id);
});
