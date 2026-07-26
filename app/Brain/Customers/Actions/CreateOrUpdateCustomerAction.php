<?php

declare(strict_types=1);

namespace App\Brain\Customers\Actions;

use App\Models\Customer;
use Brain\Action;

/**
 * Action CreateOrUpdateCustomerAction
 *
 * @property-read int $companyId
 * @property-read int|null $customerId
 * @property-read string $name
 * @property-read string|null $phone
 * @property-read string|null $email
 * @property-read string|null $address
 * @property-read bool $isActive
 * @property int $resolvedCustomerId
 */
class CreateOrUpdateCustomerAction extends Action
{
    public function rules(): array
    {
        return [
            'companyId' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'isActive' => 'boolean',
        ];
    }

    public function handle(): self
    {
        $data = [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'is_active' => $this->isActive,
        ];

        if ($this->customerId) {
            $customer = Customer::where('company_id', $this->companyId)->findOrFail($this->customerId);
            $customer->update($data);
        } else {
            $customer = Customer::create([
                'company_id' => $this->companyId,
                ...$data,
            ]);
        }

        $this->resolvedCustomerId = $customer->id;

        return $this;
    }
}
