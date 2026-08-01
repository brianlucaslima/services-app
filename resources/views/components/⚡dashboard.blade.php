<?php

use App\Models\User;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceInstance;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Carbon;

new class extends Component
{
    public ?int $filterMonth = null;
    public ?int $filterYear = null;

    public function mount(): void
    {
        if (auth()->user()->role === 'superadmin') {
            $this->redirect(route('superadmin'), navigate: true);
        }

        $this->filterMonth = (int) now()->month;
        $this->filterYear = (int) now()->year;
    }

    public function rendering($view): void
    {
        $view->title(__('Dashboard'));
    }

    #[Computed]
    public function isManagement(): bool
    {
        return auth()->user()->role === 'management';
    }

    #[Computed]
    public function metrics(): array
    {
        if (! auth()->user()->company_id) {
            return [
                'monthlyRevenue' => 0,
                'pendingPayout' => 0,
                'activeCustomers' => 0,
                'completedServices' => 0,
                'completedHours' => 0,
                'earningsThisMonth' => 0,
                'assignedSchedules' => 0,
            ];
        }

        return \App\Brain\Queries\GetDashboardMetricsQuery::run(
            companyId: (int) auth()->user()->company_id,
            userId: auth()->id(),
            role: auth()->user()->role,
            month: $this->filterMonth,
            year: $this->filterYear
        );
    }

    #[Computed]
    public function recentInvoices()
    {
        if (! $this->isManagement() || ! auth()->user()->company_id) {
            return [];
        }

        return Invoice::where('company_id', auth()->user()->company_id)
            ->with('customer')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();
    }

    #[Computed]
    public function recentServices()
    {
        $companyId = auth()->user()->company_id;
        if (! $companyId) {
            return [];
        }
        $userId = auth()->id();

        $query = ServiceInstance::where('company_id', $companyId)
            ->with(['address.customer', 'customer', 'users']);

        if (!$this->isManagement()) {
            $query->whereHas('users', fn($q) => $q->where('users.id', $userId));
        }

        return $query->orderBy('date', 'desc')
            ->orderBy('time', 'desc')
            ->take(5)
            ->get();
    }

    #[Computed]
    public function topCollaborators()
    {
        if (! $this->isManagement() || ! auth()->user()->company_id) {
            return [];
        }

        return \App\Brain\Queries\GetTopCollaboratorsQuery::run(
            companyId: auth()->user()->company_id,
            month: $this->filterMonth,
            year: $this->filterYear
        );
    }
};

?>

<div class="mx-auto max-w-none w-full px-4 sm:px-6 lg:px-8 space-y-8 pb-24">
    <!-- Header Greeting -->
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 sm:px-0">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">
                {{ __('Hello, :name!', ['name' => auth()->user()->name]) }}
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                @if ($this->isManagement)
                    {{ __('Welcome to your business dashboard. Here is an overview of everything today.') }}
                @else
                    {{ __('Welcome to your personal dashboard. Track your schedules and hours below.') }}
                @endif
            </p>
        </div>

        <!-- Month and Year Filters -->
        <div class="flex items-center gap-3">
            <flux:select wire:model.live="filterMonth" class="w-40" size="sm">
                @foreach(range(1, 12) as $m)
                    <flux:select.option value="{{ $m }}">{{ \Illuminate\Support\Carbon::create(null, $m, 1)->translatedFormat('F') }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filterYear" class="w-28" size="sm">
                @foreach(range(now()->year - 2, now()->year + 1) as $y)
                    <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </header>

    @php
        $selectedMonthName = \Illuminate\Support\Carbon::create(null, $filterMonth, 1)->translatedFormat('F');
    @endphp

    <!-- Metrics Cards Grid -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        @if ($this->isManagement)
            <!-- Monthly Revenue Card -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-sm relative overflow-hidden flex flex-col justify-between h-32">
                <div>
                    <span class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">{{ __('Monthly Revenue') }}</span>
                    <h3 class="text-2xl font-bold text-zinc-900 dark:text-white mt-1">{{ Number::currency($this->metrics['monthlyRevenue'], 'GBP') }}</h3>
                </div>
                <div class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                    {{ __('Invoiced in :month', ['month' => $selectedMonthName]) }}
                </div>
                <div class="absolute right-4 top-4 text-emerald-500/20">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>

            <!-- Pending Payouts Card -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-sm relative overflow-hidden flex flex-col justify-between h-32">
                <div>
                    <span class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">{{ __('Pending Payouts') }}</span>
                    <h3 class="text-2xl font-bold text-zinc-900 dark:text-white mt-1">{{ Number::currency($this->metrics['pendingPayout'], 'GBP') }}</h3>
                </div>
                <div class="text-xs text-amber-600 dark:text-amber-400 font-medium">
                    {{ __('Amount to pay collaborators') }}
                </div>
                <div class="absolute right-4 top-4 text-amber-500/20">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>

            <!-- Active Customers Card -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-sm relative overflow-hidden flex flex-col justify-between h-32">
                <div>
                    <span class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">{{ __('Active Customers') }}</span>
                    <h3 class="text-2xl font-bold text-zinc-900 dark:text-white mt-1">{{ $this->metrics['activeCustomers'] }}</h3>
                </div>
                <div class="text-xs text-blue-600 dark:text-blue-400 font-medium">
                    {{ __('Registered active contacts') }}
                </div>
                <div class="absolute right-4 top-4 text-blue-500/20">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>

            <!-- Completed Services Card -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-sm relative overflow-hidden flex flex-col justify-between h-32">
                <div>
                    <span class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">{{ __('Completed Services') }}</span>
                    <h3 class="text-2xl font-bold text-zinc-900 dark:text-white mt-1">{{ $this->metrics['completedServices'] }}</h3>
                </div>
                <div class="text-xs text-purple-600 dark:text-purple-400 font-medium">
                    {{ __('Services completed in :month', ['month' => $selectedMonthName]) }}
                </div>
                <div class="absolute right-4 top-4 text-purple-500/20">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
            </div>
        @else
            <!-- Collaborator Metrics -->
            <!-- Hours Worked -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-sm relative overflow-hidden flex flex-col justify-between h-32">
                <div>
                    <span class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">{{ __('Your Hours Work') }}</span>
                    <h3 class="text-2xl font-bold text-zinc-900 dark:text-white mt-1">{{ number_format($this->metrics['completedHours'], 2) }}h</h3>
                </div>
                <div class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                    {{ __('Completed hours in :month', ['month' => $selectedMonthName]) }}
                </div>
                <div class="absolute right-4 top-4 text-emerald-500/20">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>

            <!-- Your Earnings -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-sm relative overflow-hidden flex flex-col justify-between h-32">
                <div>
                    <span class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">{{ __('Your Earnings') }}</span>
                    <h3 class="text-2xl font-bold text-zinc-900 dark:text-white mt-1">{{ Number::currency($this->metrics['earningsThisMonth'], 'GBP') }}</h3>
                </div>
                <div class="text-xs text-blue-600 dark:text-blue-400 font-medium">
                    {{ __('Earnings in :month based on your rate', ['month' => $selectedMonthName]) }}
                </div>
                <div class="absolute right-4 top-4 text-blue-500/20">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>

            <!-- Your Pending Payout -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-sm relative overflow-hidden flex flex-col justify-between h-32">
                <div>
                    <span class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">{{ __('Your Pending Payout') }}</span>
                    <h3 class="text-2xl font-bold text-zinc-900 dark:text-white mt-1">{{ Number::currency($this->metrics['pendingPayout'], 'GBP') }}</h3>
                </div>
                <div class="text-xs text-amber-600 dark:text-amber-400 font-medium">
                    {{ __('Awaiting payments') }}
                </div>
                <div class="absolute right-4 top-4 text-amber-500/20">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>

            <!-- Assigned Schedules -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-sm relative overflow-hidden flex flex-col justify-between h-32">
                <div>
                    <span class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">{{ __('Your Schedules') }}</span>
                    <h3 class="text-2xl font-bold text-zinc-900 dark:text-white mt-1">{{ $this->metrics['assignedSchedules'] }}</h3>
                </div>
                <div class="text-xs text-purple-600 dark:text-purple-400 font-medium">
                    {{ __('Schedules assigned to you') }}
                </div>
                <div class="absolute right-4 top-4 text-purple-500/20">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            </div>
        @endif
    </div>

    <!-- Main Content Layout Section -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Tables column -->
        <div class="space-y-6 lg:col-span-2">
            <!-- Recent Completed / Upcoming Services -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="p-5 border-b border-zinc-150 dark:border-zinc-850 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-white">{{ __('Recent Completed / Assigned Services') }}</h2>
                    <a href="{{ route('agenda') }}" class="text-xs font-semibold text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 hover:underline">{{ __('View Full Agenda') }} →</a>
                </div>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                        <flux:table.column>{{ __('Customer / Location') }}</flux:table.column>
                        @if ($this->isManagement)
                            <flux:table.column>{{ __('Team') }}</flux:table.column>
                        @endif
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($this->recentServices as $srv)
                            <flux:table.row :key="'srv-'.$srv->id">
                                <flux:table.cell class="font-semibold text-zinc-900 dark:text-white">
                                    {{ $srv->date->format('d/m/Y') }}
                                    <span class="block text-xs font-normal text-zinc-400 mt-0.5">{{ substr($srv->time, 0, 5) }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span class="block font-medium text-zinc-800 dark:text-zinc-200">{{ $srv->customer?->name ?? ($srv->address?->customer?->name ?? 'N/A') }}</span>
                                    <span class="block text-xs text-zinc-400 mt-0.5">{{ $srv->address?->label ?? $srv->description }}</span>
                                </flux:table.cell>
                                @if ($this->isManagement)
                                    <flux:table.cell>
                                        <div class="flex items-center gap-1.5">
                                            @foreach($srv->users as $u)
                                                <flux:tooltip :content="$u->name" position="top">
                                                    <span class="w-6 h-6 rounded-full bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center text-[10px] font-bold text-zinc-600 dark:text-zinc-400">
                                                        {{ $u->initials() }}
                                                    </span>
                                                </flux:tooltip>
                                            @endforeach
                                        </div>
                                    </flux:table.cell>
                                @endif
                                <flux:table.cell>
                                    @if ($srv->status === 'completed')
                                        <flux:badge size="sm" color="emerald" inset="top">{{ __('Completed') }}</flux:badge>
                                    @elseif ($srv->status === 'skipped')
                                        <flux:badge size="sm" color="zinc" inset="top">{{ __('Skipped') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="amber" inset="top">{{ __('Pending') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center py-6 text-zinc-400 text-sm">
                                    {{ __('No service activities registered yet.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            <!-- Recent Invoices Table (Management only) -->
            @if ($this->isManagement)
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden shadow-sm">
                    <div class="p-5 border-b border-zinc-150 dark:border-zinc-850 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-zinc-900 dark:text-white">{{ __('Recent Invoices') }}</h2>
                        <a href="{{ route('invoices') }}" class="text-xs font-semibold text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 hover:underline">{{ __('Manage Invoices') }} →</a>
                    </div>
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Number') }}</flux:table.column>
                            <flux:table.column>{{ __('Customer') }}</flux:table.column>
                            <flux:table.column>{{ __('Amount') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse($this->recentInvoices as $inv)
                                <flux:table.row :key="'inv-'.$inv->id">
                                    <flux:table.cell class="font-semibold text-zinc-900 dark:text-white">
                                        {{ $inv->number }}
                                        <span class="block text-xs font-normal text-zinc-400 mt-0.5">{{ $inv->date->format('d/m/Y') }}</span>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $inv->customer->name }}</span>
                                    </flux:table.cell>
                                    <flux:table.cell class="font-semibold text-zinc-900 dark:text-white">
                                        {{ Number::currency($inv->total_amount, 'GBP') }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if ($inv->status === 'paid')
                                            <flux:badge size="sm" color="emerald" inset="top">{{ __('Paid') }}</flux:badge>
                                        @elseif ($inv->status === 'sent')
                                            <flux:badge size="sm" color="blue" inset="top">{{ __('Sent') }}</flux:badge>
                                        @elseif ($inv->status === 'cancelled')
                                            <flux:badge size="sm" color="red" inset="top">{{ __('Cancelled') }}</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc" inset="top">{{ __('Draft') }}</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="4" class="text-center py-6 text-zinc-400 text-sm">
                                        {{ __('No invoices created yet.') }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endif
        </div>

        <!-- Sidebar list columns -->
        <div class="space-y-6">
            <!-- List of top collabs (Management only) -->
            @if ($this->isManagement)
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-sm space-y-4">
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-white">{{ __('Top Collaborators') }} ({{ $selectedMonthName }})</h2>
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse($this->topCollaborators as $item)
                            <div class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                                <span class="w-9 h-9 rounded-full bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center text-xs font-bold text-zinc-600 dark:text-zinc-400">
                                    {{ $item['user']->initials() }}
                                </span>
                                <div class="flex-1 min-w-0">
                                    <span class="block font-semibold text-sm text-zinc-800 dark:text-zinc-200 truncate">{{ $item['user']->name }}</span>
                                    <span class="block text-xs text-zinc-400 truncate">{{ __('Hourly Rate') }}: {{ Number::currency($item['user']->hourly_rate, 'GBP') }}/h</span>
                                </div>
                                <div class="text-right">
                                    <span class="block text-sm font-bold text-zinc-900 dark:text-white">{{ number_format($item['hours'], 1) }}h</span>
                                    <span class="block text-[10px] text-zinc-400 mt-0.5">{{ Number::currency($item['payout'], 'GBP') }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="text-zinc-400 text-xs py-2">{{ __('No collaborator activities registered yet.') }}</p>
                        @endforelse
                    </div>
                </div>
            @endif

            <!-- Quick Actions -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-sm space-y-4">
                <h2 class="text-lg font-bold text-zinc-900 dark:text-white">{{ __('Quick Actions') }}</h2>
                <div class="grid grid-cols-1 gap-2.5">
                    @if ($this->isManagement)
                        <flux:button href="{{ route('customers') }}" variant="ghost" class="w-full justify-start text-left" icon="users">
                            {{ __('Manage Customers') }}
                        </flux:button>
                        <flux:button href="{{ route('agenda') }}" variant="ghost" class="w-full justify-start text-left" icon="calendar">
                            {{ __('Schedule Service') }}
                        </flux:button>
                        <flux:button href="{{ route('invoices') }}" variant="ghost" class="w-full justify-start text-left" icon="document-text">
                            {{ __('Generate Invoice') }}
                        </flux:button>
                        <flux:button href="{{ route('reports') }}" variant="ghost" class="w-full justify-start text-left" icon="document-chart-bar">
                            {{ __('Payout Reports') }}
                        </flux:button>
                        <flux:button href="{{ route('company.edit') }}" variant="ghost" class="w-full justify-start text-left" icon="cog">
                            {{ __('Company Settings') }}
                        </flux:button>
                    @else
                        <flux:button href="{{ route('agenda') }}" variant="ghost" class="w-full justify-start text-left" icon="calendar">
                            {{ __('My Schedule') }}
                        </flux:button>
                        <flux:button href="{{ route('profile.edit') }}" variant="ghost" class="w-full justify-start text-left" icon="cog">
                            {{ __('My Profile') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
