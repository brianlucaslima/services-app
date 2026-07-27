<?php

declare(strict_types=1);

namespace App\Brain\Invoices\Actions;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ServiceInstance;
use Brain\Action;

/**
 * Action CreateInvoiceItemsAction
 *
 * @property-read int $invoiceId
 * @property-read array $selectedServiceIds
 */
class CreateInvoiceItemsAction extends Action
{
    public function rules(): array
    {
        return [
            'invoiceId' => 'required|exists:invoices,id',
            'selectedServiceIds' => 'required|array|min:1',
        ];
    }

    public function handle(): self
    {
        $invoice = Invoice::findOrFail($this->invoiceId);
        $total = 0;

        $services = ServiceInstance::with('address')
            ->findMany($this->selectedServiceIds)
            ->sortBy('date');

        // Group services by week, service type, service address, and hourly rate
        $groups = [];

        foreach ($services as $service) {
            $weekKey = $service->date->startOfWeek()->format('Y-W');
            $addressId = $service->service_address_id ?? 0;
            $typeId = $service->service_type_id ?? 0;
            $rate = (float) $service->hourly_rate;

            $groupKey = "{$weekKey}_{$addressId}_{$typeId}_{$rate}";

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'services' => [],
                    'address' => $service->address,
                    'base_description' => $service->description,
                    'hourly_rate' => $rate,
                    'dates' => [],
                ];
            }

            $groups[$groupKey]['services'][] = $service;
            $groups[$groupKey]['dates'][] = $service->date;
        }

        foreach ($groups as $group) {
            // Sort dates chronologically
            usort($group['dates'], fn ($a, $b) => $a <=> $b);

            $formattedDates = array_map(fn ($date) => $date->format('d/m/Y'), $group['dates']);
            $datesString = implode(', ', $formattedDates);

            $itemDescription = $group['base_description'];
            if ($group['address']) {
                $itemDescription .= ' - '.$group['address']->label;
            }
            $itemDescription .= ' ('.$datesString.')';

            $totalHours = 0;
            foreach ($group['services'] as $service) {
                $totalHours += (float) $service->duration_hours;
            }

            $amount = $totalHours * $group['hourly_rate'];
            $total += $amount;

            // Reference the first service instance in the group
            $firstService = $group['services'][0];

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'service_instance_id' => $firstService->id,
                'description' => $itemDescription,
                'quantity' => $totalHours,
                'unit_price' => $group['hourly_rate'],
                'amount' => $amount,
            ]);
        }

        $invoice->update(['total_amount' => $total]);

        return $this;
    }
}
