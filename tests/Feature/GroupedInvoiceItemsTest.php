<?php

use App\Brain\Invoices\Workflows\GenerateInvoiceWorkflow;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceAddress;
use App\Models\ServiceInstance;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Support\Carbon;

test('invoice items from the same week, service type, and address are grouped into a single line', function () {
    // 1. Setup data
    $user = User::factory()->create();
    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'Weekly Grouping Corp',
        'email' => 'grouping@example.com',
        'invoice_start_number' => 100,
    ]);

    $user->update(['company_id' => $company->id]);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
    ]);

    $serviceType = ServiceType::create([
        'company_id' => $company->id,
        'name' => 'Weekly Cleaning',
    ]);

    $serviceAddress = ServiceAddress::create([
        'customer_id' => $customer->id,
        'label' => 'Main Office',
        'address' => '123 Fake Street',
        'duration_hours' => 2.50,
        'hourly_rate' => 20.00,
        'is_active' => true,
    ]);

    // Create three service instances:
    // Two in the same week (this week), same type, same rate
    // One in a different week (next week), same type, same rate

    $mondayThisWeek = Carbon::now()->startOfWeek();
    $wednesdayThisWeek = $mondayThisWeek->copy()->addDays(2);
    $mondayNextWeek = $mondayThisWeek->copy()->addWeek();

    // Group A (Same Week, Instance 1)
    $instance1 = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'service_type_id' => $serviceType->id,
        'service_address_id' => $serviceAddress->id,
        'description' => 'Weekly Cleaning',
        'date' => $mondayThisWeek,
        'time' => '09:00:00',
        'duration_hours' => 2.50,
        'hourly_rate' => 20.00,
        'status' => 'completed',
    ]);

    // Group A (Same Week, Instance 2)
    $instance2 = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'service_type_id' => $serviceType->id,
        'service_address_id' => $serviceAddress->id,
        'description' => 'Weekly Cleaning',
        'date' => $wednesdayThisWeek,
        'time' => '09:00:00',
        'duration_hours' => 2.50,
        'hourly_rate' => 20.00,
        'status' => 'completed',
    ]);

    // Group B (Next Week, Instance 3)
    $instance3 = ServiceInstance::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'service_type_id' => $serviceType->id,
        'service_address_id' => $serviceAddress->id,
        'description' => 'Weekly Cleaning',
        'date' => $mondayNextWeek,
        'time' => '09:00:00',
        'duration_hours' => 3.00,
        'hourly_rate' => 20.00,
        'status' => 'completed',
    ]);

    // 2. Run the workflow
    $payload = GenerateInvoiceWorkflow::run([
        'companyId' => $company->id,
        'customerId' => $customer->id,
        'invoiceDate' => now()->format('Y-m-d'),
        'dueDate' => now()->addDays(14)->format('Y-m-d'),
        'notes' => 'Weekly Grouping Test',
        'selectedServiceIds' => [$instance1->id, $instance2->id, $instance3->id],
    ]);

    // 3. Assert invoice and items
    expect($payload->invoiceId)->not->toBeNull();

    $invoice = Invoice::with('items')->find($payload->invoiceId);
    expect($invoice)->not->toBeNull();

    // 4. We expect EXACTLY 2 items instead of 3 (the first 2 should be grouped)
    expect($invoice->items->count())->toBe(2);

    // Sum of hours for group A: 2.50 + 2.50 = 5.00
    // Sum of amounts for group A: 5.00 * 20.00 = 100.00
    $groupedItem = $invoice->items->first();
    expect($groupedItem->quantity)->toBe('5.00')
        ->and($groupedItem->unit_price)->toBe('20.00')
        ->and($groupedItem->amount)->toBe('100.00');

    // Dates of group A should be appended chronologically in parentheses
    $expectedDatesString = '('.$mondayThisWeek->format('d/m/Y').', '.$wednesdayThisWeek->format('d/m/Y').')';
    expect($groupedItem->description)->toContain($expectedDatesString);

    // Group B item check:
    $separateItem = $invoice->items->last();
    expect($separateItem->quantity)->toBe('3.00')
        ->and($separateItem->unit_price)->toBe('20.00')
        ->and($separateItem->amount)->toBe('60.00');

    $expectedDateNextWeek = '('.$mondayNextWeek->format('d/m/Y').')';
    expect($separateItem->description)->toContain($expectedDateNextWeek);

    // Total invoice amount: 100.00 + 60.00 = 160.00
    expect($invoice->total_amount)->toBe('160.00');
});
