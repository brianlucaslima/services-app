<?php

declare(strict_types=1);

namespace App\Brain\Collaborators\Actions;

use App\Models\User;
use Brain\Action;
use Illuminate\Support\Facades\Hash;

/**
 * Action CreateOrUpdateCollaboratorAction
 *
 * @property-read int $companyId
 * @property-read int|null $userId
 * @property-read string $name
 * @property-read string $email
 * @property-read string|null $password
 * @property-read string $role
 * @property-read float $hourlyRate
 * @property int $resolvedUserId
 */
class CreateOrUpdateCollaboratorAction extends Action
{
    public function rules(): array
    {
        return [
            'companyId' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                function ($attribute, $value, $fail) {
                    $user = User::where('email', $value)->first();
                    if ($user) {
                        if ($this->userId) {
                            if ($user->id !== $this->userId) {
                                $fail(__('The email has already been taken.'));
                            }
                        } else {
                            // Fail only if they are already registered in this company
                            $alreadyInCompany = $user->companies()->where('companies.id', $this->companyId)->exists();
                            if ($alreadyInCompany) {
                                $fail(__('This collaborator is already registered in your company.'));
                            }
                        }
                    }
                },
            ],
            'role' => 'required|in:management,collaborator',
            'hourlyRate' => 'required|numeric|min:0',
            'password' => $this->userId ? 'nullable|string|min:8' : 'required|string|min:8',
        ];
    }

    public function handle(): self
    {
        $userData = [
            'name' => $this->name,
        ];

        if ($this->password) {
            $userData['password'] = Hash::make($this->password);
        }

        if ($this->userId) {
            $user = User::query()->findOrFail($this->userId);

            $userData['email'] = $this->email;
            $user->update($userData);
        } else {
            // Check if user exists globally by email
            $user = User::where('email', $this->email)->first();

            if ($user) {
                // Registering an existing global user into a NEW company
                if (! $user->company_id) {
                    $user->update(['company_id' => $this->companyId]);
                }
            } else {
                // New user globally
                $userData['email'] = $this->email;
                $userData['company_id'] = $this->companyId;

                $user = User::create($userData);

            }
        }

        // Sync the pivot table values for this company
        $user->companies()->syncWithPivotValues([$this->companyId], [
            'role' => $this->role,
            'hourly_rate' => $this->hourlyRate,
        ], false);

        $this->resolvedUserId = $user->id;

        return $this;
    }
}
