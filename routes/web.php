<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('customers', 'customer')->name('customers');
    Route::livewire('customers/form', 'customer-form')->name('customers.form');
    Route::livewire('customers/{id}/addresses', 'service-addresses')->name('customers.addresses');
    Route::livewire('agenda', 'agenda')->name('agenda');
    Route::livewire('invoices', 'invoices')->name('invoices');
    Route::livewire('service-types', 'service-types')->name('service-types');
});

require __DIR__.'/settings.php';
