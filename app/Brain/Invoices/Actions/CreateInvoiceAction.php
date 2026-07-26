<?php

declare(strict_types=1);

namespace App\Brain\Invoices\Actions;

use App\Models\Company;
use App\Models\Invoice;
use Brain\Action;

/**
 * Action CreateInvoiceAction
 *
 * @property-read int $companyId
 * @property-read int $customerId
 * @property-read string $invoiceDate
 * @property-read string $dueDate
 * @property-read string|null $notes
 * @property int $invoiceId
 */
class CreateInvoiceAction extends Action
{
    public function rules(): array
    {
        return [
            'companyId' => 'required|exists:companies,id',
            'customerId' => 'required|exists:customers,id',
            'invoiceDate' => 'required|date',
            'dueDate' => 'required|date',
        ];
    }

    public function handle(): self
    {
        $company = Company::findOrFail($this->companyId);
        $lastNum = $company->invoice_start_number ?? 0;
        $nextNum = $lastNum + 1;

        // Safety loop to prevent duplicate invoice numbers
        do {
            $number = str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
            $exists = Invoice::where('company_id', $company->id)->where('number', $number)->exists();
            if ($exists) {
                $nextNum++;
            }
        } while ($exists);

        // Update the company's last invoice number
        $company->update(['invoice_start_number' => $nextNum]);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $this->customerId,
            'number' => $number,
            'date' => $this->invoiceDate,
            'due_date' => $this->dueDate,
            'status' => 'draft',
            'total_amount' => 0,
            'notes' => $this->notes,
        ]);

        $this->invoiceId = $invoice->id;

        return $this;
    }
}
