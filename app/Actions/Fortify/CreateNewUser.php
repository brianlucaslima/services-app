<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        $company = Company::create([
            'user_id' => $user->id,
            'name' => $user->name.' Services',
            'email' => $user->email,
            'subscription_status' => 'trial',
            'subscription_ends_at' => now()->addDays(10),
        ]);

        $user->update(['company_id' => $company->id]);

        // Attach user to company_user pivot table as management
        $user->companies()->attach($company->id, [
            'role' => 'management',
            'hourly_rate' => 0.00,
        ]);

        return $user;
    }
}
