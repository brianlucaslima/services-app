<?php

use App\Models\Invoice;
use App\Models\ServiceInstance;
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

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $query = ServiceInstance::where('company_id', $user->company_id)
            ->where('status', 'completed')
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->whereHas('users', fn ($q) => $q->where('users.id', $id));

        if ($addressType !== 'all') {
            $query->whereHas('address', fn ($q) => $q->where('type', $addressType));
        }

        if ($payoutStatus !== 'all') {
            $query->where('payout_status', $payoutStatus);
        }

        $instances = $query->with(['address.customer', 'customer', 'users'])
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        $services = $instances->map(fn ($inst) => [
            'date' => $inst->date->format('d/m/Y'),
            'time' => substr($inst->time, 0, 5),
            'customer_name' => $inst->customer?->name ?? ($inst->address?->customer?->name ?? ''),
            'location' => $inst->address?->label ?? '',
            'location_type' => $inst->address?->type ?? 'house',
            'description' => $inst->description,
            'total_duration' => $inst->duration_hours,
            'team_count' => $inst->users->count() ?: 1,
            'share_hours' => $inst->duration_hours / ($inst->users->count() ?: 1),
            'hourly_rate' => $user->hourly_rate,
            'payout' => $user->hourly_rate * ($inst->duration_hours / ($inst->users->count() ?: 1)),
            'payout_status' => $inst->payout_status ?? 'unpaid',
        ]);

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
