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

        $services = ServiceInstance::with('address')->findMany($this->selectedServiceIds);

        foreach ($services as $service) {
            $amount = $service->duration_hours * $service->hourly_rate;
            $total += $amount;

            $itemDescription = $service->description;
            if ($service->address) {
                $itemDescription .= ' - '.$service->address->label;
            }
            $itemDescription .= ' ('.$service->date->format('d/m/Y').')';

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'service_instance_id' => $service->id,
                'description' => $itemDescription,
                'quantity' => $service->duration_hours,
                'unit_price' => $service->hourly_rate,
                'amount' => $amount,
            ]);
        }

        $invoice->update(['total_amount' => $total]);

        return $this;
    }
}
