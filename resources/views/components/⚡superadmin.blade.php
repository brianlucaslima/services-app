<?php

use App\Models\Company;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Flux\Flux;

new class extends Component
{
    public string $search = '';

    public function mount(): void
    {
        if (auth()->user()->role !== 'superadmin') {
            abort(403);
        }
    }

    public function rendering($view): void
    {
        $view->title(__('Superadmin'));
    }

    #[Computed]
    public function companies()
    {
        return Company::with('user')
            ->when($this->search !== '', function($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhereHas('user', function($q) {
                        $q->where('name', 'like', "%{$this->search}%");
                    });
            })
            ->latest()
            ->get();
    }

    public function extend30Days(int $companyId): void
    {
        \App\Brain\Subscriptions\Workflows\ExtendSubscriptionWorkflow::run([
            'companyId' => $companyId,
            'status' => 'active',
            'daysToExtend' => 30,
        ]);

        Flux::toast(variant: 'success', text: __('Subscription extended by 30 days.'));
    }

    public function extend1Year(int $companyId): void
    {
        \App\Brain\Subscriptions\Workflows\ExtendSubscriptionWorkflow::run([
            'companyId' => $companyId,
            'status' => 'active',
            'daysToExtend' => 365,
        ]);

        Flux::toast(variant: 'success', text: __('Subscription extended by 1 year.'));
    }

    public function suspendAccess(int $companyId): void
    {
        \App\Brain\Subscriptions\Workflows\ExtendSubscriptionWorkflow::run([
            'companyId' => $companyId,
            'status' => 'expired',
        ]);

        Flux::toast(variant: 'danger', text: __('Subscription suspended.'));
    }

    public function loginAsCompanyOwner(int $companyId): mixed
    {
        $company = Company::findOrFail($companyId);
        
        if (!$company->user_id) {
            Flux::toast(variant: 'danger', text: __('Company has no master user associated.'));
            return null;
        }

        // Login as the company owner using Laravel's loginUsingId
        auth()->loginUsingId($company->user_id);

        // Redirect to the dashboard!
        return redirect()->route('dashboard');
    }
};

?>

<div class="mx-auto max-w-5xl space-y-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between sm:px-0">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ __('Superadmin Dashboard') }}</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">{{ __('Manage company subscriptions and system-wide activations.') }}</p>
        </div>
    </header>

    <!-- Filters and Search -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between sm:px-0">
        <div class="relative w-full sm:w-72">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="h-5 w-5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
            </div>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search companies...') }}" class="block w-full rounded-lg border-0 py-2 pl-10 pr-3 text-zinc-900 shadow-sm ring-1 ring-inset ring-zinc-300 placeholder:text-zinc-400 focus:ring-2 focus:ring-inset focus:ring-zinc-900 dark:bg-zinc-900 dark:text-white dark:ring-zinc-700 dark:placeholder:text-zinc-500 dark:focus:ring-white sm:text-sm sm:leading-6" />
        </div>
    </div>

    <!-- Company subscriptions list -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden shadow-sm">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Company') }}</flux:table.column>
                <flux:table.column>{{ __('Owner') }}</flux:table.column>
                <flux:table.column>{{ __('Subscription Status') }}</flux:table.column>
                <flux:table.column>{{ __('Expires At') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            
            <flux:table.rows>
                @forelse($this->companies as $company)
                    <flux:table.row :key="'company-'.$company->id">
                        <flux:table.cell class="font-semibold text-zinc-900 dark:text-white">
                            {{ $company->name }}
                            <span class="block text-xs font-normal text-zinc-400 mt-0.5">{{ $company->email }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $company->user?->name ?? 'N/A' }}</span>
                            <span class="block text-xs text-zinc-400 mt-0.5">{{ $company->user?->email }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $isExpired = $company->subscription_ends_at && now()->gt($company->subscription_ends_at);
                            @endphp
                            
                            @if ($isExpired)
                                <flux:badge size="sm" color="red" inset="top">{{ __('Expired') }}</flux:badge>
                            @elseif ($company->subscription_status === 'active')
                                <flux:badge size="sm" color="emerald" inset="top">{{ __('Active') }}</flux:badge>
                            @elseif ($company->subscription_status === 'trial')
                                <flux:badge size="sm" color="blue" inset="top">{{ __('Trial') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc" inset="top">{{ __($company->subscription_status) }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="font-medium text-zinc-700 dark:text-zinc-300">
                            {{ $company->subscription_ends_at ? $company->subscription_ends_at->format('d/m/Y H:i') : __('Lifetime') }}
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <flux:button wire:click="loginAsCompanyOwner({{ $company->id }})" variant="outline" size="xs" icon="arrow-right-start-on-rectangle" title="{{ __('Login as Owner') }}">
                                    {{ __('Login') }}
                                </flux:button>
                                <flux:button wire:click="extend30Days({{ $company->id }})" variant="outline" size="xs" icon="plus" title="{{ __('Add 30 Days') }}">
                                    +30 {{ __('Days') }}
                                </flux:button>
                                <flux:button wire:click="extend1Year({{ $company->id }})" variant="outline" size="xs" icon="plus" title="{{ __('Add 1 Year') }}">
                                    +1 {{ __('Year') }}
                                </flux:button>
                                @if (!$isExpired)
                                    <flux:button wire:click="suspendAccess({{ $company->id }})" variant="danger" size="xs" icon="x-mark" title="{{ __('Suspend') }}">
                                        {{ __('Suspend') }}
                                    </flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-6 text-zinc-400 text-sm">
                            {{ __('No companies registered yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
