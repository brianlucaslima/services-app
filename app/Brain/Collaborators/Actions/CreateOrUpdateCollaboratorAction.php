<?php

declare(strict_types=1);

namespace App\Brain\Collaborators\Actions;

use App\Models\User;
use Brain\Action;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

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
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->userId),
            ],
            'role' => 'required|in:management,collaborator',
            'hourlyRate' => 'required|numeric|min:0',
            'password' => $this->userId ? 'nullable|string|min:6' : 'required|string|min:6',
        ];
    }

    public function handle(): self
    {
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'hourly_rate' => $this->hourlyRate,
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->userId) {
            $user = User::where('company_id', $this->companyId)->findOrFail($this->userId);
            $user->update($data);
        } else {
            $user = User::create([
                'company_id' => $this->companyId,
                ...$data,
            ]);
        }

        $this->resolvedUserId = $user->id;

        return $this;
    }
}
