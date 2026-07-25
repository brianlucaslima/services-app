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

    // Reschedule modal state
    public bool $showRescheduleModal = false;
    public bool $showCompletionModal = false;
    public ?int $selectedScheduleId = null;
    public string $selectedOriginalDate = '';
    public string $newDate = '';
    public string $newTime = '';
    public string $rescheduleMode = 'move'; // move or skip
    public string $notes = '';

    public function mount(): void
    {
        $this->viewDate = now()->startOfWeek()->format('Y-m-d');
        $this->refreshAgenda();
    }

    public function updatedViewDate(): void
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

    public function openCompletion(int $scheduleId, string $date): void
    {
        $this->selectedScheduleId = $scheduleId;
        $this->selectedOriginalDate = $date;
        $this->notes = '';
        
        // Find existing instance to load notes if any
        $instance = ServiceInstance::where('service_schedule_id', $scheduleId)
            ->where('original_date', $date)
            ->first();
            
        if ($instance) {
            $this->notes = $instance->notes ?? '';
        }

        $this->showCompletionModal = true;
    }

    public function skipOccurrence(int $scheduleId, string $date): void
    {
        $schedule = ServiceSchedule::findOrFail($scheduleId);
        
        ServiceInstance::updateOrCreate(
            ['service_schedule_id' => $scheduleId, 'original_date' => $date],
            [
                'date' => $date,
                'time' => $schedule->start_time,
                'status' => 'skipped'
            ]
        );

        $this->refreshAgenda();
        Flux::toast(variant: 'success', text: __('Service marked as skipped for this week.'));
    }

    public function saveCompletion(): void
    {
        $schedule = ServiceSchedule::with(['address', 'type'])->findOrFail($this->selectedScheduleId);
        
        // If it was already rescheduled, we should use its current date/time
        $instance = ServiceInstance::where('service_schedule_id', $this->selectedScheduleId)
            ->where('original_date', $this->selectedOriginalDate)
            ->first();

        $description = $schedule->description;
        if (empty($description) && $schedule->type) {
            $description = $schedule->type->name;
        }
        if (empty($description)) {
            $description = __('Service at') . ' ' . $schedule->address->label;
        }

        ServiceInstance::updateOrCreate(
            ['service_schedule_id' => $this->selectedScheduleId, 'original_date' => $this->selectedOriginalDate],
            [
                'customer_id' => $schedule->address->customer_id,
                'service_address_id' => $schedule->service_address_id,
                'service_type_id' => $schedule->service_type_id,
                'description' => $description,
                'date' => $instance->date ?? $this->selectedOriginalDate,
                'time' => $instance->time ?? $schedule->start_time,
                'duration_hours' => $schedule->address->duration_hours,
                'hourly_rate' => $schedule->address->hourly_rate,
                'status' => 'completed',
                'notes' => $this->notes
            ]
        );

        $this->showCompletionModal = false;
        $this->refreshAgenda();
        Flux::toast(variant: 'success', text: __('Service marked as completed.'));
    }

    public function saveReschedule(): void
    {
        ServiceInstance::updateOrCreate(
            ['service_schedule_id' => $this->selectedScheduleId, 'original_date' => $this->selectedOriginalDate],
            [
                'date' => $this->newDate,
                'time' => $this->newTime,
                'status' => $this->rescheduleMode === 'skip' ? 'skipped' : 'scheduled'
            ]
        );

        $this->showRescheduleModal = false;
        $this->refreshAgenda();
        Flux::toast(variant: 'success', text: __('Agenda updated.'));
    }

    public function refreshAgenda(): void
    {
        $start = Carbon::parse($this->viewDate)->startOfWeek();
        $end = $start->copy()->endOfWeek();
        $period = CarbonPeriod::create($start, $end);
        
        $schedules = ServiceSchedule::query()
            ->with(['address.customer'])
            ->where('is_active', true)
            ->get();

        // Get all instances in or affecting this week
        $instances = ServiceInstance::query()
            ->with(['schedule.address.customer'])
            ->where(function($query) use ($start, $end) {
                $query->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                      ->orWhereBetween('original_date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
            })
            ->get();

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
                        // Regular occurrence
                        $dayServices[] = [
                            'id' => $schedule->id,
                            'customer_name' => $schedule->address->customer->name,
                            'address_label' => $schedule->address->label,
                            'description' => $schedule->description ?: ($schedule->type ? $schedule->type->name : null),
                            'duration' => $schedule->address->duration_hours,
                            'total_value' => $schedule->address->duration_hours * $schedule->address->hourly_rate,
                            'time' => $schedule->start_time,
                            'recurrence' => $schedule->recurrence_type,
                            'original_date' => $dateStr,
                            'is_override' => false,
                            'status' => 'scheduled',
                        ];
                    } elseif ($override->status !== 'skipped' && $override->date->isSameDay($date)) {
                        // It was overridden but remains on the same day (maybe time change)
                        $dayServices[] = [
                            'id' => $schedule->id,
                            'customer_name' => $schedule->address->customer->name,
                            'address_label' => $schedule->address->label,
                            'description' => $override->description,
                            'duration' => $schedule->address->duration_hours,
                            'total_value' => $schedule->address->duration_hours * $schedule->address->hourly_rate,
                            'time' => $override->time,
                            'recurrence' => $schedule->recurrence_type,
                            'original_date' => $dateStr,
                            'is_override' => true,
                            'status' => $override->status,
                        ];
                    }
                    // If status is skipped or date != original_date, it's not shown here
                }
            }

            // 2. Services rescheduled TO this date (that weren't originally here)
            foreach ($instances as $instance) {
                if ($instance->status !== 'skipped' && $instance->date->isSameDay($date)) {
                    // Check if it was originally NOT on this date
                    if (!$this->isScheduledForDate($instance->schedule, $date)) {
                        $dayServices[] = [
                            'id' => $instance->schedule->id,
                            'customer_name' => $instance->schedule->address->customer->name,
                            'address_label' => $instance->schedule->address->label,
                            'description' => $instance->description,
                            'duration' => $instance->duration_hours,
                            'total_value' => $instance->duration_hours * $instance->hourly_rate,
                            'time' => $instance->time,
                            'recurrence' => $instance->schedule->recurrence_type,
                            'original_date' => $instance->original_date->format('Y-m-d'),
                            'is_override' => true,
                            'rescheduled_from' => $instance->original_date->format('d/m'),
                            'status' => $instance->status,
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
        foreach ($this->days as $day) {
            foreach ($day['services'] as $service) {
                $this->weeklyHours += $service['duration'];
                $this->weeklyValue += $service['total_value'];
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

<div class="mx-auto max-w-5xl space-y-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('Agenda') }}</h1>
            <div class="mt-1 flex items-center gap-4 text-sm text-zinc-500 dark:text-zinc-400">
                <span>{{ __('Your weekly service schedule.') }}</span>
                <div class="flex items-center gap-3 border-l border-zinc-200 dark:border-zinc-700 pl-4">
                    <div class="flex items-center gap-1.5">
                        <flux:icon.clock class="w-4 h-4" />
                        <span class="font-medium text-zinc-900 dark:text-white">{{ $weeklyHours }}h</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <flux:icon.banknotes class="w-4 h-4" />
                        <span class="font-medium text-emerald-600 dark:text-emerald-400">{{ Number::currency($weeklyValue, 'GBP') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <flux:button.group>
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
                <div class="p-3 {{ $day['is_today'] ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }} border-b border-zinc-200 dark:border-zinc-700 text-center">
                    <p class="text-xs font-bold uppercase tracking-wider">{{ $day['date']->translatedFormat('D') }}</p>
                    <p class="text-lg font-semibold">{{ $day['date']->format('d') }}</p>
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

                                    <flux:dropdown align="end">
                                        <flux:button variant="ghost" size="xs" icon="ellipsis-vertical" class="h-4 w-4" />
                                        <flux:menu>
                                            @if($service['status'] !== 'completed')
                                                <flux:menu.item icon="check" wire:click="openCompletion({{ $service['id'] }}, '{{ $service['original_date'] }}')">{{ __('Mark as Completed') }}</flux:menu.item>
                                            @else
                                                <flux:menu.item icon="pencil" wire:click="openCompletion({{ $service['id'] }}, '{{ $service['original_date'] }}')">{{ __('Edit Notes') }}</flux:menu.item>
                                            @endif
                                            <flux:menu.item icon="calendar" wire:click="openReschedule({{ $service['id'] }}, '{{ $service['original_date'] }}', '{{ $service['time'] }}')">{{ __('Reschedule') }}</flux:menu.item>
                                            <flux:menu.item icon="x-mark" variant="danger" wire:click="skipOccurrence({{ $service['id'] }}, '{{ $service['original_date'] }}')">{{ __('Skip this week') }}</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
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
                                <div class="flex items-center gap-0.5">
                                    <flux:icon.clock class="w-3 h-3" />
                                    <span>{{ $service['duration'] }}h</span>
                                </div>
                                <div class="flex items-center gap-0.5 font-medium text-zinc-700 dark:text-zinc-300">
                                    <flux:icon.banknotes class="w-3 h-3" />
                                    <span>{{ Number::currency($service['total_value'], 'GBP') }}</span>
                                </div>
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
    <flux:modal wire:model="showCompletionModal" class="md:w-96">
        <form wire:submit="saveCompletion" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Complete Service') }}</flux:heading>
                <flux:subheading>{{ __('Mark this service as executed.') }}</flux:subheading>
            </div>

            <flux:field>
                <flux:label>{{ __('Notes (Optional)') }}</flux:label>
                <flux:textarea wire:model="notes" placeholder="{{ __('Add any observations about the service...') }}" />
            </flux:field>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button wire:click="$set('showCompletionModal', false)" variant="ghost">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Confirm Execution') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

