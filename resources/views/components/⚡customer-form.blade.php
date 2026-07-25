<?php

use App\Http\Requests\StoreCustomerRequest;
use App\Models\Customer;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public bool $is_active = true;

    public int|null $customerId = null;

    public function mount(?int $id = null): void
    {
        $this->customerId = $id;

        if ($id) {
            $customer = Customer::query()->findOrFail($id);
            $this->name = $customer->name;
            $this->phone = $customer->phone ?? '';
            $this->email = $customer->email ?? '';
            $this->is_active = (bool) $customer->is_active;
        }
    }

    public function rendering($view): void
    {
        $view->title($this->customerId ? __('Edit customer') : __('New customer'));
    }

    public function rules(): array
    {
        return (new StoreCustomerRequest())->rules();
    }

    public function save(): void
    {
        $validated = $this->validate();
        $validated['is_active'] = $this->is_active;

        if ($this->customerId) {
            Customer::query()->findOrFail($this->customerId)->update($validated);
            Flux::toast(variant: 'success', text: __('Customer updated successfully.'));
        } else {
            Customer::create($validated);
            Flux::toast(variant: 'success', text: __('Customer created successfully.'));
        }

        $this->redirect(route('customers'), navigate: true);
    }
};

?>

<div class="mx-auto max-w-3xl rounded-[24px] border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900 sm:p-6">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                {{ $customerId ? __('Edit customer') : __('New customer') }}
            </h2>
            <p class="mt-2 text-sm leading-6 text-neutral-600 dark:text-neutral-400">
                {{ $customerId ? __('Update the client details and status.') : __('Create a new customer with all the essential information.') }}
            </p>
        </div>
        <a href="{{ route('customers') }}" wire:navigate class="rounded-full border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-700 transition hover:border-neutral-300 hover:bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800">
            {{ __('Back') }}
        </a>
    </div>

    <form wire:submit="save" class="mt-6 space-y-4 rounded-[20px] border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-800/70 sm:p-5">
        <div class="grid gap-3 sm:grid-cols-2">
            <flux:field class="sm:col-span-2">
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="name" type="text" required autofocus />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Phone') }}</flux:label>
                <flux:input wire:model="phone" type="tel" />
                <flux:error name="phone" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Email') }}</flux:label>
                <flux:input wire:model="email" type="email" />
                <flux:error name="email" />
            </flux:field>

            <div class="sm:col-span-2">
                <label class="flex items-center gap-3 rounded-[16px] border border-neutral-200 bg-white px-3 py-3 text-sm text-neutral-700 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300">
                    <input type="checkbox" wire:model="is_active" class="h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-500" />
                    <span>{{ __('Active customer') }}</span>
                </label>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('customers') }}" wire:navigate class="rounded-full border border-neutral-200 bg-white px-4 py-2 text-sm font-medium text-neutral-700 transition hover:border-neutral-300 hover:bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="rounded-full bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-700 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200">
                {{ $customerId ? __('Save changes') : __('Save customer') }}
            </button>
        </div>
    </form>
</div>

