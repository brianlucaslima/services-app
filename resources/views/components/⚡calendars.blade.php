<?php

use App\Models\Calendar;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Flux\Flux;

new class extends Component
{
    public $name = '';
    public $editingId = null;

    public function mount(): void
    {
        if (auth()->user()->role !== 'management') {
            abort(403);
        }
    }

    public function rendering($view): void
    {
        $view->title(__('Calendars & Locations'));
    }

    public function getCalendarsProperty()
    {
        return auth()->user()->company->calendars()->orderBy('name')->get();
    }

    public function save(): void
    {
        $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('calendars', 'name')
                    ->ignore($this->editingId)
                    ->where('company_id', auth()->user()->company->id)
            ]
        ]);

        if ($this->editingId) {
            $calendar = auth()->user()->company->calendars()->findOrFail($this->editingId);
            $calendar->update(['name' => $this->name]);
            Flux::toast(variant: 'success', text: __('Calendar updated successfully.'));
        } else {
            auth()->user()->company->calendars()->create([
                'name' => $this->name,
            ]);
            Flux::toast(variant: 'success', text: __('Calendar created successfully.'));
        }

        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $calendar = auth()->user()->company->calendars()->findOrFail($id);
        $this->editingId = $calendar->id;
        $this->name = $calendar->name;
    }

    public function delete(int $id): void
    {
        $company = auth()->user()->company;
        
        if ($company->calendars()->count() <= 1) {
            Flux::toast(variant: 'danger', text: __('You must have at least one calendar.'));
            return;
        }

        $company->calendars()->findOrFail($id)->delete();
        Flux::toast(variant: 'success', text: __('Calendar deleted.'));
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->name = '';
        $this->editingId = null;
    }
};

?>

<div class="mx-auto max-w-2xl space-y-6 pb-24">
    <header class="px-4 sm:px-0">
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('Calendars & Locations') }}</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Create and manage your business calendar types (e.g. House, Office, Industrial).') }}</p>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Form Column -->
        <div class="md:col-span-1 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-sm h-fit">
            <form wire:submit="save" class="space-y-4">
                <flux:heading size="md">
                    {{ $editingId ? __('Update Calendar') : __('Add Calendar') }}
                </flux:heading>

                <flux:field>
                    <flux:label>{{ __('Calendar Name') }}</flux:label>
                    <flux:input wire:model="name" required placeholder="{{ __('e.g. Commercial Cleaning') }}" />
                    <flux:error name="name" />
                </flux:field>

                <div class="flex gap-2 pt-2">
                    @if($editingId)
                        <flux:button wire:click="resetForm" type="button" variant="ghost" class="w-full">
                            {{ __('Cancel') }}
                        </flux:button>
                    @endif
                    <flux:button type="submit" variant="primary" class="w-full">
                        {{ $editingId ? __('Update') : __('Save') }}
                    </flux:button>
                </div>
            </form>
        </div>

        <!-- List Column -->
        <div class="md:col-span-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-sm overflow-hidden">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($this->calendars as $cal)
                        <flux:table.row :key="$cal->id">
                            <flux:table.cell class="font-semibold text-zinc-900 dark:text-white">
                                {{ $cal->name }}
                            </flux:table.cell>
                            <flux:table.cell class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button wire:click="edit({{ $cal->id }})" size="xs" variant="ghost" icon="pencil" />
                                    <flux:button wire:click="delete({{ $cal->id }})" size="xs" variant="ghost" icon="trash" variant="danger" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </div>
</div>
