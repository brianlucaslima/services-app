<?php

use App\Models\ServiceType;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Flux\Flux;

new class extends Component
{
    public $name = '';
    public $editingId = null;

    public function rendering($view): void
    {
        $view->title(__('Services'));
    }

    public function getServiceTypesProperty()
    {
        return auth()->user()->company->serviceTypes()->orderBy('name')->get();
    }

    public function save(): void
    {
        $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('service_types', 'name')
                    ->ignore($this->editingId)
                    ->where('company_id', auth()->user()->company->id)
            ]
        ]);

        if ($this->editingId) {
            auth()->user()->company->serviceTypes()->findOrFail($this->editingId)->update(['name' => $this->name]);
            Flux::toast(variant: 'success', text: __('Service updated.'));
        } else {
            auth()->user()->company->serviceTypes()->create(['name' => $this->name]);
            Flux::toast(variant: 'success', text: __('Service created.'));
        }

        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $type = auth()->user()->company->serviceTypes()->findOrFail($id);
        $this->editingId = $type->id;
        $this->name = $type->name;
    }

    public function delete(int $id): void
    {
        auth()->user()->company->serviceTypes()->findOrFail($id)->delete();
        Flux::toast(variant: 'success', text: __('Service deleted.'));
    }

    public function resetForm(): void
    {
        $this->name = '';
        $this->editingId = null;
    }
};

?>

<div class="mx-auto max-w-2xl space-y-6">
    <header class="flex items-center justify-between px-4 sm:px-0">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('Services Catalog') }}</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Manage your reusable services list.') }}</p>
        </div>
    </header>

    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl p-6 shadow-sm">
        <form wire:submit="save" class="flex gap-3">
            <flux:field class="flex-1">
                <flux:input wire:model="name" placeholder="{{ __('Service name (e.g. Regular Cleaning)') }}" required />
            </flux:field>
            <flux:button type="submit" variant="primary">
                {{ $editingId ? __('Update') : __('Add Service') }}
            </flux:button>
            @if($editingId)
                <flux:button wire:click="resetForm" variant="ghost">{{ __('Cancel') }}</flux:button>
            @endif
        </form>

        <div class="mt-8 space-y-2">
            @foreach($this->serviceTypes as $type)
                <div class="flex items-center justify-between p-3 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-100 dark:border-zinc-700 group">
                    <span class="text-zinc-900 dark:text-white font-medium">{{ $type->name }}</span>
                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition">
                        <flux:button wire:click="edit({{ $type->id }})" size="xs" variant="ghost" icon="pencil" />
                        <flux:button wire:click="delete({{ $type->id }})" size="xs" variant="ghost" icon="trash" class="text-red-500" />
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
