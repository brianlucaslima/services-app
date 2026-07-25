<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;

test('invoice pdf generation forces english locale even if user has pt_BR locale', function () {
    // 1. Create a user with pt_BR locale
    $user = User::factory()->create([
        'locale' => 'pt_BR',
    ]);

    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'My Company',
        'email' => 'company@example.com',
    ]);

    $user->update(['company_id' => $company->id]);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'address' => '123 Test St',
    ]);

    $invoice = Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'number' => '123',
        'date' => now(),
        'due_date' => now()->addDays(7),
        'status' => 'draft',
        'total_amount' => 100.00,
    ]);

    // 2. Mock Pdf facade to verify the locale is 'en' when view is loaded
    Pdf::shouldReceive('loadView')
        ->once()
        ->with('pdf.invoice', Mockery::on(function ($data) use ($invoice) {
            expect(app()->getLocale())->toBe('en');
            expect($data['invoice']->id)->toBe($invoice->id);

            return true;
        }))
        ->andReturnSelf();

    Pdf::shouldReceive('download')
        ->once()
        ->with('123-draft.pdf')
        ->andReturn(response('PDF Content', 200, ['Content-Type' => 'application/pdf']));

    // 3. Act as the user and request the PDF
    $this->actingAs($user);

    $response = $this->get(route('invoices.pdf', ['id' => $invoice->id]));

    $response->assertStatus(200);
});

test('collaborator report pdf generation forces english locale even if user has pt_BR locale', function () {
    // 1. Create a user with pt_BR locale
    $user = User::factory()->create([
        'locale' => 'pt_BR',
    ]);

    $company = Company::create([
        'user_id' => $user->id,
        'name' => 'My Company',
        'email' => 'company@example.com',
    ]);

    $user->update(['company_id' => $company->id]);

    // 2. Mock Pdf facade to verify the locale is 'en' when view is loaded
    Pdf::shouldReceive('loadView')
        ->once()
        ->with('pdf.collaborator-report', Mockery::on(function ($data) use ($user) {
            expect(app()->getLocale())->toBe('en');
            expect($data['user']->id)->toBe($user->id);

            return true;
        }))
        ->andReturnSelf();

    Pdf::shouldReceive('download')
        ->once()
        ->andReturn(response('PDF Content', 200, ['Content-Type' => 'application/pdf']));

    // 3. Act as the user and request the PDF
    $this->actingAs($user);

    $response = $this->get(route('reports.pdf', [
        'id' => $user->id,
        'start_date' => now()->subDays(7)->format('Y-m-d'),
        'end_date' => now()->format('Y-m-d'),
    ]));

    $response->assertStatus(200);
});
