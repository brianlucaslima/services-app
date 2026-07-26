<?php

use App\Brain\Queries\GetCollaboratorPayoutsQuery;
use App\Models\Invoice;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('lang/{locale}', function ($locale) {
    if (in_array($locale, ['en_GB', 'pt_BR'])) {
        session(['locale' => $locale]);
        if (auth()->check()) {
            auth()->user()->update(['locale' => $locale]);
        }
    }

    return redirect()->back();
})->name('lang.switch');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'dashboard')->name('dashboard');
    Route::view('subscription-expired', 'subscription-expired')->name('subscription.expired');
    Route::livewire('superadmin', 'superadmin')->name('superadmin');
    Route::livewire('customers', 'customer')->name('customers');
    Route::livewire('customers/form/{id?}', 'customer-form')->name('customers.form');
    Route::livewire('customers/{id}/addresses', 'service-addresses')->name('customers.addresses');
    Route::livewire('agenda', 'agenda')->name('agenda');
    Route::livewire('invoices', 'invoices')->name('invoices');
    Route::livewire('service-types', 'service-types')->name('service-types');
    Route::livewire('collaborators', 'collaborators')->name('collaborators');
    Route::livewire('reports', 'collaborator-reports')->name('reports');

    Route::get('reports/{id}/pdf', function ($id) {
        app()->setLocale('en');

        $user = User::findOrFail($id);
        $startDate = request('start_date');
        $endDate = request('end_date');
        $addressType = request('address_type', 'all');
        $payoutStatus = request('payout_status', 'all');

        $services = GetCollaboratorPayoutsQuery::run(
            companyId: $user->company_id,
            startDate: $startDate,
            endDate: $endDate,
            userId: (int) $id,
            payoutStatus: $payoutStatus,
            addressType: $addressType
        );

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $pdf = Pdf::loadView('pdf.collaborator-report', [
            'user' => $user,
            'services' => $services,
            'startDate' => $start->format('d/m/Y'),
            'endDate' => $end->format('d/m/Y'),
            'company' => $user->company,
        ]);

        return $pdf->download('payout-report-'.strtolower(str_replace(' ', '-', $user->name)).'-'.$start->format('d-m-Y').'.pdf');
    })->name('reports.pdf');

    Route::get('invoices/{id}/pdf', function ($id) {
        app()->setLocale('en');

        $invoice = Invoice::with(['customer', 'company', 'items'])->findOrFail($id);

        // Secure access: must belong to the user's company
        if ($invoice->company_id !== auth()->user()->company->id) {
            abort(403);
        }

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
        ]);

        $fileName = strtolower($invoice->number).($invoice->status === 'draft' ? '-draft' : '').'.pdf';

        return $pdf->download($fileName);
    })->name('invoices.pdf');
});

require __DIR__.'/settings.php';
