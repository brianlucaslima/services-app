<?php

declare(strict_types=1);

namespace App\Brain\Invoices\Actions;

use App\Mail\InvoiceMail;
use App\Models\EmailLog;
use App\Models\Invoice;
use Brain\Action;
use Exception;
use Illuminate\Support\Facades\Mail;

/**
 * Action SendInvoiceEmailAction
 *
 * @property-read int $invoiceId
 * @property-read string $pdfData
 * @property-read string $fileName
 * @property-read string|null $recipientEmail
 */
class SendInvoiceEmailAction extends Action
{
    public function rules(): array
    {
        return [
            'invoiceId' => 'required|exists:invoices,id',
            'pdfData' => 'required|string',
            'fileName' => 'required|string',
        ];
    }

    public function handle(): self
    {
        $invoice = Invoice::with(['customer', 'company'])->findOrFail($this->invoiceId);
        $email = $this->recipientEmail ?: $invoice->customer->email;

        if (empty($email)) {
            throw new Exception(__('Customer has no email address.'));
        }

        // Retrieve all company administrators to add in CC
        $managementEmails = $invoice->company->users()
            ->wherePivot('role', 'management')
            ->whereNotNull('email')
            ->pluck('email')
            ->toArray();

        $originalLocale = app()->getLocale();
        app()->setLocale('en'); // Always English on PDFs & Emails

        try {
            $mail = Mail::to($email);

            if (! empty($managementEmails)) {
                $mail->cc($managementEmails);
            }

            $mail->send(new InvoiceMail($invoice, $this->pdfData, $this->fileName));

            // If the invoice is in draft, update status to sent
            if ($invoice->status === 'draft') {
                $invoice->update(['status' => 'sent']);
            }

            // Log Success
            EmailLog::create([
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'recipient_email' => $email,
                'status' => 'success',
            ]);
        } catch (Exception $e) {
            // Log Failure
            EmailLog::create([
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'recipient_email' => $email,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            app()->setLocale($originalLocale); // Restore original locale
        }

        return $this;
    }
}
