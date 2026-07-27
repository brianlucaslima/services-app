<?php

namespace App\Livewire\Settings;

use App\Models\Company as CompanyModel;
use Flux\Flux;
use Livewire\Component;
use Livewire\WithFileUploads;

class Company extends Component
{
    use WithFileUploads;

    public CompanyModel $company;

    public $name = '';

    public $email = '';

    public $phone = '';

    public $address = '';

    public $payment_name = '';

    public $payment_account_number = '';

    public $payment_sort_code = '';

    public $default_invoice_message = '';

    public $default_quote_message = '';

    public $logo;

    public $primary_color = '#18181b';

    public $invoice_start_number = 1;

    public $quote_start_number = 0;

    public function mount(): void
    {
        if (auth()->user()->role !== 'management') {
            abort(403);
        }

        $this->company = auth()->user()->company;
        $this->name = $this->company->name;
        $this->email = $this->company->email;
        $this->phone = $this->company->phone;
        $this->address = $this->company->address;
        $this->payment_name = $this->company->payment_name;
        $this->payment_account_number = $this->company->payment_account_number;
        $this->payment_sort_code = $this->company->payment_sort_code;
        $this->default_invoice_message = $this->company->default_invoice_message;
        $this->default_quote_message = $this->company->default_quote_message;
        $this->primary_color = $this->company->primary_color ?? '#18181b';
        $this->invoice_start_number = $this->company->invoice_start_number ?? 1;
        $this->quote_start_number = $this->company->quote_start_number ?? 0;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'payment_name' => 'nullable|string|max:255',
            'payment_account_number' => 'nullable|string|max:255',
            'payment_sort_code' => 'nullable|string|max:255',
            'default_invoice_message' => 'nullable|string',
            'default_quote_message' => 'nullable|string',
            'logo' => 'nullable|image|max:1024',
            'primary_color' => 'required|string|max:10',
            'invoice_start_number' => 'required|integer|min:0',
            'quote_start_number' => 'required|integer|min:0',
        ]);

        if ($this->logo) {
            $validated['logo'] = $this->logo->store('logos', 'public');
        } else {
            unset($validated['logo']);
        }

        $this->company->update($validated);

        $this->reset('logo');

        Flux::toast(variant: 'success', text: __('Company details updated.'));
    }

    public function render()
    {
        return view('livewire.settings.company');
    }
}
