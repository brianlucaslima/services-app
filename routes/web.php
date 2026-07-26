<?php

use App\Http\Controllers\PdfController;
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

    Route::get('reports/{id}/pdf', [PdfController::class, 'collaboratorReport'])->name('reports.pdf');
    Route::get('invoices/{id}/pdf', [PdfController::class, 'invoice'])->name('invoices.pdf');
});

require __DIR__.'/settings.php';
