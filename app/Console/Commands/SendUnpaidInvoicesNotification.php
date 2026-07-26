<?php

namespace App\Console\Commands;

use App\Mail\UnpaidInvoicesSummaryMail;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('app:send-unpaid-invoices-notification')]
#[Description('Sends a daily summary of sent, unpaid invoices to company managers.')]
class SendUnpaidInvoicesNotification extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting daily unpaid invoices notification run...');

        $companies = Company::all();

        foreach ($companies as $company) {
            $unpaidInvoices = Invoice::where('company_id', $company->id)
                ->where('status', 'sent')
                ->where('total_amount', '>', 0)
                ->with('customer')
                ->orderBy('due_date', 'asc')
                ->get();

            if ($unpaidInvoices->isNotEmpty()) {
                $managers = $company->users()
                    ->where('role', 'management')
                    ->get();

                if ($managers->isNotEmpty()) {
                    $this->info("Sending daily summary of {$unpaidInvoices->count()} unpaid invoices to company managers for '{$company->name}'...");

                    foreach ($managers as $manager) {
                        Mail::to($manager->email)->send(new UnpaidInvoicesSummaryMail($manager, $unpaidInvoices));
                    }
                }
            }
        }

        $this->info('Daily unpaid invoices notification run completed!');
    }
}
