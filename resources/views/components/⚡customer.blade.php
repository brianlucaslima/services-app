<?php

use App\Models\Customer;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public string $search = '';

    public string $tab = 'active';

    public array $customers = [];

    public function mount(): void
    {
        $this->refreshCustomers();
    }

    public function rendering($view): void
    {
        $view->title(__('Customers'));
    }

    public function toggleStatus(int $id): void
    {
        $customer = Customer::query()->findOrFail($id);
        $customer->update(['is_active' => ! $customer->is_active]);
        $this->refreshCustomers();

        Flux::toast(variant: 'success', text: __('Customer status updated.'));
    }

    public function refreshCustomers(): void
    {
        $query = Customer::query()
            ->latest()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%");
                });
            })
            ->when($this->tab === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->tab === 'inactive', fn ($query) => $query->where('is_active', false));

        $this->customers = $query
            ->get(['id', 'name', 'phone', 'email', 'is_active', 'created_at'])
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'is_active' => (bool) $customer->is_active,
                'created_at' => $customer->created_at->diffForHumans(),
            ])
            ->toArray();
    }

    public function updatedSearch(): void
    {
        $this->refreshCustomers();
    }

    public function updatedTab(): void
    {
        $this->refreshCustomers();
    }
};

?>


<div class="mx-auto max-w-5xl space-y-6">

    <!-- 1. CABEÇALHO COMPACTO -->
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between sm:px-0">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-semibold text-zinc-900 dark:text-white sm:text-2xl">
                    {{ __('Customers') }}
                </h1>
                <span class="inline-flex items-center rounded-md bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-600 ring-1 ring-inset ring-zinc-500/10 dark:bg-zinc-400/10 dark:text-zinc-400 dark:ring-zinc-400/20">
                    {{ count($customers) }}
                </span>
            </div>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Manage your contacts and registrations in one place.') }}
            </p>
        </div>

        <!-- Botão visível APENAS em Desktop (Estilo padrão do Flux) -->
        <a href="{{ route('customers.form') }}" wire:navigate class="hidden sm:inline-flex items-center justify-center gap-2 rounded-lg bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-zinc-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zinc-900 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('New customer') }}
        </a>
    </header>

    <!-- 2. BARRA DE FILTROS & BUSCA -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between sm:px-0">
        <!-- Tabs de Status (Estilo Flux) -->
        <div class="flex w-full rounded-lg bg-zinc-100 p-1 dark:bg-zinc-900/80 sm:w-auto">
            <button type="button" wire:click="$set('tab', 'active')" class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-all sm:flex-initial {{ $tab === 'active' ? 'bg-white text-zinc-900 shadow-sm ring-1 ring-zinc-900/5 dark:bg-zinc-800 dark:text-white dark:ring-white/10' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
                {{ __('Active (plural)') }}
            </button>
            <button type="button" wire:click="$set('tab', 'inactive')" class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-all sm:flex-initial {{ $tab === 'inactive' ? 'bg-white text-zinc-900 shadow-sm ring-1 ring-zinc-900/5 dark:bg-zinc-800 dark:text-white dark:ring-white/10' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
                {{ __('Inactive (plural)') }}
            </button>
        </div>

        <!-- Campo de Pesquisa -->
        <div class="relative w-full sm:w-72">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="h-5 w-5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
            </div>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search customers...') }}" class="block w-full rounded-lg border-0 py-2 pl-10 pr-3 text-zinc-900 shadow-sm ring-1 ring-inset ring-zinc-300 placeholder:text-zinc-400 focus:ring-2 focus:ring-inset focus:ring-zinc-900 dark:bg-zinc-900 dark:text-white dark:ring-zinc-700 dark:placeholder:text-zinc-500 dark:focus:ring-white sm:text-sm sm:leading-6" />
        </div>
    </div>

    <!-- 3. LISTA DE CLIENTES -->
    <main class="border-y border-zinc-200 bg-white sm:rounded-xl sm:border sm:shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        @if (empty($customers))
            <div class="px-6 py-14 text-center text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('No customers found in this view.') }}
            </div>
        @else
            <ul role="list" class="divide-y divide-zinc-200 dark:divide-zinc-800 rounded-xl ">
                @foreach ($customers as $customer)
                    <li class="flex flex-col gap-y-4 px-4 py-5 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <div class="flex items-center gap-x-3">
                                <p class="text-sm font-semibold leading-6 text-zinc-900 dark:text-white">
                                    {{ $customer['name'] }}
                                </p>
                                @if ($customer['is_active'])
                                    <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-400 dark:ring-emerald-400/20">{{ __('Active') }}</span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-zinc-50 px-2 py-1 text-xs font-medium text-zinc-600 ring-1 ring-inset ring-zinc-500/10 dark:bg-zinc-400/10 dark:text-zinc-400 dark:ring-zinc-400/20">{{ __('Inactive') }}</span>
                                @endif
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                                @if($customer['email'])
                                    <span class="flex items-center gap-x-1.5 truncate">
                                        <svg class="h-4 w-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                                        {{ $customer['email'] }}
                                    </span>
                                @endif
                                @if($customer['phone'])
                                    <span class="flex items-center gap-x-1.5">
                                        <svg class="h-4 w-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/></svg>
                                        {{ $customer['phone'] }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-x-3 border-t border-zinc-200 pt-3 dark:border-zinc-700/60 sm:border-t-0 sm:pt-0">
                            <a href="{{ route('customers.form', ['id' => $customer['id']]) }}" wire:navigate class="flex-1 rounded-md bg-white px-3 py-1.5 text-center text-sm font-semibold text-zinc-900 shadow-sm ring-1 ring-inset ring-zinc-300 hover:bg-zinc-50 sm:flex-none dark:bg-zinc-800 dark:text-zinc-100 dark:ring-zinc-700 dark:hover:bg-zinc-700/50 transition">
                                {{ __('Edit') }}
                            </a>
                            <button type="button" wire:click="toggleStatus({{ $customer['id'] }})" class="flex-1 rounded-md bg-white px-3 py-1.5 text-center text-sm font-semibold text-zinc-900 shadow-sm ring-1 ring-inset ring-zinc-300 hover:bg-zinc-50 sm:flex-none dark:bg-zinc-800 dark:text-zinc-100 dark:ring-zinc-700 dark:hover:bg-zinc-700/50 transition">
                                {{ $customer['is_active'] ? __('Deactivate') : __('Activate') }}
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </main>

    <!-- 4. BOTÃO FLUTUANTE (FAB) - Mobile -->
    <a href="{{ route('customers.form') }}" wire:navigate
       class="sm:hidden fixed bottom-6 right-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-zinc-900 text-white shadow-xl hover:bg-zinc-800 active:scale-90 dark:bg-zinc-100 dark:text-zinc-900 transition-all duration-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zinc-900"
       aria-label="{{ __('Register new customer') }}">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
    </a>

</div>
