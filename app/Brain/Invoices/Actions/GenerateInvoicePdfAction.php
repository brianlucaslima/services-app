<?php

declare(strict_types=1);

namespace App\Brain\Invoices\Actions;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Brain\Action;

/**
 * Action GenerateInvoicePdfAction
 *
 * @property-read int $invoiceId
 * @property string $pdfData
 * @property string $fileName
 */
class GenerateInvoicePdfAction extends Action
{
    public function rules(): array
    {
        return [
            'invoiceId' => 'required|exists:invoices,id',
        ];
    }

    public function handle(): self
    {
        $invoice = Invoice::with(['customer', 'company', 'items'])->findOrFail($this->invoiceId);

        $originalLocale = app()->getLocale();
        app()->setLocale('en'); // Always English on PDFs

        try {
            $pdf = Pdf::loadView('pdf.invoice', [
                'invoice' => $invoice,
            ]);
            $this->pdfData = $pdf->output();
        } finally {
            app()->setLocale($originalLocale); // Restore original locale
        }

        $this->fileName = strtolower($invoice->number).($invoice->status === 'draft' ? '-draft' : '').'.pdf';

        return $this;
    }
}
