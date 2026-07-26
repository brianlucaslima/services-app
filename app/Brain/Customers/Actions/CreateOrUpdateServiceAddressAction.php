<?php

declare(strict_types=1);

namespace App\Brain\Customers\Actions;

use App\Models\Customer;
use Brain\Action;

/**
 * Action CreateOrUpdateServiceAddressAction
 *
 * @property-read int $customerId
 * @property-read int|null $addressId
 * @property-read string $label
 * @property-read string $address
 * @property-read string|null $city
 * @property-read string|null $zipCode
 * @property-read string $startDate
 * @property-read string|null $endDate
 * @property-read float $durationHours
 * @property-read float $hourlyRate
 * @property-read string $type
 * @property int $resolvedAddressId
 */
class CreateOrUpdateServiceAddressAction extends Action
{
    public function rules(): array
    {
        return [
            'customerId' => 'required|exists:customers,id',
            'label' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'startDate' => 'required|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
            'durationHours' => 'required|numeric|min:0',
            'hourlyRate' => 'required|numeric|min:0',
            'type' => 'required|in:house,office',
        ];
    }

    public function handle(): self
    {
        $customer = Customer::findOrFail($this->customerId);

        $address = $customer->addresses()->updateOrCreate(
            ['id' => $this->addressId],
            [
                'label' => $this->label,
                'address' => $this->address,
                'city' => $this->city,
                'zip_code' => $this->zipCode,
                'start_date' => $this->startDate,
                'end_date' => $this->endDate ?: null,
                'duration_hours' => $this->durationHours,
                'hourly_rate' => $this->hourlyRate,
                'type' => $this->type,
            ]
        );

        $this->resolvedAddressId = $address->id;

        return $this;
    }
}
