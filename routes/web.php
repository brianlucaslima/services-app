<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('customers', 'customer')->name('customers');
    Route::livewire('customers/form', 'customer-form')->name('customers.form');
});

require __DIR__.'/settings.php';
