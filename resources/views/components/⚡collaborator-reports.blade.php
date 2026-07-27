<?php

use App\Models\User;
use App\Models\ServiceInstance;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Flux\Flux;

new class extends Component
{
    public string $startDate;
    public string $endDate;
    public string $calendarId = 'all'; // all or calendar_id
    public string $payoutStatus = 'unpaid'; // all, unpaid, paid
    public array $collaboratorSummary = [];
    public array $detailServices = [];
    public array $selectedInstanceIds = [];
    public ?int $selectedUserId = null;

    public function mount(): void
    {
        if (auth()->user()->role !== 'management') {
            abort(403);
        }

        $this->startDate = now()->startOfWeek()->format('Y-m-d');
        $this->endDate = now()->endOfWeek()->format('Y-m-d');
        $this->refreshReports();
    }

    public function rendering($view): void
    {
        $view->title(__('Collaborator Reports'));
    }

    public function updatedStartDate(): void { $this->refreshReports(); }
    public function updatedEndDate(): void { $this->refreshReports(); }
    public function updatedCalendarId(): void { $this->refreshReports(); }
    public function updatedPayoutStatus(): void { $this->refreshReports(); }

    public function nextWeek(): void
    {
        $start = Carbon::parse($this->startDate)->addWeek()->startOfWeek();
        $this->startDate = $start->format('Y-m-d');
        $this->endDate = $start->copy()->endOfWeek()->format('Y-m-d');
        $this->refreshReports();
    }

    public function prevWeek(): void
    {
        $start = Carbon::parse($this->startDate)->subWeek()->startOfWeek();
        $this->startDate = $start->format('Y-m-d');
        $this->endDate = $start->copy()->endOfWeek()->format('Y-m-d');
        $this->refreshReports();
    }

    public function goToToday(): void
    {
        $this->startDate = now()->startOfWeek()->format('Y-m-d');
        $this->endDate = now()->endOfWeek()->format('Y-m-d');
        $this->refreshReports();
    }

    public function selectCollaborator(int $id): void
    {
        $this->selectedUserId = $id;
        $this->loadDetailServices();
    }

    public function closeDetail(): void
    {
        $this->selectedUserId = null;
        $this->detailServices = [];
        $this->selectedInstanceIds = [];
    }

    public function refreshReports(): void
    {
        $this->collaboratorSummary = \App\Brain\Queries\GetCollaboratorPayoutsQuery::run(
            companyId: auth()->user()->company->id,
            startDate: $this->startDate,
            endDate: $this->endDate,
            payoutStatus: $this->payoutStatus,
            calendarId: $this->calendarId
        );

        if ($this->selectedUserId) {
            $this->loadDetailServices();
        }
    }

    public function loadDetailServices(): void
    {
        $this->detailServices = \App\Brain\Queries\GetCollaboratorPayoutsQuery::run(
            companyId: auth()->user()->company->id,
            startDate: $this->startDate,
            endDate: $this->endDate,
            userId: $this->selectedUserId,
            payoutStatus: $this->payoutStatus,
            calendarId: $this->calendarId
        );
        
        $this->selectedInstanceIds = array_column(array_filter($this->detailServices, fn($s) => $s['payout_status'] === 'unpaid'), 'id');
    }

    public function markSelectedAsPaid(): void
    {
        if (empty($this->selectedInstanceIds)) {
            Flux::toast(variant: 'danger', text: __('Select at least one record.'));
            return;
        }

        ServiceInstance::whereIn('id', $this->selectedInstanceIds)->update([
            'payout_status' => 'paid',
            'payout_date' => now()->format('Y-m-d')
        ]);

        $this->selectedInstanceIds = [];
        $this->refreshReports();
        Flux::toast(variant: 'success', text: __('Payout marked as paid successfully.'));
    }

    #[Computed]
    public function selectedUser()
    {
        return $this->selectedUserId ? auth()->user()->company->users()->find($this->selectedUserId) : null;
    }

    #[Computed]
    public function calendars()
    {
        return auth()->user()->company->calendars()->orderBy('name')->get();
    }
};

?>

<div class="mx-auto max-w-5xl space-y-6 pb-24">
    @if(!$selectedUserId)
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between sm:px-0">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('Collaborator Payouts') }}</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Track completed hours and amounts to pay by custom filters.') }}</p>
            </div>

            <div class="flex items-center gap-2">
                <flux:button.group>
                    <flux:button wire:click="prevWeek" icon="chevron-left" variant="outline" />
                    <flux:button wire:click="goToToday" variant="outline">{{ __('Today') }}</flux:button>
                    <flux:button wire:click="nextWeek" icon="chevron-right" variant="outline" />
                </flux:button.group>
            </div>
        </header>

        <!-- Filters section -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800">
            <flux:field>
                <flux:label>{{ __('Start Date') }}</flux:label>
                <flux:input type="date" wire:model.live="startDate" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('End Date') }}</flux:label>
                <flux:input type="date" wire:model="endDate" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Calendar & Location Type') }}</flux:label>
                <flux:select wire:model.live="calendarId">
                    <flux:select.option value="all">{{ __('All Calendars') }}</flux:select.option>
                    @foreach($this->calendars as $cal)
                        <flux:select.option value="{{ $cal->id }}">{{ $cal->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Payment Status') }}</flux:label>
                <flux:select wire:model.live="payoutStatus">
                    <flux:select.option value="unpaid">{{ __('Unpaid') }}</flux:select.option>
                    <flux:select.option value="paid">{{ __('Paid') }}</flux:select.option>
                    <flux:select.option value="all">{{ __('All Status') }}</flux:select.option>
                </flux:select>
            </flux:field>
        </div>

        <div class="bg-white dark:bg-zinc-900 border-y sm:border border-zinc-200 dark:border-zinc-700 sm:rounded-xl overflow-hidden shadow-sm">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Collaborator') }}</flux:table.column>
                    <flux:table.column>{{ __('Rate') }}</flux:table.column>
                    <flux:table.column>{{ __('Completed Hours') }}</flux:table.column>
                    <flux:table.column>{{ __('Total Payout') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($collaboratorSummary as $collab)
                        <flux:table.row :key="$collab['id']">
                            <flux:table.cell>
                                <span class="block font-semibold text-zinc-900 dark:text-white">{{ $collab['name'] }}</span>
                                <span class="block text-xs text-zinc-500">{{ $collab['email'] }}</span>
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-600 dark:text-zinc-400">
                                <span class="block text-xs text-zinc-500">{{ __('House') }}: {{ Number::currency($collab['hourly_rate_house'], 'GBP') }}/h</span>
                                <span class="block text-xs text-zinc-500">{{ __('Office') }}: {{ Number::currency($collab['hourly_rate_office'], 'GBP') }}/h</span>
                            </flux:table.cell>
                            <flux:table.cell class="font-medium">
                                {{ number_format($collab['hours'], 2) }}h ({{ $collab['services_count'] }} {{ __('services') }})
                            </flux:table.cell>
                            <flux:table.cell class="font-bold text-red-600 dark:text-red-400">
                                {{ Number::currency($collab['payout'], 'GBP') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button wire:click="selectCollaborator({{ $collab['id'] }})" size="sm" variant="ghost" icon="eye" />
                            </flux:cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            @if(empty($collaboratorSummary))
                <div class="p-12 text-center text-zinc-500 dark:text-zinc-400">
                    <flux:icon.users class="w-12 h-12 mx-auto mb-4 opacity-20" />
                    <p>{{ __('No completed services found in this period.') }}</p>
                </div>
            @endif
        </div>
    @else
        <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 px-4 sm:px-0">
            <div class="flex items-center gap-3">
                <flux:button wire:click="closeDetail" variant="ghost" icon="chevron-left" size="sm" class="rounded-full" />
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ $this->selectedUser->name }}</h1>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">{{ __('Period Work Report') }} ({{ Carbon::parse($startDate)->format('d/m/Y') }} - {{ Carbon::parse($endDate)->format('d/m/Y') }})</p>
                </div>
            </div>

            <a href="{{ route('reports.pdf', ['id' => $selectedUserId, 'start_date' => $startDate, 'end_date' => $endDate, 'calendar_id' => $calendarId, 'payout_status' => $payoutStatus]) }}" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-lg bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-zinc-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zinc-900 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200 w-full sm:w-auto">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 015.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                </svg>
                {{ __('Download PDF') }}
            </a>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                <span class="text-xs text-zinc-500 uppercase tracking-wider">{{ __('Hourly Rate') }}</span>
                <div class="mt-1 space-y-1">
                    @foreach($this->calendars as $cal)
                        <div class="text-sm font-semibold text-zinc-550 dark:text-zinc-400">
                            {{ $cal->name }}: <span class="text-base font-bold text-zinc-950 dark:text-white">{{ Number::currency($this->selectedUser->hourlyRateFor($cal->id), 'GBP') }}/h</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                <span class="text-xs text-zinc-500 uppercase tracking-wider">{{ __('Total Hours Work') }}</span>
                <span class="text-3xl font-extrabold text-zinc-950 dark:text-white mt-1">{{ number_format(collect($detailServices)->sum('share_hours'), 2) }}h</span>
            </div>
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                <span class="text-xs text-zinc-500 uppercase tracking-wider">{{ __('Total Amount to Pay') }}</span>
                <span class="text-3xl font-extrabold text-red-600 dark:text-red-400 mt-1">{{ Number::currency(collect($detailServices)->sum('payout'), 'GBP') }}</span>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-900 border-y sm:border border-zinc-200 dark:border-zinc-700 sm:rounded-xl overflow-hidden shadow-sm mt-6">
            <table class="w-full text-sm text-left">
                <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 uppercase text-[10px] tracking-wider">
                    <tr>
                        <th class="px-4 py-3 w-10">
                            <input type="checkbox" 
                                x-on:change="
                                    if ($el.checked) {
                                        $wire.selectedInstanceIds = @js(array_column(array_filter($detailServices, fn($s) => $s['payout_status'] === 'unpaid'), 'id'))
                                    } else {
                                        $wire.selectedInstanceIds = []
                                    }
                                "
                                class="rounded border-zinc-300"
                                {{ count($selectedInstanceIds) === count(array_filter($detailServices, fn($s) => $s['payout_status'] === 'unpaid')) && count(array_filter($detailServices, fn($s) => $s['payout_status'] === 'unpaid')) > 0 ? 'checked' : '' }}
                            />
                        </th>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('Service / Location') }}</th>
                        <th class="px-4 py-3">{{ __('Type') }}</th>
                        <th class="px-4 py-3">{{ __('Your Hours') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Payout') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse($detailServices as $service)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                            <td class="px-4 py-4 text-center">
                                @if($service['payout_status'] === 'unpaid')
                                    <input type="checkbox" wire:model.live="selectedInstanceIds" value="{{ $service['id'] }}" class="rounded border-zinc-300" />
                                @else
                                    <span class="inline-block w-4 h-4 text-emerald-500 font-bold">✓</span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <span class="font-medium text-zinc-900 dark:text-white block">{{ $service['date'] }}</span>
                                <span class="block text-xs text-zinc-500">{{ $service['time'] }}</span>
                            </td>
                            <td class="px-4 py-4">
                                <span class="block font-semibold text-zinc-950 dark:text-white">{{ $service['description'] }}</span>
                                <span class="block text-xs text-zinc-500">{{ $service['customer_name'] }} - {{ $service['location'] }}</span>
                            </td>
                            <td class="px-4 py-4">
                                <flux:badge size="sm" :color="$service['location_type'] === 'house' ? 'zinc' : 'blue'">{{ __($service['location_type']) }}</flux:badge>
                            </td>
                            <td class="px-4 py-4 font-semibold text-zinc-950 dark:text-white">
                                {{ number_format($service['share_hours'], 2) }}h <span class="text-xs font-normal text-zinc-400">/ {{ number_format($service['total_duration'], 2) }}h</span>
                            </td>
                            <td class="px-4 py-4">
                                <flux:badge size="sm" :color="$service['payout_status'] === 'paid' ? 'emerald' : 'zinc'">{{ __($service['payout_status']) }}</flux:badge>
                            </td>
                            <td class="px-4 py-4 text-right font-bold text-red-600 dark:text-red-400">
                                {{ Number::currency($service['payout'], 'GBP') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-zinc-400 italic">
                                <p>{{ __('No completed services found for this criteria.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(!empty($selectedInstanceIds))
            <div class="fixed bottom-0 left-0 right-0 bg-white dark:bg-zinc-900 border-t border-zinc-200 dark:border-zinc-700 p-4 sm:relative sm:border sm:rounded-2xl sm:p-6 shadow-lg sm:shadow-sm animate-in slide-in-from-bottom duration-300 z-50 mt-6">
                <div class="max-w-5xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-center sm:text-left">
                        <p class="text-xs text-zinc-500 uppercase tracking-wider">{{ __('Total Selected') }} ({{ count($selectedInstanceIds) }})</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400">
                            {{ Number::currency(collect($detailServices)->whereIn('id', $selectedInstanceIds)->sum('payout'), 'GBP') }}
                        </p>
                    </div>
                    
                    <flux:button wire:click="markSelectedAsPaid" variant="primary" class="py-3 px-8 rounded-xl font-bold w-full sm:w-auto">{{ __('Mark Selected as Paid') }}</flux:button>
                </div>
            </div>
        @endif
    @endif
</div>
