<?php

use App\Brain\Invoices\Workflows\GenerateInvoiceWorkflow;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceInstance;
use App\Models\User;

test('generate invoice workflow creates invoice, adds items and updates company start number', function () {
    // 1. Setup data
    $user = User::factory()->create();
    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Test Company',
        'email' => 'test@example.com',
        'invoice_start_number' => 10,
    ]);

    $user->update(['company_id' => $company->id]);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $instance1 = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'description' => 'Regular Cleaning',
        'date' => now(),
        'time' => '10:00:00',
        'duration_hours' => 2.00,
        'hourly_rate' => 25.00,
        'status' => 'completed',
    ]);

    $instance2 = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'description' => 'Extra Window Wash',
        'date' => now(),
        'time' => '12:00:00',
        'duration_hours' => 1.50,
        'hourly_rate' => 30.00,
        'status' => 'completed',
    ]);

    // 2. Run the workflow
    $payload = GenerateInvoiceWorkflow::run([
        'companyId' => $company->id,
        'customerId' => $customer->id,
        'invoiceDate' => now()->format('Y-m-d'),
        'dueDate' => now()->addDays(14)->format('Y-m-d'),
        'notes' => 'Test Notes',
        'selectedServiceIds' => [$instance1->id, $instance2->id],
    ]);

    // 3. Assert invoice was created with correct values
    expect($payload->invoiceId)->not->toBeNull();

    $invoice = Invoice::with('items')->find($payload->invoiceId);
    expect($invoice)->not->toBeNull()
        ->and($invoice->number)->toBe('0011') // 10 + 1 = 11, padded to 4 digits
        ->and($invoice->total_amount)->toBe('95.00') // (2 * 25) + (1.5 * 30) = 50 + 45 = 95
        ->and($invoice->status)->toBe('draft')
        ->and($invoice->notes)->toBe('Test Notes');

    // 4. Assert invoice items were created
    expect($invoice->items->count())->toBe(2);

    $item1 = $invoice->items->where('service_instance_id', $instance1->id)->first();
    expect($item1)->not->toBeNull()
        ->and($item1->quantity)->toBe('2.00')
        ->and($item1->unit_price)->toBe('25.00')
        ->and($item1->amount)->toBe('50.00');

    // 5. Assert company start number was updated
    expect($company->fresh()->invoice_start_number)->toBe(11);
});
