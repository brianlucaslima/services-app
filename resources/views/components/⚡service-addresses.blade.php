<?php

use App\Models\Customer;
use App\Models\ServiceAddress;
use App\Models\ServiceSchedule;
use App\Models\ServiceType;
use Flux\Flux;
use Livewire\Component;
use Livewire\Attributes\Computed;

new class extends Component
{
    public Customer $customer;
    public $addresses = [];

    // Form fields for adding/editing address
    public $addressId = null;
    public $label = '';
    public $address = '';
    public $city = '';
    public $zip_code = '';
    public $start_date = '';
    public $end_date = '';
    public $duration_hours = 0;
    public $hourly_rate = 0;
    public ?int $calendar_id = null;
    public string $billing_type = 'hourly';

    // Schedules for the current address being edited
    public $schedules = [];

    public $showModal = false;

    public function mount(int $id): void
    {
        if (auth()->user()->role !== 'management') {
            abort(403);
        }

        $this->customer = auth()->user()->company->customers()->findOrFail($id);
        $this->refreshAddresses();
        $this->resetForm();
    }

    public function rendering($view): void
    {
        $view->title(__('Addresses') . ' - ' . $this->customer->name);
    }

    public function refreshAddresses(): void
    {
        $this->addresses = $this->customer->addresses()->with('schedules')->get()->toArray();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->addSchedule(); // Start with one schedule
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->resetForm();
        $address = $this->customer->addresses()->with('schedules.users')->findOrFail($id);
        $this->addressId = $address->id;
        $this->label = $address->label;
        $this->address = $address->address;
        $this->city = $address->city;
        $this->zip_code = $address->zip_code;
        $this->start_date = $address->start_date ? $address->start_date->format('Y-m-d') : '';
        $this->end_date = $address->end_date ? $address->end_date->format('Y-m-d') : '';
        $this->billing_type = $address->billing_type ?? 'hourly';
        if ($this->billing_type === 'hourly') {
            $this->duration_hours = \App\Brain\Helpers\TimeHelper::decimalToColon($address->duration_hours);
        } else {
            $this->duration_hours = (float) $address->duration_hours;
        }
        $this->hourly_rate = $address->hourly_rate;
        $this->calendar_id = $address->calendar_id;

        foreach ($address->schedules as $schedule) {
            $this->schedules[] = [
                'id' => $schedule->id,
                'service_type_id' => $schedule->service_type_id,
                'description' => $schedule->description ?? '',
                'recurrence_type' => $schedule->recurrence_type,
                'days_of_week' => $schedule->days_of_week ?? [],
                'day_of_month' => $schedule->day_of_month,
                'start_date' => $schedule->start_date->format('Y-m-d'),
                'start_time' => $schedule->start_time,
                'user_ids' => $schedule->users->pluck('id')->toArray(),
            ];
        }

        if (empty($this->schedules)) {
            $this->addSchedule();
        }

        $this->showModal = true;
    }

    public function resetForm(): void
    {
        $this->addressId = null;
        $this->label = '';
        $this->address = '';
        $this->city = '';
        $this->zip_code = '';
        $this->start_date = now()->format('Y-m-d');
        $this->end_date = '';
        $this->duration_hours = 0;
        $this->hourly_rate = 0;
        $this->billing_type = 'hourly';
        $this->calendar_id = auth()->check() ? (auth()->user()->company->calendars()->first()?->id) : null;
        $this->schedules = [];
        $this->showModal = false;
    }

    public function updatedBillingType($value): void
    {
        if ($value === 'hourly') {
            $this->duration_hours = \App\Brain\Helpers\TimeHelper::decimalToColon($this->duration_hours);
        } else {
            $this->duration_hours = \App\Brain\Helpers\TimeHelper::humanToDecimal($this->duration_hours);
        }
    }

    public function addSchedule(): void
    {
        $this->schedules[] = [
            'id' => null,
            'service_type_id' => null,
            'description' => '',
            'recurrence_type' => 'weekly',
            'days_of_week' => [],
            'day_of_month' => null,
            'start_date' => now()->format('Y-m-d'),
            'start_time' => '08:00',
            'user_ids' => [],
        ];
    }

    public function removeSchedule(int $index): void
    {
        unset($this->schedules[$index]);
        $this->schedules = array_values($this->schedules);
    }

    public function save(): void
    {
        // Clear user_ids for unit-based billing type
        if ($this->billing_type === 'unit') {
            foreach ($this->schedules as $idx => $sch) {
                $this->schedules[$idx]['user_ids'] = [];
            }
        }

        $durationRule = $this->billing_type === 'hourly' ? 'required' : 'required|numeric|min:0';

        $this->validate([
            'label' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'duration_hours' => $durationRule,
            'hourly_rate' => 'required|numeric|min:0',
            'calendar_id' => 'required|exists:calendars,id',
            'schedules.*.recurrence_type' => 'required|in:once,weekly,fortnightly,monthly',
            'schedules.*.start_date' => 'required|date',
            'schedules.*.start_time' => 'required',
        ]);

        // Parse the duration hours (handles formats like "2:30" or "2.5") only AFTER validation passes!
        if ($this->billing_type === 'hourly') {
            $parsedDuration = \App\Brain\Helpers\TimeHelper::humanToDecimal($this->duration_hours);
        } else {
            $parsedDuration = (float) $this->duration_hours;
        }

        // Run the SaveServiceAddressWorkflow!
        \App\Brain\Customers\Workflows\SaveServiceAddressWorkflow::run([
            'customerId' => $this->customer->id,
            'addressId' => $this->addressId,
            'label' => $this->label,
            'address' => $this->address,
            'city' => $this->city,
            'zipCode' => $this->zip_code,
            'startDate' => $this->start_date,
            'endDate' => $this->end_date,
            'durationHours' => $parsedDuration,
            'hourlyRate' => $this->hourly_rate,
            'calendarId' => $this->calendar_id,
            'billingType' => $this->billing_type,
            'schedules' => $this->schedules,
        ]);

        Flux::toast(variant: 'success', text: __('Address saved successfully.'));
        $this->resetForm();
        $this->refreshAddresses();
    }

    public function deleteAddress(int $id): void
    {
        $this->customer->addresses()->findOrFail($id)->delete();
        Flux::toast(variant: 'success', text: __('Address deleted.'));
        $this->refreshAddresses();
    }

    #[Computed]
    public function calendars()
    {
        return auth()->user()->company->calendars()->orderBy('name')->get();
    }

    #[Computed]
    public function serviceTypes()
    {
        return auth()->user()->company->serviceTypes()->orderBy('name')->get();
    }

    #[Computed]
    public function collaborators()
    {
        return auth()->user()->company->users()->orderBy('name')->get();
    }
};

?>

<div class="mx-auto max-w-2xl space-y-6 pb-24">
    <header class="flex items-center justify-between px-4 sm:px-0">
        <div class="flex items-center gap-3">
             <a href="{{ route('customers') }}" wire:navigate class="p-2 -ml-2 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800 transition">
                <flux:icon.chevron-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ $customer->name }}</h1>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">{{ __('Service Locations') }}</p>
            </div>
        </div>
        <flux:button wire:click="openCreateModal" variant="primary" icon="plus" size="sm" class="rounded-full">
            {{ __('Add') }}
        </flux:button>
    </header>

    <main class="space-y-4 px-4 sm:px-0">
        @forelse($addresses as $addr)
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl p-4 shadow-sm">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <h3 class="font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                            {{ $addr['label'] }}
                            <flux:badge size="sm" color="zinc">{{ \App\Models\Calendar::find($addr['calendar_id'])?->name ?? __($addr['type']) }}</flux:badge>
                        </h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $addr['address'] }}</p>

                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500">
                            @if(($addr['billing_type'] ?? 'hourly') === 'hourly')
                                <div class="flex items-center gap-1">
                                    <flux:icon.clock class="w-3.5 h-3.5" />
                                    <span>{{ \App\Brain\Helpers\TimeHelper::decimalToColon($addr['duration_hours']) }}h</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <flux:icon.banknotes class="w-3.5 h-3.5" />
                                    <span>{{ Number::currency($addr['hourly_rate'], 'GBP') }}/h ({{ Number::currency($addr['duration_hours'] * $addr['hourly_rate'], 'GBP') }})</span>
                                </div>
                            @else
                                <div class="flex items-center gap-1">
                                    <flux:icon.hashtag class="w-3.5 h-3.5" />
                                    <span>{{ (float) $addr['duration_hours'] }} {{ __('units') }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <flux:icon.banknotes class="w-3.5 h-3.5" />
                                    <span>{{ Number::currency($addr['hourly_rate'], 'GBP') }} ({{ Number::currency($addr['duration_hours'] * $addr['hourly_rate'], 'GBP') }})</span>
                                </div>
                            @endif
                            @if($addr['start_date'])
                                <div class="flex items-center gap-1">
                                    <flux:icon.calendar class="w-3.5 h-3.5" />
                                    <span>{{ \Carbon\Carbon::parse($addr['start_date'])->format('d/m/y') }} @if($addr['end_date']) - {{ \Carbon\Carbon::parse($addr['end_date'])->format('d/m/y') }} @endif</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="flex gap-1">
                        <flux:button wire:click="openEditModal({{ $addr['id'] }})" size="xs" variant="ghost" icon="pencil" />
                        <flux:button wire:click="deleteAddress({{ $addr['id'] }})" size="xs" variant="ghost" icon="trash" class="text-red-500" />
                    </div>
                </div>

                @if(!empty($addr['schedules']))
                    <div class="space-y-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                        @foreach($addr['schedules'] as $sch)
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <span class="text-zinc-700 dark:text-zinc-300 font-medium">{{ __($sch['recurrence_type']) }}</span>
                                    <span class="text-zinc-400">•</span>
                                    <span class="text-zinc-500">{{ substr($sch['start_time'], 0, 5) }}</span>
                                </div>
                                <div class="text-zinc-400">
                                    @if($sch['recurrence_type'] === 'weekly' || $sch['recurrence_type'] === 'fortnightly')
                                        @foreach($sch['days_of_week'] as $day)
                                            {{ substr(__(['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$day]), 0, 1) }}
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="py-12 text-center text-zinc-400">
                <flux:icon.map-pin class="w-12 h-12 mx-auto mb-3 opacity-20" />
                <p>{{ __('No locations registered yet.') }}</p>
            </div>
        @endforelse
    </main>

    <!-- Modal para Cadastro/Edição -->
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-zinc-950/20 backdrop-blur-sm">
            <div class="bg-white dark:bg-zinc-900 w-full max-w-lg rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden animate-in slide-in-from-bottom duration-300">
                <div class="px-6 py-4 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                    <h2 class="font-bold text-zinc-900 dark:text-white">{{ $addressId ? __('Edit Location') : __('New Location') }}</h2>
                    <flux:button wire:click="resetForm" variant="ghost" icon="x-mark" size="sm" />
                </div>

                <form wire:submit="save" class="p-6 space-y-6 max-h-[80vh] overflow-y-auto">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>{{ __('Label') }}</flux:label>
                                <flux:input wire:model="label" placeholder="Ex: Casa, Trabalho..." required />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Calendar & Location Type') }}</flux:label>
                                <flux:select wire:model="calendar_id" placeholder="{{ __('Select a calendar...') }}" required>
                                    <flux:select.option value="">{{ __('Select a calendar...') }}</flux:select.option>
                                    @foreach($this->calendars as $cal)
                                        <flux:select.option value="{{ $cal->id }}">{{ $cal->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="calendar_id" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>{{ __('Address') }}</flux:label>
                            <flux:input wire:model="address" required />
                        </flux:field>

                        <div class="grid grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>{{ __('City') }}</flux:label>
                                <flux:input wire:model="city" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('Zip Code') }}</flux:label>
                                <flux:input wire:model="zip_code" />
                            </flux:field>
                        </div>

                        <div class="grid grid-cols-2 gap-4 pt-2">
                            <flux:field>
                                <flux:label>{{ __('Start Date') }}</flux:label>
                                <flux:input type="date" wire:model="start_date" required />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('End Date') }}</flux:label>
                                <flux:input type="date" wire:model="end_date" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>{{ __('Billing Type') }}</flux:label>
                            <flux:select wire:model.live="billing_type" required>
                                <flux:select.option value="hourly">{{ __('Hourly') }}</flux:select.option>
                                <flux:select.option value="unit">{{ __('Unit') }}</flux:select.option>
                            </flux:select>
                        </flux:field>

                        <div class="grid grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>{{ $billing_type === 'hourly' ? __('Hours Duration') : __('Quantity') }}</flux:label>
                                @if($billing_type === 'hourly')
                                    <flux:input type="text" wire:model="duration_hours" placeholder="Ex: 02:30" required wire:key="duration-hours-time" />
                                @else
                                    <flux:input type="text" wire:model="duration_hours" placeholder="Ex: 1, 2, 5" required wire:key="duration-hours-text" />
                                @endif
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ $billing_type === 'hourly' ? __('Hourly Rate') : __('Unit Price') }}</flux:label>
                                <flux:input type="number" step="0.01" wire:model="hourly_rate" icon="banknotes" required />
                            </flux:field>
                        </div>
                    </div>

                    <div class="space-y-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-zinc-900 dark:text-white uppercase tracking-widest">{{ __('Schedules') }}</h3>
                            <flux:button wire:click="addSchedule" variant="ghost" size="xs" icon="plus">{{ __('Add') }}</flux:button>
                        </div>

                        @foreach($schedules as $idx => $sch)
                            <div class="p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-100 dark:border-zinc-700 space-y-4 relative">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <flux:field>
                                        <flux:label>{{ __('Service Type') }}</flux:label>
                                        <flux:select wire:model="schedules.{{ $idx }}.service_type_id" placeholder="{{ __('Select a service...') }}">
                                            @foreach($this->serviceTypes as $type)
                                                <flux:select.option value="{{ $type->id }}">{{ $type->name }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>{{ __('Custom Description (Optional)') }}</flux:label>
                                        <flux:input wire:model="schedules.{{ $idx }}.description" />
                                    </flux:field>
                                </div>

                                @if($billing_type === 'hourly')
                                    <flux:field>
                                        <flux:label>{{ __('Assigned Team') }}</flux:label>
                                        <div class="mt-2 space-y-2 max-h-36 overflow-y-auto border border-zinc-200 dark:border-zinc-800 rounded-lg p-3 bg-zinc-50/50 dark:bg-zinc-950/20">
                                            @foreach($this->collaborators as $collab)
                                                <flux:checkbox wire:model="schedules.{{ $idx }}.user_ids" value="{{ $collab->id }}" label="{{ $collab->name }}" />
                                            @endforeach
                                        </div>
                                    </flux:field>
                                @endif

                                <div class="grid grid-cols-2 gap-4">
                                    <flux:field>
                                        <flux:label>{{ __('Recurrence') }}</flux:label>
                                        <flux:select wire:model="schedules.{{ $idx }}.recurrence_type">
                                            <flux:select.option value="once">{{ __('Once') }}</flux:select.option>
                                            <flux:select.option value="weekly">{{ __('Weekly') }}</flux:select.option>
                                            <flux:select.option value="fortnightly">{{ __('Fortnightly') }}</flux:select.option>
                                            <flux:select.option value="monthly">{{ __('Monthly') }}</flux:select.option>
                                        </flux:select>
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>{{ __('Start Time') }}</flux:label>
                                        <flux:input type="time" wire:model="schedules.{{ $idx }}.start_time" />
                                    </flux:field>
                                </div>

                                @if($schedules[$idx]['recurrence_type'] === 'weekly' || $schedules[$idx]['recurrence_type'] === 'fortnightly')
                                    <flux:field>
                                        <flux:label>{{ __('Days') }}</flux:label>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach(['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $dayIdx => $dayLabel)
                                                <label class="cursor-pointer">
                                                    <input type="checkbox" wire:model="schedules.{{ $idx }}.days_of_week" value="{{ $dayIdx }}" class="hidden peer">
                                                    <div class="w-8 h-8 rounded-full border border-zinc-200 dark:border-zinc-700 flex items-center justify-center text-[10px] font-bold peer-checked:bg-zinc-900 peer-checked:text-white dark:peer-checked:bg-white dark:peer-checked:text-zinc-900 transition">
                                                        {{ $dayLabel }}
                                                    </div>
                                                </label>
                                            @endforeach
                                        </div>
                                    </flux:field>
                                @endif

                                @if($schedules[$idx]['recurrence_type'] === 'monthly')
                                    <flux:field>
                                        <flux:label>{{ __('Day of Month') }}</flux:label>
                                        <flux:input type="number" min="1" max="31" wire:model="schedules.{{ $idx }}.day_of_month" />
                                    </flux:field>
                                @endif

                                @if(count($schedules) > 1)
                                    <button type="button" wire:click="removeSchedule({{ $idx }})" class="absolute top-2 right-2 text-zinc-400 hover:text-red-500">
                                        <flux:icon.x-mark class="w-4 h-4" />
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="pt-6">
                        <flux:button type="submit" variant="primary" class="w-full rounded-2xl h-12 text-lg font-bold">
                            {{ __('Save Location') }}
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
