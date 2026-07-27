<?php

use App\Brain\Quotes\Actions\ConvertQuoteToInvoiceAction;
use App\Brain\Quotes\Workflows\SaveQuoteWorkflow;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use Livewire\Livewire;

test('save quote workflow creates quote and quote items correctly', function () {
    // 1. Setup
    $user = User::factory()->create();
    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Quotes R Us',
        'email' => 'quotes@example.com',
    ]);
    $user->update(['company_id' => $company->id]);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'David Copperfield',
        'email' => 'david@magic.com',
    ]);

    // 2. Run save quote workflow
    $payload = SaveQuoteWorkflow::run([
        'companyId' => $company->id,
        'customerId' => $customer->id,
        'quoteDate' => now()->format('Y-m-d'),
        'expiryDate' => now()->addDays(10)->format('Y-m-d'),
        'notes' => 'Magical services estimate',
        'items' => [
            [
                'service_type_id' => null,
                'description' => 'Card tricks basic performance',
                'quantity' => 2.00,
                'unit_price' => 100.00,
            ],
            [
                'service_type_id' => null,
                'description' => 'Levitation illusion grand finale',
                'quantity' => 1.00,
                'unit_price' => 500.00,
            ],
        ],
    ]);

    // 3. Assertions
    expect($payload->quoteId)->not->toBeNull();

    $quote = Quote::with('items')->find($payload->quoteId);
    expect($quote)->not->toBeNull()
        ->and($quote->number)->toBe('Q0001')
        ->and($quote->total_amount)->toBe('700.00')
        ->and($quote->notes)->toBe('Magical services estimate');

    expect($quote->items->count())->toBe(2);

    $item1 = $quote->items->where('description', 'Card tricks basic performance')->first();
    expect($item1)->not->toBeNull()
        ->and($item1->quantity)->toBe('2.00')
        ->and($item1->unit_price)->toBe('100.00')
        ->and($item1->amount)->toBe('200.00');
});

test('convert quote to invoice action duplicates items and creates invoice draft', function () {
    // 1. Setup
    $user = User::factory()->create();
    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Quotes R Us',
        'email' => 'quotes@example.com',
        'invoice_start_number' => 50,
    ]);
    $user->update(['company_id' => $company->id]);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'David Copperfield',
        'email' => 'david@magic.com',
    ]);

    $payload = SaveQuoteWorkflow::run([
        'companyId' => $company->id,
        'customerId' => $customer->id,
        'quoteDate' => now()->format('Y-m-d'),
        'expiryDate' => now()->addDays(10)->format('Y-m-d'),
        'notes' => 'Conversion Test Notes',
        'items' => [
            [
                'service_type_id' => null,
                'description' => 'Disappearing act',
                'quantity' => 1.00,
                'unit_price' => 450.00,
            ],
        ],
    ]);

    $quote = Quote::find($payload->quoteId);

    // 2. Convert to Invoice
    $conversion = ConvertQuoteToInvoiceAction::run([
        'quoteId' => $quote->id,
    ]);

    // 3. Assertions
    expect($conversion->invoiceId)->not->toBeNull();
    expect($quote->fresh()->status)->toBe('accepted');

    $invoice = Invoice::with('items')->find($conversion->invoiceId);
    expect($invoice)->not->toBeNull()
        ->and($invoice->number)->toBe('0051') // 50 + 1
        ->and($invoice->total_amount)->toBe('450.00')
        ->and($invoice->notes)->toBe('Conversion Test Notes');

    expect($invoice->items->count())->toBe(1);
    expect($invoice->items->first()->description)->toBe('Disappearing act')
        ->and($invoice->items->first()->quantity)->toBe('1.00')
        ->and($invoice->items->first()->unit_price)->toBe('450.00');
});

test('quotes livewire component works correctly for management users', function () {
    // 1. Setup
    $user = User::factory()->create([
        'role' => 'management',
    ]);
    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Quotes R Us',
        'email' => 'quotes@example.com',
    ]);
    $user->update(['company_id' => $company->id]);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'David Copperfield',
        'email' => 'david@magic.com',
    ]);

    $quote = Quote::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'number' => 'Q0042',
        'date' => now(),
        'expiry_date' => now()->addDays(10),
        'status' => 'draft',
        'total_amount' => 1250.00,
    ]);

    $this->actingAs($user);

    // 2. Test Livewire
    Livewire::test('quotes')
        ->assertSee('Q0042')
        ->assertSee('David Copperfield')
        ->assertSee('£1,250.00')
        ->call('delete', $quote->id)
        ->assertHasNoErrors();

    expect(Quote::find($quote->id))->toBeNull();
});
