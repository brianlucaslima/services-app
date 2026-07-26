<?php

use App\Mail\UnpaidInvoicesSummaryMail;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

test('daily unpaid invoices notification command sends summary only to managers', function () {
    Mail::fake();

    // 1. Create a primary user for the company owner
    $owner = User::factory()->create([
        'email' => 'owner@example.com',
    ]);

    // 2. Create the company with user_id
    $company = Company::create([
        'user_id' => $owner->id,
        'name' => 'Test Company',
        'email' => 'test@example.com',
    ]);

    $owner->update(['company_id' => $company->id]);

    // 3. Create managers and collaborators
    $manager1 = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'management',
        'email' => 'manager1@example.com',
    ]);

    $manager2 = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'management',
        'email' => 'manager2@example.com',
    ]);

    $collaborator = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'collaborator',
        'email' => 'collab@example.com',
    ]);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    // 3. Create different invoices
    $unpaidInvoice = Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'number' => '111',
        'date' => now(),
        'due_date' => now()->addDays(5),
        'status' => 'sent',
        'total_amount' => 150.00,
    ]);

    $draftInvoice = Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'number' => '222',
        'date' => now(),
        'status' => 'draft',
        'total_amount' => 200.00,
    ]);

    $paidInvoice = Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'number' => '333',
        'date' => now(),
        'status' => 'paid',
        'total_amount' => 300.00,
    ]);

    // 4. Run the Artisan command
    Artisan::call('app:send-unpaid-invoices-notification');

    // 5. Assert Mail was sent only to managers, with the correct unpaid invoice
    Mail::assertSent(UnpaidInvoicesSummaryMail::class, function ($mail) use ($manager1, $unpaidInvoice) {
        return $mail->hasTo($manager1->email) &&
               $mail->invoices->count() === 1 &&
               $mail->invoices->first()->id === $unpaidInvoice->id;
    });

    Mail::assertSent(UnpaidInvoicesSummaryMail::class, function ($mail) use ($manager2) {
        return $mail->hasTo($manager2->email);
    });

    // 6. Assert Mail was NOT sent to the collaborator
    Mail::assertNotSent(UnpaidInvoicesSummaryMail::class, function ($mail) use ($collaborator) {
        return $mail->hasTo($collaborator->email);
    });
});
