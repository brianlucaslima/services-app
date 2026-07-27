<?php

declare(strict_types=1);

namespace App\Brain\Quotes\Actions;

use App\Brain\Invoices\Actions\CreateInvoiceAction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quote;
use Brain\Action;

/**
 * Action ConvertQuoteToInvoiceAction
 *
 * @property-read int $quoteId
 * @property int $invoiceId
 */
class ConvertQuoteToInvoiceAction extends Action
{
    public function rules(): array
    {
        return [
            'quoteId' => 'required|exists:quotes,id',
        ];
    }

    public function handle(): self
    {
        $quote = Quote::with('items')->findOrFail($this->quoteId);

        // Update quote status to accepted
        $quote->update(['status' => 'accepted']);

        // Generate dynamic invoice dates
        $invoiceDate = now()->format('Y-m-d');
        $dueDate = now()->addDays(14)->format('Y-m-d');

        // Execute CreateInvoiceAction internally to generate the correct invoice number and record
        $createInvoice = CreateInvoiceAction::run([
            'companyId' => $quote->company_id,
            'customerId' => $quote->customer_id,
            'invoiceDate' => $invoiceDate,
            'dueDate' => $dueDate,
            'notes' => $quote->notes,
        ]);

        $invoice = Invoice::findOrFail($createInvoice->invoiceId);
        $total = 0;

        foreach ($quote->items as $item) {
            $amount = (float) $item->quantity * (float) $item->unit_price;
            $total += $amount;

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'service_instance_id' => null, // No service instance since it was converted from a Quote
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'amount' => $amount,
            ]);
        }

        $invoice->update(['total_amount' => $total]);

        $this->invoiceId = $invoice->id;

        return $this;
    }
}
