<?php

use App\Models\ServiceSchedule;
use App\Models\ServiceInstance;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Flux\Flux;

new class extends Component
{
    public string $viewDate;
    public array $days = [];
    public float $weeklyHours = 0;
    public float $weeklyValue = 0;
    public float $weeklyPayout = 0;
    public string $filterCalendarId = 'all';

    #[Computed]
    public function calendars()
    {
        return auth()->user()->company->calendars()->orderBy('name')->get();
    }

    #[Computed]
    public function collaborators()
    {
        return auth()->user()->company->users()->orderBy('name')->get();
    }

    // Reschedule modal state
    public bool $showRescheduleModal = false;
    public bool $showCompletionModal = false;
    public ?int $selectedScheduleId = null;
    public ?int $selectedInstanceId = null;
    public string $selectedOriginalDate = '';
    public string $newDate = '';
    public string $newTime = '';
    public string $rescheduleMode = 'move'; // move or skip
    public string $notes = '';

    // Completion / edit state
    public array $completionUserIds = [];
    public $completionHours = 0;
    public $completionRate = 0;
    public string $completionBillingType = 'hourly';
    public bool $hasCompletedInstance = false;

    public function mount(): void
    {
        $this->viewDate = now()->startOfWeek()->format('Y-m-d');
        $this->refreshAgenda();
    }

    public function updatedViewDate(): void
    {
        $this->refreshAgenda();
    }

    public function updatedFilterCalendarId(): void
    {
        $this->refreshAgenda();
    }

    public function rendering($view): void
    {
        $view->title(__('Agenda'));
    }

    public function nextWeek(): void
    {
        $this->viewDate = Carbon::parse($this->viewDate)->addWeek()->format('Y-m-d');
        $this->refreshAgenda();
    }

    public function prevWeek(): void
    {
        $this->viewDate = Carbon::parse($this->viewDate)->subWeek()->format('Y-m-d');
        $this->refreshAgenda();
    }

    public function goToToday(): void
    {
        $this->viewDate = now()->startOfWeek()->format('Y-m-d');
        $this->refreshAgenda();
    }

    public function openReschedule(int $scheduleId, string $date, string $time): void
    {
        $this->selectedScheduleId = $scheduleId;
        $this->selectedOriginalDate = $date;
        $this->newDate = $date;
        $this->newTime = substr($time, 0, 5);
        $this->rescheduleMode = 'move';
        $this->showRescheduleModal = true;
    }

    public function openCompletion(?int $scheduleId, string $date, ?int $instanceId = null): void
    {
        $this->selectedScheduleId = $scheduleId;
        $this->selectedInstanceId = $instanceId;
        $this->selectedOriginalDate = $date;
        $this->notes = '';

        if ($instanceId) {
            $instance = ServiceInstance::with('users')->findOrFail($instanceId);
            $this->notes = $instance->notes ?? '';
            $this->completionRate = (float) $instance->hourly_rate;
            $this->completionBillingType = $instance->billing_type ?? 'hourly';
            $this->completionUserIds = $instance->users->pluck('id')->toArray();
            $this->hasCompletedInstance = ($instance->status === 'completed');
            $this->selectedScheduleId = $instance->service_schedule_id;

            $duration = (float) $instance->duration_hours;
        } else {
            $schedule = ServiceSchedule::with(['address', 'users'])->findOrFail($scheduleId);

            // Find existing instance
            $instance = ServiceInstance::with('users')->where('company_id', auth()->user()->company->id)
                ->where('service_schedule_id', $scheduleId)
                ->whereDate('original_date', $date)
                ->first();

            if ($instance) {
                $this->notes = $instance->notes ?? '';
                $this->completionRate = (float) $instance->hourly_rate;
                $this->completionBillingType = $instance->billing_type ?? 'hourly';
                $this->completionUserIds = $instance->users->pluck('id')->toArray();
                $this->hasCompletedInstance = ($instance->status === 'completed');
                $this->selectedInstanceId = $instance->id;

                $duration = (float) $instance->duration_hours;
            } else {
                $this->completionRate = (float) $schedule->address->hourly_rate;
                $this->completionBillingType = $schedule->address->billing_type ?? 'hourly';
                $this->completionUserIds = $schedule->users->pluck('id')->toArray();
                $this->hasCompletedInstance = false;
                $this->selectedInstanceId = null;

                $duration = (float) $schedule->address->duration_hours;
            }
        }

        if ($this->completionBillingType === 'hourly') {
            $this->completionHours = \App\Brain\Helpers\TimeHelper::decimalToColon($duration);
        } else {
            $this->completionHours = $duration;
        }

        $this->showCompletionModal = true;
    }

    public function skipOccurrence(int $scheduleId, string $date): void
    {
        $schedule = ServiceSchedule::with('address')->findOrFail($scheduleId);

        $inst = ServiceInstance::updateOrCreate(
            ['service_schedule_id' => $scheduleId, 'original_date' => $date],
            [
                'company_id' => auth()->user()->company->id,
                'customer_id' => $schedule->address->customer_id,
                'service_address_id' => $schedule->service_address_id,
                'service_type_id' => $schedule->service_type_id,
                'description' => $schedule->description ?: ($schedule->type ? $schedule->type->name : __('Service at') . ' ' . $schedule->address->label),
                'date' => $date,
                'time' => $schedule->start_time,
                'duration_hours' => $schedule->address->duration_hours,
                'hourly_rate' => $schedule->address->hourly_rate,
                'status' => 'skipped'
            ]
        );
        $inst->users()->sync($schedule->users()->pluck('users.id')->toArray());

        $this->refreshAgenda();
        Flux::toast(variant: 'success', text: __('Service marked as skipped for this week.'));
    }

    public function saveCompletion(): void
    {
        $userIds = $this->completionBillingType === 'hourly' ? $this->completionUserIds : [];

        if ($this->completionBillingType === 'hourly') {
            $hoursDecimal = \App\Brain\Helpers\TimeHelper::humanToDecimal($this->completionHours);
        } else {
            $hoursDecimal = (float) $this->completionHours;
        }

        if ($this->selectedScheduleId) {
            // Run the UpdateServiceOccurrenceWorkflow!
            \App\Brain\Agenda\Workflows\UpdateServiceOccurrenceWorkflow::run([
                'scheduleId' => $this->selectedScheduleId,
                'originalDate' => $this->selectedOriginalDate,
                'companyId' => auth()->user()->company->id,
                'status' => 'completed',
                'notes' => $this->notes,
                'durationHours' => $hoursDecimal,
                'hourlyRate' => $this->completionRate,
                'userIds' => $userIds,
            ]);
        } elseif ($this->selectedInstanceId) {
            // Update independent instance directly
            $instance = ServiceInstance::where('company_id', auth()->user()->company->id)->findOrFail($this->selectedInstanceId);
            $instance->update([
                'notes' => $this->notes,
                'duration_hours' => $hoursDecimal,
                'hourly_rate' => $this->completionRate,
                'status' => 'completed',
            ]);
            $instance->users()->sync($userIds);
        }

        $this->showCompletionModal = false;
        $this->refreshAgenda();
        Flux::toast(variant: 'success', text: __('Service saved.'));
    }

    public function directUncomplete(?int $scheduleId, string $date, ?int $instanceId = null): void
    {
        $this->selectedScheduleId = $scheduleId;
        $this->selectedInstanceId = $instanceId;
        $this->selectedOriginalDate = $date;
        $this->uncompleteService();
    }

    public function uncompleteService(): void
    {
        $instance = null;

        if ($this->selectedInstanceId) {
            $instance = ServiceInstance::where('company_id', auth()->user()->company->id)->find($this->selectedInstanceId);
        }

        if (!$instance && $this->selectedScheduleId) {
            $instance = ServiceInstance::where('company_id', auth()->user()->company->id)
                ->where('service_schedule_id', $this->selectedScheduleId)
                ->whereDate('original_date', $this->selectedOriginalDate)
                ->first();
        }

        if ($instance) {
            // First check if it is part of an invoice item (cannot uncomplete if billed)
            if ($instance->invoiceItem()->exists()) {
                Flux::toast(variant: 'danger', text: __('This service is already included in an invoice and cannot be unmarked.'));
                return;
            }

            // Delete the instance to revert to schedule default
            $instance->delete();
        }

        $this->showCompletionModal = false;
        $this->refreshAgenda();
        Flux::toast(variant: 'success', text: __('Service unmarked as completed.'));
    }

    public function saveReschedule(): void
    {
        // Run the UpdateServiceOccurrenceWorkflow!
        \App\Brain\Agenda\Workflows\UpdateServiceOccurrenceWorkflow::run([
            'scheduleId' => $this->selectedScheduleId,
            'originalDate' => $this->selectedOriginalDate,
            'companyId' => auth()->user()->company->id,
            'date' => $this->newDate,
            'time' => $this->newTime,
            'status' => $this->rescheduleMode === 'skip' ? 'skipped' : 'scheduled',
        ]);

        $this->showRescheduleModal = false;
        $this->refreshAgenda();
        Flux::toast(variant: 'success', text: __('Agenda updated.'));
    }

    public function refreshAgenda(): void
    {
        $start = Carbon::parse($this->viewDate)->startOfWeek();
        $end = $start->copy()->endOfWeek();
        $period = CarbonPeriod::create($start, $end);

        $companyId = auth()->user()->company->id;
        
        $schedulesQuery = ServiceSchedule::whereHas('address.customer', fn($q) => $q->where('company_id', $companyId))
            ->with(['address.customer', 'users'])
            ->where('is_active', true);

        // Get all instances in or affecting this week
        $instancesQuery = ServiceInstance::where('company_id', $companyId)
            ->with(['schedule.address.customer', 'users', 'address', 'invoiceItem'])
            ->where(function($query) use ($start, $end) {
                $query->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                      ->orWhereBetween('original_date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
            });

        if ($this->filterCalendarId !== 'all') {
            $schedulesQuery->whereHas('address', fn($q) => $q->where('calendar_id', $this->filterCalendarId));
            $instancesQuery->whereHas('address', fn($q) => $q->where('calendar_id', $this->filterCalendarId));
        }

        $schedules = $schedulesQuery->get();
        $instances = $instancesQuery->get();

        // If basic access (collaborator), filter to only see what they are part of
        if (auth()->user()->role !== 'management') {
            $schedules = $schedules->filter(fn($s) => $s->users->contains(auth()->id()));
            $instances = $instances->filter(fn($i) => $i->users->contains(auth()->id()) || ($i->schedule && $i->schedule->users->contains(auth()->id())));
        }

        $days = [];
        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $dayServices = [];

            // 1. Virtual occurrences from recurrence
            foreach ($schedules as $schedule) {
                if ($this->isScheduledForDate($schedule, $date)) {
                    // Check if there is an override instance for this original date
                    $override = $instances->where('service_schedule_id', $schedule->id)
                                         ->filter(fn($inst) => $inst->original_date->isSameDay($date))
                                         ->first();

                    if (!$override) {
                        $assignedUsers = $schedule->users;
                        $assignedCount = $assignedUsers->count() ?: 1;
                        $duration = $schedule->address->duration_hours;

                        if (auth()->user()->role !== 'management') {
                            $totalValue = auth()->user()->hourlyRateFor($schedule->address->type) * ($duration / $assignedCount);
                        } else {
                            $totalValue = $duration * $schedule->address->hourly_rate;
                        }

                        // Regular occurrence
                        $dayServices[] = [
                            'id' => $schedule->id,
                            'instance_id' => null,
                            'is_billed' => false,
                            'customer_name' => $schedule->address->customer->name,
                            'address_label' => $schedule->address->label,
                            'description' => $schedule->description ?: ($schedule->type ? $schedule->type->name : null),
                            'duration' => $duration,
                            'total_value' => $totalValue,
                            'payout' => $assignedUsers->sum(fn($u) => $u->hourlyRateFor($schedule->address->type)) * ($duration / $assignedCount),
                            'time' => $schedule->start_time,
                            'recurrence' => $schedule->recurrence_type,
                            'original_date' => $dateStr,
                            'is_override' => false,
                            'status' => 'scheduled',
                            'billing_type' => $schedule->address->billing_type ?? 'hourly',
                            'assigned_users' => $assignedUsers->map(fn($u) => [
                                'name' => $u->name,
                                'initials' => $u->initials(),
                            ])->toArray(),
                        ];
                    } elseif ($override->status !== 'skipped' && $override->date->isSameDay($date)) {
                        $assignedUsers = $override->users;
                        $assignedCount = $assignedUsers->count() ?: 1;
                        $duration = $schedule->address->duration_hours;

                        if (auth()->user()->role !== 'management') {
                            $totalValue = auth()->user()->hourlyRateFor($schedule->address->type) * ($duration / $assignedCount);
                        } else {
                            $totalValue = $duration * $schedule->address->hourly_rate;
                        }

                        // It was overridden but remains on the same day (maybe time change)
                        $dayServices[] = [
                            'id' => $schedule->id,
                            'instance_id' => $override->id,
                            'is_billed' => (bool) $override->invoiceItem,
                            'customer_name' => $schedule->address->customer->name,
                            'address_label' => $schedule->address->label,
                            'description' => $override->description,
                            'duration' => $duration,
                            'total_value' => $totalValue,
                            'payout' => $assignedUsers->sum(fn($u) => $u->hourlyRateFor($schedule->address->type)) * ($duration / $assignedCount),
                            'time' => $override->time,
                            'recurrence' => $schedule->recurrence_type,
                            'original_date' => $dateStr,
                            'is_override' => true,
                            'status' => $override->status,
                            'billing_type' => $override->billing_type ?? $schedule->address->billing_type ?? 'hourly',
                            'assigned_users' => $assignedUsers->map(fn($u) => [
                                'name' => $u->name,
                                'initials' => $u->initials(),
                            ])->toArray(),
                        ];
                    }
                    // If status is skipped or date != original_date, it's not shown here
                }
            }

            // 2. Services rescheduled TO this date (that weren't originally here)
            foreach ($instances as $instance) {
                if ($instance->status !== 'skipped' && $instance->date->isSameDay($date)) {
                    // Check if it was originally NOT on this date (only for scheduled instances)
                    $isOriginal = $instance->schedule ? $this->isScheduledForDate($instance->schedule, $date) : false;

                    if (!$isOriginal) {
                        $assignedUsers = $instance->users;
                        $assignedCount = $assignedUsers->count() ?: 1;
                        $duration = $instance->duration_hours;

                        if (auth()->user()->role !== 'management') {
                            $totalValue = auth()->user()->hourlyRateFor($instance->address?->type) * ($duration / $assignedCount);
                        } else {
                            $totalValue = $duration * $instance->hourly_rate;
                        }

                        $dayServices[] = [
                            'id' => $instance->schedule?->id,
                            'instance_id' => $instance->id,
                            'is_billed' => (bool) $instance->invoiceItem,
                            'customer_name' => $instance->schedule?->address->customer->name ?? $instance->customer?->name ?? 'N/A',
                            'address_label' => $instance->schedule?->address->label ?? $instance->address?->label ?? 'N/A',
                            'description' => $instance->description,
                            'duration' => $duration,
                            'total_value' => $totalValue,
                            'payout' => $assignedUsers->sum(fn($u) => $u->hourlyRateFor($instance->address?->type)) * ($duration / $assignedCount),
                            'time' => $instance->time,
                            'recurrence' => $instance->schedule?->recurrence_type ?? 'once',
                            'original_date' => $instance->original_date ? $instance->original_date->format('Y-m-d') : $instance->date->format('Y-m-d'),
                            'is_override' => (bool) $instance->service_schedule_id,
                            'rescheduled_from' => $instance->original_date ? $instance->original_date->format('d/m') : null,
                            'status' => $instance->status,
                            'billing_type' => $instance->billing_type ?? $instance->schedule?->address->billing_type ?? 'hourly',
                            'assigned_users' => $assignedUsers->map(fn($u) => [
                                'name' => $u->name,
                                'initials' => $u->initials(),
                            ])->toArray(),
                        ];
                    }
                }
            }

            usort($dayServices, fn($a, $b) => $a['time'] <=> $b['time']);

            $days[] = [
                'date' => $date,
                'is_today' => $date->isToday(),
                'services' => $dayServices,
            ];
        }

        $this->days = $days;

        // Calculate weekly totals
        $this->weeklyHours = 0;
        $this->weeklyValue = 0;
        $this->weeklyPayout = 0;
        foreach ($this->days as $day) {
            foreach ($day['services'] as $service) {
                $isHourly = ($service['billing_type'] ?? 'hourly') === 'hourly';
                if (auth()->user()->role !== 'management') {
                    // Collaborator only gets paid for completed services
                    if ($service['status'] === 'completed') {
                        if ($isHourly) {
                            $this->weeklyHours += $service['duration'] / (count($service['assigned_users']) ?: 1);
                        }
                        $this->weeklyValue += $service['total_value'];
                    }
                } else {
                    // Management sees totals for all active services in view
                    if ($isHourly) {
                        $this->weeklyHours += $service['duration'];
                    }
                    $this->weeklyValue += $service['total_value'];
                    // Sum payout for completed services (or all services, but "confirmado e executado" usually means we calculate payout for completed, or we can count expected payout too. Let's count payout for completed services only, which perfectly matches "confirmado e executado", or let's sum payouts for completed services).
                    if ($service['status'] === 'completed') {
                        $this->weeklyPayout += $service['payout'] ?? 0;
                    }
                }
            }
        }
    }

    private function isScheduledForDate(ServiceSchedule $schedule, Carbon $date): bool
    {
        $address = $schedule->address;
        $startDate = $address->start_date ?? $schedule->start_date;

        // Do not show if the date is before the actual start date
        if ($date->lt($startDate->startOfDay())) {
            return false;
        }

        // Check end date if available
        if ($address->end_date && $date->gt($address->end_date->endOfDay())) {
            return false;
        }

        switch ($schedule->recurrence_type) {
            case 'once':
                return $date->isSameDay($startDate);

            case 'weekly':
                return in_array($date->dayOfWeek, $schedule->days_of_week ?? []);

            case 'fortnightly':
                if (!in_array($date->dayOfWeek, $schedule->days_of_week ?? [])) {
                    return false;
                }
                // Calculate weeks difference from the week of start_date
                $weeksDiff = $startDate->copy()->startOfWeek()->diffInWeeks($date->copy()->startOfWeek());
                return $weeksDiff % 2 === 0;

            case 'monthly':
                return $date->day === $schedule->day_of_month;
        }

        return false;
    }
};

?>

<div class="mx-auto max-w-none w-full px-4 sm:px-6 lg:px-8 space-y-6 pb-24">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('Agenda') }}</h1>
            <div class="mt-1 flex items-center gap-4 text-sm text-zinc-500 dark:text-zinc-400">
                <span>{{ __('Your weekly service schedule.') }}</span>
                <div class="flex items-center gap-3 border-l border-zinc-200 dark:border-zinc-700 pl-4">
                    <div class="flex items-center gap-1.5">
                        <flux:icon.clock class="w-4 h-4" />
                        <span class="font-medium text-zinc-900 dark:text-white">{{ \App\Brain\Helpers\TimeHelper::decimalToHuman($weeklyHours) }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <flux:icon.banknotes class="w-4 h-4 text-emerald-500" />
                        <span class="font-medium text-emerald-600 dark:text-emerald-400" title="{{ __('Weekly Gross Billing') }}">{{ Number::currency($weeklyValue, 'GBP') }}</span>
                    </div>
                    @if(auth()->user()->role === 'management')
                        <div class="flex items-center gap-1.5 border-l border-zinc-200 dark:border-zinc-700 pl-3">
                            <flux:icon.banknotes class="w-4 h-4 text-red-500" />
                            <span class="font-medium text-red-600 dark:text-red-400" title="{{ __('Weekly Team Payout') }}">{{ Number::currency($weeklyPayout, 'GBP') }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-3">
            <flux:select wire:model.live="filterCalendarId" class="w-full sm:w-44" size="sm">
                <flux:select.option value="all">{{ __('All Calendars') }}</flux:select.option>
                @foreach($this->calendars as $cal)
                    <flux:select.option value="{{ $cal->id }}">{{ $cal->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:button.group class="w-full sm:w-auto justify-center">
                <flux:button wire:click="prevWeek" icon="chevron-left" variant="outline" />
                <flux:button wire:click="goToToday" variant="outline">{{ __('Today') }}</flux:button>
                <flux:button wire:click="nextWeek" icon="chevron-right" variant="outline" />
            </flux:button.group>
        </div>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-7 gap-4">
        @foreach($this->days as $day)
            @php $hasServices = count($day['services']) > 0; @endphp
            <div class="flex flex-col min-h-[200px] bg-white dark:bg-zinc-900 border {{ $day['is_today'] ? 'border-zinc-900 dark:border-white ring-1 ring-zinc-900 dark:ring-white' : 'border-zinc-200 dark:border-zinc-700' }} rounded-xl overflow-hidden shadow-sm">
                <div class="p-3 {{ $day['is_today'] ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }} border-b border-zinc-200 dark:border-zinc-700 text-center relative">
                    <p class="text-xs font-bold uppercase tracking-wider">{{ $day['date']->translatedFormat('D') }}</p>
                    <p class="text-lg font-semibold">{{ $day['date']->format('d') }}</p>
                    @php 
                        $dayHours = collect($day['services'])
                            ->where('status', '!=', 'skipped')
                            ->filter(fn($s) => ($s['billing_type'] ?? 'hourly') === 'hourly')
                            ->sum('duration'); 
                    @endphp
                    @if($dayHours > 0)
                        <span class="absolute top-1.5 right-2 text-[10px] font-bold px-1.5 py-0.5 rounded-full {{ $day['is_today'] ? 'bg-white/20 text-white dark:bg-zinc-900/10 dark:text-zinc-900' : 'bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300' }}">
                            {{ \App\Brain\Helpers\TimeHelper::decimalToHuman($dayHours) }}
                        </span>
                    @endif
                </div>

                <div class="flex-1 p-2 space-y-2">
                    @forelse($day['services'] as $service)
                        <div class="group relative p-2 rounded-lg {{ $service['is_override'] ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800' : 'bg-zinc-50 dark:bg-zinc-800 border-zinc-100 dark:border-zinc-700' }} border text-xs">
                            <div class="flex justify-between items-start">
                                <p class="font-bold text-zinc-900 dark:text-white truncate pr-4 {{ $service['status'] === 'completed' ? 'line-through opacity-50' : '' }}" title="{{ $service['customer_name'] }}">
                                    {{ $service['customer_name'] }}
                                </p>

                                <div class="flex items-center gap-1 -mr-1">
                                    @if($service['status'] === 'completed')
                                        <flux:icon.check-circle class="w-4 h-4 text-emerald-500" variant="solid" />
                                    @endif
                                </div>
                            </div>

                            @if($service['description'])
                                <p class="text-[10px] font-medium text-zinc-600 dark:text-zinc-300 truncate">
                                    {{ $service['description'] }}
                                </p>
                            @endif

                            <p class="text-zinc-500 dark:text-zinc-400 truncate text-[10px]">
                                {{ $service['address_label'] }}
                            </p>

                             <div class="mt-1 flex items-center gap-2 text-[10px] text-zinc-500">
                                @if(($service['billing_type'] ?? 'hourly') === 'hourly')
                                    <div class="flex items-center gap-0.5">
                                        <flux:icon.clock class="w-3 h-3" />
                                        <span>{{ \App\Brain\Helpers\TimeHelper::decimalToHuman($service['duration']) }}</span>
                                    </div>
                                @else
                                    <div class="flex items-center gap-0.5">
                                        <flux:icon.hashtag class="w-3 h-3" />
                                        <span>{{ (float) $service['duration'] }} {{ (float) $service['duration'] === 1.0 ? __('unit') : __('units') }}</span>
                                    </div>
                                @endif
                                <div class="flex items-center gap-0.5 font-medium text-zinc-700 dark:text-zinc-300">
                                    <flux:icon.banknotes class="w-3 h-3" />
                                    <span>{{ Number::currency($service['total_value'], 'GBP') }}</span>
                                </div>
                                @if($service['is_billed'] ?? false)
                                    <div class="flex items-center gap-0.5 text-blue-600 dark:text-blue-400 font-bold ml-auto" title="{{ __('Invoiced') }}">
                                        <flux:icon.document-text class="w-3.5 h-3.5" />
                                        <span class="text-[9px] uppercase tracking-wider">{{ __('Billed') }}</span>
                                    </div>
                                @endif
                            </div>

                            @if(isset($service['rescheduled_from']))
                                <p class="text-[9px] text-amber-600 dark:text-amber-400 mt-0.5">
                                    {{ __('Rescheduled from') }} {{ $service['rescheduled_from'] }}
                                </p>
                            @endif

                             <div class="mt-1 flex items-center justify-between">
                                <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ substr($service['time'], 0, 5) }}</span>
                                <span class="px-1.5 py-0.5 rounded-md bg-zinc-200 dark:bg-zinc-700 text-[10px] text-zinc-600 dark:text-zinc-400 uppercase">
                                    {{ substr(__($service['recurrence']), 0, 1) }}
                                </span>
                            </div>

                              @if(($service['billing_type'] ?? 'hourly') === 'hourly' && !empty($service['assigned_users']))
                                  <div class="mt-2 flex -space-x-1 overflow-hidden">
                                      @foreach($service['assigned_users'] as $u)
                                          <div class="flex justify-center itens-center h-5 w-5 rounded-full ring-2 ring-white dark:ring-zinc-900 bg-zinc-100 dark:bg-zinc-700 text-[8px] font-bold flex items-center justify-center text-zinc-600 dark:text-zinc-300" title="{{ $u['name'] }}">
                                              {{ $u['initials'] }}
                                          </div>
                                      @endforeach
                                  </div>
                              @endif

                              @if(auth()->user()->role !== 'collaborator' && ($service['id'] !== null || $service['instance_id'] !== null))
                                  <div class="mt-2.5 pt-2 border-t border-zinc-200/50 dark:border-zinc-700/50 flex items-center justify-between gap-1">
                                      @if($service['status'] !== 'completed')
                                          <button type="button" wire:click="openCompletion({{ $service['id'] ?? 'null' }}, '{{ $service['original_date'] }}', {{ $service['instance_id'] ?? 'null' }})" class="flex-1 inline-flex items-center justify-center gap-1 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/20 dark:hover:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 py-1 px-1.5 rounded text-[10px] font-semibold cursor-pointer border border-emerald-200/30 dark:border-emerald-800/30" title="{{ __('Mark as Completed') }}">
                                              <flux:icon.check class="w-3 h-3" />
                                              <span>{{ __('Complete') }}</span>
                                          </button>
                                      @else
                                          <button type="button" wire:click="openCompletion({{ $service['id'] ?? 'null' }}, '{{ $service['original_date'] }}', {{ $service['instance_id'] ?? 'null' }})" class="flex-1 inline-flex items-center justify-center gap-1 bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 py-1 px-1.5 rounded text-[10px] font-semibold cursor-pointer border border-zinc-200 dark:border-zinc-700" title="{{ __('Edit Execution / Value') }}">
                                              <flux:icon.pencil class="w-3 h-3" />
                                              <span>{{ __('Edit') }}</span>
                                          </button>
                                          <button type="button" wire:click="directUncomplete({{ $service['id'] ?? 'null' }}, '{{ $service['original_date'] }}', {{ $service['instance_id'] ?? 'null' }})" class="inline-flex items-center justify-center bg-red-50 hover:bg-red-100 dark:bg-red-950/20 dark:hover:bg-red-900/30 text-red-600 dark:text-red-400 p-1 rounded text-[10px] font-semibold cursor-pointer border border-red-200/30 dark:border-red-800/30" title="{{ __('Unmark Executed') }}">
                                              <flux:icon.x-mark class="w-3 h-3" />
                                          </button>
                                      @endif

                                      <!-- More Actions (Only for scheduled services) -->
                                      @if($service['id'] !== null)
                                          <flux:dropdown align="end">
                                              <button type="button" class="inline-flex items-center justify-center bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 p-1 rounded text-[10px] text-zinc-500 cursor-pointer border border-zinc-200 dark:border-zinc-700">
                                                  <flux:icon.ellipsis-horizontal class="w-3 h-3" />
                                              </button>
                                              <flux:menu>
                                                  <flux:menu.item icon="calendar" wire:click="openReschedule({{ $service['id'] }}, '{{ $service['original_date'] }}', '{{ $service['time'] }}')">{{ __('Reschedule') }}</flux:menu.item>
                                                  <flux:menu.item icon="x-mark" variant="danger" wire:click="skipOccurrence({{ $service['id'] }}, '{{ $service['original_date'] }}')">{{ __('Skip this week') }}</flux:menu.item>
                                              </flux:menu>
                                          </flux:dropdown>
                                      @endif
                                  </div>
                              @endif
                        </div>
                    @empty
                        <div class="h-full flex items-center justify-center">
                             <p class="text-[10px] text-zinc-400 dark:text-zinc-600 italic">{{ __('No services') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    <!-- Reschedule Modal -->
    <flux:modal wire:model="showRescheduleModal" class="md:w-96">
        <form wire:submit="saveReschedule" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Reschedule Service') }}</flux:heading>
                <flux:subheading>{{ __('Only for this occurrence.') }}</flux:subheading>
            </div>

            <flux:radio.group wire:model="rescheduleMode" variant="cards" class="flex-col">
                <flux:radio value="move" label="{{ __('New date/time') }}" description="{{ __('Move to another day or time.') }}" />
                <flux:radio value="skip" label="{{ __('Skip') }}" description="{{ __('Mark as not performed this week.') }}" />
            </flux:radio.group>

            @if($rescheduleMode === 'move')
                <div class="space-y-4">
                    <flux:field>
                        <flux:label>{{ __('Date') }}</flux:label>
                        <flux:input type="date" wire:model="newDate" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Time') }}</flux:label>
                        <flux:input type="time" wire:model="newTime" />
                    </flux:field>
                </div>
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button wire:click="$set('showRescheduleModal', false)" variant="ghost">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Completion Modal -->
    <flux:modal wire:model="showCompletionModal" class="w-full md:w-[480px]">
        <form wire:submit="saveCompletion" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $hasCompletedInstance ? __('Edit Executed Service') : __('Complete Service') }}</flux:heading>
                <flux:subheading>{{ $hasCompletedInstance ? __('Edit details for this executed service occurrence.') : __('Mark this service as executed.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <!-- Collaborators (Assigned Team) -->
                @if($completionBillingType === 'hourly')
                    <flux:field>
                        <flux:label>{{ __('Assigned Team') }}</flux:label>
                        <div class="mt-2 space-y-2 max-h-36 overflow-y-auto border border-zinc-200 dark:border-zinc-800 rounded-lg p-3 bg-zinc-50/50 dark:bg-zinc-950/20">
                            @foreach($this->collaborators as $collab)
                                <flux:checkbox wire:model="completionUserIds" value="{{ $collab->id }}" label="{{ $collab->name }}" />
                            @endforeach
                        </div>
                    </flux:field>
                @endif

                <!-- Value editing (Hours/Quantity and Rate/Unit Price) -->
                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>{{ $completionBillingType === 'hourly' ? __('Hours') : __('Quantity') }}</flux:label>
                        @if($completionBillingType === 'hourly')
                            <flux:input type="text" wire:model="completionHours" placeholder="Ex: 02:30" required wire:key="completion-hours-time" />
                        @else
                            <flux:input type="number" step="0.01" wire:model="completionHours" placeholder="Ex: 5" required wire:key="completion-hours-number" />
                        @endif
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ $completionBillingType === 'hourly' ? __('Hourly Rate') : __('Unit Price') }}</flux:label>
                        <flux:input type="number" step="0.01" wire:model="completionRate" icon="banknotes" required />
                    </flux:field>
                </div>

                <!-- Notes -->
                <flux:field>
                    <flux:label>{{ __('Notes (Optional)') }}</flux:label>
                    <flux:textarea wire:model="notes" placeholder="{{ __('Add any observations about the service...') }}" />
                </flux:field>
            </div>

            <div class="flex flex-col-reverse sm:flex-row gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800/50">
                @if($hasCompletedInstance)
                    <flux:button wire:click="uncompleteService" variant="danger" class="w-full sm:w-auto mr-auto">{{ __('Unmark Executed') }}</flux:button>
                @endif
                <div class="flex gap-2 w-full sm:w-auto sm:justify-end">
                    <flux:button wire:click="$set('showCompletionModal', false)" variant="ghost" class="flex-1 sm:flex-none">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary" class="flex-1 sm:flex-none">{{ $hasCompletedInstance ? __('Save Changes') : __('Confirm Execution') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>

