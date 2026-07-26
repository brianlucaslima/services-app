<?php

use App\Mail\InvoiceMail;
use App\Models\Company;
use App\Models\Customer;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\ServiceAddress;
use App\Models\ServiceInstance;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('invoices can be filtered by status, number and customer', function () {
    // 1. Setup company and user
    $user = User::factory()->create([
        'role' => 'management',
    ]);
    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Invoease HQ',
        'email' => 'hq@invoease.co.uk',
    ]);
    $user->update(['company_id' => $company->id]);
    $user->refresh();

    $customer1 = Customer::create([
        'company_id' => $company->id,
        'name' => 'Robert De Niro',
        'email' => 'robert@deniro.com',
    ]);

    $customer2 = Customer::create([
        'company_id' => $company->id,
        'name' => 'Al Pacino',
        'email' => 'al@pacino.com',
    ]);

    $invoice1 = Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer1->id,
        'number' => '0001',
        'date' => now(),
        'status' => 'paid',
        'total_amount' => 100.00,
    ]);

    $invoice2 = Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer2->id,
        'number' => '0002',
        'date' => now(),
        'status' => 'draft',
        'total_amount' => 150.00,
    ]);

    // 2. Act as user and test Livewire filters
    $this->actingAs($user);

    Livewire::test('invoices')
        ->assertSee('£100.00')
        ->assertSee('£150.00')
        // Filter by customer
        ->set('filterCustomer', (string) $customer1->id)
        ->call('refreshInvoices')
        ->assertSee('£100.00')
        ->assertDontSee('£150.00')
        // Filter by number
        ->set('filterCustomer', 'all')
        ->set('filterNumber', '0002')
        ->call('refreshInvoices')
        ->assertSee('£150.00')
        ->assertDontSee('£100.00')
        // Filter by status
        ->set('filterNumber', '')
        ->set('filterStatus', 'paid')
        ->call('refreshInvoices')
        ->assertSee('£100.00')
        ->assertDontSee('£150.00');
});

test('invoice generation updates company last invoice number sequencially', function () {
    // 1. Setup company, user, and customer
    $user = User::factory()->create([
        'role' => 'management',
    ]);
    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Invoease HQ',
        'email' => 'hq@invoease.co.uk',
        'invoice_start_number' => 5, // Last number is 5
    ]);
    $user->update(['company_id' => $company->id]);
    $user->refresh();

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    // Create a completed, uninvoiced service
    $instance = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'description' => 'Regular Clean',
        'date' => now(),
        'time' => '10:00:00',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'status' => 'completed',
    ]);

    // 2. Act as user and test Livewire generation
    $this->actingAs($user);

    Livewire::test('invoices')
        ->set('selectedCustomerId', $customer->id)
        ->set('selectedServiceIds', [$instance->id])
        ->set('notes', 'Thank you!')
        ->call('generateInvoice')
        ->assertHasNoErrors();

    // 3. Verify that the invoice was generated as 0006 and company start number is 6
    $invoice = Invoice::where('company_id', $company->id)->where('number', '0006')->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->total_amount)->toBe('40.00')
        ->and($invoice->status)->toBe('draft');

    expect($company->fresh()->invoice_start_number)->toBe(6);
});

test('manual service can be added inside the invoice screen', function () {
    // 1. Setup company, user, customer, service type, and collaborator
    $user = User::factory()->create([
        'role' => 'management',
    ]);
    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Invoease HQ',
        'email' => 'hq@invoease.co.uk',
    ]);
    $user->update(['company_id' => $company->id]);
    $user->refresh();

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $address = ServiceAddress::create([
        'customer_id' => $customer->id,
        'label' => 'Main Office',
        'address' => '123 Business Rd',
        'is_active' => true,
        'type' => 'office',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'start_date' => now()->format('Y-m-d'),
    ]);

    $type = ServiceType::create([
        'company_id' => $company->id,
        'name' => 'Deep Cleaning',
    ]);

    $collab = User::factory()->create([
        'company_id' => $company->id,
    ]);

    // 2. Act as user and test Livewire manual service addition
    $this->actingAs($user);

    Livewire::test('invoices')
        ->set('selectedCustomerId', $customer->id)
        ->call('openManualModal')
        ->assertSet('manualAddressId', $address->id)
        ->set('manualServiceTypeId', $type->id)
        ->set('manualDescription', 'Premium Deep Clean')
        ->set('manualDate', now()->format('Y-m-d'))
        ->set('manualHours', 3.00)
        ->set('manualRate', 25.00)
        ->set('manualUserIds', [$collab->id])
        ->call('saveManualService')
        ->assertHasNoErrors();

    // 3. Verify manual ServiceInstance was created and team synced
    $instance = ServiceInstance::where('customer_id', $customer->id)
        ->where('description', 'Premium Deep Clean')
        ->first();

    expect($instance)->not->toBeNull()
        ->and($instance->service_address_id)->toBe($address->id)
        ->and($instance->duration_hours)->toBe('3.00')
        ->and($instance->hourly_rate)->toBe('25.00')
        ->and($instance->status)->toBe('completed');

    expect($instance->users->count())->toBe(1)
        ->and($instance->users->first()->id)->toBe($collab->id);
});

test('check before sending shows confirmation modal if email already sent', function () {
    Mail::fake();

    // 1. Setup data
    $user = User::factory()->create([
        'role' => 'management',
    ]);
    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Invoease HQ',
        'email' => 'hq@invoease.co.uk',
    ]);
    $user->update(['company_id' => $company->id]);
    $user->refresh();

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $invoice = Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'number' => '0001',
        'date' => now(),
        'status' => 'sent',
        'total_amount' => 100.00,
    ]);

    // Create an existing success log
    EmailLog::create([
        'company_id' => $company->id,
        'invoice_id' => $invoice->id,
        'recipient_email' => $customer->email,
        'status' => 'success',
    ]);

    // 2. Act as user and test Livewire sending flow
    $this->actingAs($user);

    Livewire::test('invoices')
        ->set('selectedInvoiceId', $invoice->id)
        ->call('checkBeforeSendEmail')
        ->assertSet('showSendConfirmationModal', true)
        ->assertSet('selectedInvoiceSentCount', 1)
        ->call('confirmSendEmail')
        ->assertHasNoErrors();

    // 3. Assert mail was dispatched
    Mail::assertSent(InvoiceMail::class, 1);
});
