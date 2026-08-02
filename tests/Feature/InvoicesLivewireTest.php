<?php

use App\Mail\InvoiceMail;
use App\Models\Company;
use App\Models\Customer;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
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

test('pending completed services can be edited and deleted inside the invoice screen', function () {
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

    $instance = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'service_address_id' => $address->id,
        'description' => 'Draft Office Cleaning',
        'date' => now()->format('Y-m-d'),
        'time' => '10:00:00',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'status' => 'completed',
    ]);

    // 2. Act as user and test Livewire edit & delete
    $this->actingAs($user);

    // Edit pending service
    Livewire::test('invoices')
        ->set('selectedCustomerId', $customer->id)
        ->call('openEditServiceModal', $instance->id)
        ->assertSet('editingServiceInstanceId', $instance->id)
        ->assertSet('manualDescription', 'Draft Office Cleaning')
        ->set('manualDescription', 'Updated Office Cleaning')
        ->set('manualHours', 4.00)
        ->call('saveManualService')
        ->assertHasNoErrors();

    $instance = $instance->fresh();
    expect($instance->description)->toBe('Updated Office Cleaning')
        ->and((float) $instance->duration_hours)->toBe(4.00);

    // Delete pending service
    Livewire::test('invoices')
        ->set('selectedCustomerId', $customer->id)
        ->call('deleteServiceInstance', $instance->id)
        ->assertHasNoErrors();

    expect(ServiceInstance::find($instance->id))->toBeNull();
});

test('draft invoice can be edited by going back to the service selection screen', function () {
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

    $instance1 = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'service_address_id' => $address->id,
        'description' => 'First Cleaning Work',
        'date' => now()->format('Y-m-d'),
        'time' => '10:00:00',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'status' => 'completed',
    ]);

    $instance2 = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'service_address_id' => $address->id,
        'description' => 'Second Cleaning Work',
        'date' => now()->format('Y-m-d'),
        'time' => '12:00:00',
        'duration_hours' => 3.00,
        'hourly_rate' => 20.00,
        'status' => 'completed',
    ]);

    // Create invoice only for instance1 initially
    $invoice = Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'number' => '0001',
        'date' => now(),
        'due_date' => now()->addDays(14),
        'status' => 'draft',
        'total_amount' => 40.00,
    ]);

    $item1 = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'service_instance_id' => $instance1->id,
        'description' => 'First Cleaning Work - Main Office ('.now()->format('d/m/Y').')',
        'quantity' => 2.00,
        'unit_price' => 20.00,
        'amount' => 40.00,
    ]);

    $this->actingAs($user);

    // 2. Test Livewire Edit Invoice Workflow
    Livewire::test('invoices')
        ->set('selectedInvoiceId', $invoice->id)
        ->call('editInvoice')
        ->assertSet('editingInvoiceId', $invoice->id)
        ->assertSet('screen', 'select_services')
        ->assertSet('selectedServiceIds', [$instance1->id])
        // Let's add instance2 to the invoice as well!
        ->set('selectedServiceIds', [$instance1->id, $instance2->id])
        ->set('notes', 'Added more services during edit')
        ->call('generateInvoice')
        ->assertHasNoErrors()
        ->assertSet('screen', 'detail')
        ->assertSet('editingInvoiceId', null);

    $invoice = $invoice->fresh();
    expect($invoice->notes)->toBe('Added more services during edit')
        ->and((float) $invoice->total_amount)->toBe(100.00); // (2h + 3h) * 20 = 100.00

    expect($invoice->items->count())->toBe(1); // Grouped into 1 line since same week/type/address!
    expect($invoice->items->first()->quantity)->toBe('5.00')
        ->and((float) $invoice->items->first()->amount)->toBe(100.00);
});

test('collaborators can be added and removed from a service instance on the invoice details screen', function () {
    // 1. Setup company, user, customer, and collaborator
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

    $collab1 = User::factory()->create([
        'company_id' => $company->id,
        'name' => 'Jane Done',
    ]);

    $collab2 = User::factory()->create([
        'company_id' => $company->id,
        'name' => 'Bob Marley',
    ]);

    $instance = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'description' => 'Cleaning Work',
        'date' => now()->format('Y-m-d'),
        'time' => '10:00:00',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'status' => 'completed',
    ]);

    // Attach collab1 initially
    $instance->users()->attach($collab1->id);

    // Create invoice for this instance
    $invoice = Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'number' => '0001',
        'date' => now(),
        'status' => 'draft',
        'total_amount' => 40.00,
    ]);

    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'service_instance_id' => $instance->id,
        'description' => 'Cleaning Work ('.now()->format('d/m/Y').')',
        'quantity' => 2.00,
        'unit_price' => 20.00,
        'amount' => 40.00,
    ]);

    $this->actingAs($user);

    // 2. Test Livewire details screen, adding and removing collaborators
    Livewire::test('invoices')
        ->set('selectedInvoiceId', $invoice->id)
        ->set('screen', 'detail')
        // Add collab2
        ->call('addCollaborator', $item->id, $collab2->id)
        ->assertHasNoErrors();

    expect($instance->fresh()->users->pluck('id')->toArray())
        ->toContain($collab1->id)
        ->toContain($collab2->id);

    // Remove collab1
    Livewire::test('invoices')
        ->set('selectedInvoiceId', $invoice->id)
        ->set('screen', 'detail')
        ->call('removeCollaborator', $item->id, $collab1->id)
        ->assertHasNoErrors();

    expect($instance->fresh()->users->pluck('id')->toArray())
        ->not->toContain($collab1->id)
        ->toContain($collab2->id);
});

test('pending services can be filtered by customer address/location on the invoice selection screen', function () {
    // 1. Setup company, user, customer with two addresses, and two service instances
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

    $address1 = ServiceAddress::create([
        'customer_id' => $customer->id,
        'label' => 'Main Office',
        'address' => '123 Business Rd',
        'is_active' => true,
        'type' => 'office',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'start_date' => now()->format('Y-m-d'),
    ]);

    $address2 = ServiceAddress::create([
        'customer_id' => $customer->id,
        'label' => 'Home',
        'address' => '456 Residential Way',
        'is_active' => true,
        'type' => 'house',
        'duration_hours' => 3.00,
        'hourly_rate' => 25.00,
        'start_date' => now()->format('Y-m-d'),
    ]);

    $instance1 = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'service_address_id' => $address1->id,
        'description' => 'Office Cleaning Work',
        'date' => now()->format('Y-m-d'),
        'time' => '10:00:00',
        'duration_hours' => 2.00,
        'hourly_rate' => 20.00,
        'status' => 'completed',
    ]);

    $instance2 = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'service_address_id' => $address2->id,
        'description' => 'Home Cleaning Work',
        'date' => now()->format('Y-m-d'),
        'time' => '14:00:00',
        'duration_hours' => 3.00,
        'hourly_rate' => 25.00,
        'status' => 'completed',
    ]);

    $this->actingAs($user);

    // 2. Test Livewire selection screen and filtering
    Livewire::test('invoices')
        ->set('selectedCustomerId', $customer->id)
        ->set('screen', 'select_services')
        ->call('loadPendingServices')
        ->assertSet('filterAddressId', 'all')
        ->assertSee('Office Cleaning Work')
        ->assertSee('Home Cleaning Work')

        // Filter by Main Office
        ->set('filterAddressId', $address1->id)
        ->assertSee('Office Cleaning Work')
        ->assertDontSee('Home Cleaning Work')

        // Filter by Home
        ->set('filterAddressId', $address2->id)
        ->assertSee('Home Cleaning Work')
        ->assertDontSee('Office Cleaning Work')

        // Filter back to all
        ->set('filterAddressId', 'all')
        ->assertSee('Office Cleaning Work')
        ->assertSee('Home Cleaning Work');
});

test('invoice details automatically creates and links a service instance when adding collaborators to a schedule-less hourly item', function () {
    // 1. Setup company, user, customer, and collaborator
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

    $collab = User::factory()->create([
        'company_id' => $company->id,
        'name' => 'Collaborator 1',
    ]);

    // Create an Invoice with a schedule-less (independent) InvoiceItem (service_instance_id is null)
    $invoice = Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'number' => '0002',
        'date' => now(),
        'status' => 'draft',
        'total_amount' => 50.00,
    ]);

    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'service_instance_id' => null, // null originally!
        'description' => 'Garden Maintenance',
        'quantity' => 2.50,
        'unit_price' => 20.00,
        'amount' => 50.00,
        'billing_type' => 'hourly',
    ]);

    $this->actingAs($user);

    // 2. Add collaborator using Livewire
    Livewire::test('invoices')
        ->set('selectedInvoiceId', $invoice->id)
        ->set('screen', 'detail')
        ->call('addCollaborator', $item->id, $collab->id)
        ->assertHasNoErrors();

    // 3. Verify that a ServiceInstance was automatically created and linked
    $item = $item->fresh();
    expect($item->service_instance_id)->not->toBeNull();

    $instance = ServiceInstance::find($item->service_instance_id);
    expect($instance)->not->toBeNull()
        ->and($instance->description)->toBe('Garden Maintenance')
        ->and((float) $instance->duration_hours)->toBe(2.50)
        ->and((float) $instance->hourly_rate)->toBe(20.00)
        ->and($instance->status)->toBe('completed');

    expect($instance->users->pluck('id')->toArray())->toContain($collab->id);
});

test('user can edit due date and notes of an invoice after it has been issued', function () {
    // 1. Setup company, user, and customer
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
        'number' => '0003',
        'date' => now(),
        'due_date' => now()->addDays(7),
        'status' => 'sent',
        'total_amount' => 100.00,
        'notes' => 'Original Notes',
    ]);

    $this->actingAs($user);

    $newDueDate = now()->addDays(14)->format('Y-m-d');

    Livewire::test('invoices')
        ->set('selectedInvoiceId', $invoice->id)
        ->set('screen', 'detail')
        ->call('openEditInfoModal')
        ->assertSet('showEditInfoModal', true)
        ->assertSet('editInfoDueDate', $invoice->due_date->format('Y-m-d'))
        ->assertSet('editInfoNotes', 'Original Notes')
        ->set('editInfoDueDate', $newDueDate)
        ->set('editInfoNotes', 'Updated Notes')
        ->call('saveInvoiceInfo')
        ->assertSet('showEditInfoModal', false);

    $invoice = $invoice->fresh();
    expect($invoice->due_date->format('Y-m-d'))->toBe($newDueDate)
        ->and($invoice->notes)->toBe('Updated Notes');
});

test('invoices can be filtered by email sending status', function () {
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

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Robert De Niro',
        'email' => 'robert@deniro.com',
    ]);

    $invoiceSent = Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'number' => '0001',
        'date' => now(),
        'status' => 'sent',
        'total_amount' => 100.00,
    ]);

    $invoiceNotSent = Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'number' => '0002',
        'date' => now(),
        'status' => 'draft',
        'total_amount' => 150.00,
    ]);

    // Create a successful email log for invoiceSent
    EmailLog::create([
        'company_id' => $company->id,
        'invoice_id' => $invoiceSent->id,
        'recipient_email' => $customer->email,
        'status' => 'success',
    ]);

    // Create a failed email log for invoiceNotSent (which shouldn't count as success!)
    EmailLog::create([
        'company_id' => $company->id,
        'invoice_id' => $invoiceNotSent->id,
        'recipient_email' => $customer->email,
        'status' => 'failed',
    ]);

    $this->actingAs($user);

    $test = Livewire::test('invoices');

    expect($test->get('invoices'))->toHaveCount(2);

    // Verify HTML contains the new column and values
    $test->assertSee('Email Sent')
        ->assertSee('Yes')
        ->assertSee('No');

    $test->set('filterSentSuccess', 'yes');
    expect($test->get('invoices'))->toHaveCount(1)
        ->and($test->get('invoices')[0]['number'])->toBe('0001');

    $test->set('filterSentSuccess', 'no');
    expect($test->get('invoices'))->toHaveCount(1)
        ->and($test->get('invoices')[0]['number'])->toBe('0002');

    $test->set('filterSentSuccess', 'all');
    expect($test->get('invoices'))->toHaveCount(2);
});
