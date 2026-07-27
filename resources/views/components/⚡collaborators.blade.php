<?php

use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

new class extends Component
{
    public $search = '';
    public $showModal = false;

    // Form fields
    public $userId = null;
    public $name = '';
    public $email = '';
    public $password = '';
    public $role = 'collaborator';
    public $hourly_rate_house = 0;
    public $hourly_rate_office = 0;

    public function mount(): void
    {
        if (auth()->user()->role !== 'management') {
            abort(403);
        }
    }

    public function rendering($view): void
    {
        $view->title(__('Collaborators'));
    }

    public function getCollaboratorsProperty()
    {
        return auth()->user()->company->users()
            ->when($this->search !== '', function($query) {
                $query->where(function($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('name')
            ->get();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->resetForm();
        $user = auth()->user()->company->users()->findOrFail($id);
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->pivot->role;
        $this->hourly_rate_house = $user->pivot->hourly_rate_house;
        $this->hourly_rate_office = $user->pivot->hourly_rate_office;
        $this->showModal = true;
    }

    public function save(): void
    {
        // Run the SaveCollaboratorWorkflow!
        \App\Brain\Collaborators\Workflows\SaveCollaboratorWorkflow::run([
            'companyId' => auth()->user()->company->id,
            'userId' => $this->userId,
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password ?: null,
            'role' => $this->role,
            'hourlyRateHouse' => $this->hourly_rate_house,
            'hourlyRateOffice' => $this->hourly_rate_office,
        ]);

        if ($this->userId) {
            Flux::toast(variant: 'success', text: __('Collaborator updated successfully.'));
        } else {
            Flux::toast(variant: 'success', text: __('Collaborator registered successfully.'));
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        if ($id === auth()->id()) {
            Flux::toast(variant: 'danger', text: __('You cannot delete your own user.'));
            return;
        }

        auth()->user()->company->users()->findOrFail($id)->delete();
        Flux::toast(variant: 'success', text: __('Collaborator deleted.'));
    }

    public function resetForm(): void
    {
        $this->userId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->role = 'collaborator';
        $this->hourly_rate_house = 0;
        $this->hourly_rate_office = 0;
    }
};

?>

<div class="mx-auto max-w-5xl space-y-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between sm:px-0">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('Collaborators') }}</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Manage your team members and their hourly rates.') }}</p>
        </div>
        <flux:button wire:click="openCreateModal" variant="primary" icon="plus" class="rounded-full">
            {{ __('Add Collaborator') }}
        </flux:button>
    </header>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between sm:px-0">
        <div class="relative w-full sm:w-72">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="h-5 w-5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
            </div>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search collaborators...') }}" class="block w-full rounded-lg border-0 py-2 pl-10 pr-3 text-zinc-900 shadow-sm ring-1 ring-inset ring-zinc-300 placeholder:text-zinc-400 focus:ring-2 focus:ring-inset focus:ring-zinc-900 dark:bg-zinc-900 dark:text-white dark:ring-zinc-700 dark:placeholder:text-zinc-500 dark:focus:ring-white sm:text-sm sm:leading-6" />
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900 border-y sm:border border-zinc-200 dark:border-zinc-700 sm:rounded-xl overflow-hidden shadow-sm">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column>{{ __('Hourly Rate') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($this->collaborators as $collab)
                    <flux:table.row :key="$collab->id">
                        <flux:table.cell>
                            <span class="block font-semibold text-zinc-900 dark:text-white">{{ $collab->name }}</span>
                            <span class="block text-xs text-zinc-500">{{ $collab->email }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$collab->pivot->role === 'management' ? 'purple' : 'zinc'" size="sm">
                                {{ __($collab->pivot->role) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-700 dark:text-zinc-300">
                            <span class="block text-xs font-semibold">{{ __('House') }}: {{ Number::currency($collab->pivot->hourly_rate_house, 'GBP') }}/h</span>
                            <span class="block text-xs font-semibold">{{ __('Office') }}: {{ Number::currency($collab->pivot->hourly_rate_office, 'GBP') }}/h</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown align="end">
                                <flux:button variant="ghost" size="xs" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil" wire:click="openEditModal({{ $collab->id }})">{{ __('Edit') }}</flux:menu.item>
                                    @if($collab->id !== auth()->id())
                                        <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $collab->id }})">{{ __('Delete') }}</flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        @if(count($this->collaborators) === 0)
            <div class="p-12 text-center text-zinc-500 dark:text-zinc-400">
                <flux:icon.users class="w-12 h-12 mx-auto mb-4 opacity-20" />
                <p>{{ __('No collaborators found.') }}</p>
            </div>
        @endif
    </div>

    <!-- Add/Edit Collaborator Modal -->
    <flux:modal wire:model="showModal" class="md:w-96">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $userId ? __('Edit Collaborator') : __('Add Collaborator') }}</flux:heading>
                <flux:subheading>{{ __('Configure your collaborator details.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Name') }}</flux:label>
                    <flux:input wire:model="name" required />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Email') }}</flux:label>
                    <flux:input type="email" wire:model="email" required />
                    <flux:error name="email" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Password') }}</flux:label>
                    <flux:input type="password" wire:model="password" :required="!$userId" placeholder="{{ $userId ? __('Leave blank to keep current') : '' }}" viewable />
                    <flux:error name="password" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Role') }}</flux:label>
                    <flux:select wire:model="role">
                        <flux:select.option value="collaborator">{{ __('Collaborator (Basic Access)') }}</flux:select.option>
                        <flux:select.option value="management">{{ __('Management (Full Access)') }}</flux:select.option>
                    </flux:select>
                    <flux:error name="role" />
                </flux:field>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>{{ __('Hourly Rate (House)') }}</flux:label>
                        <flux:input type="number" step="0.01" wire:model="hourly_rate_house" icon="banknotes" required />
                        <flux:error name="hourly_rate_house" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Hourly Rate (Office)') }}</flux:label>
                        <flux:input type="number" step="0.01" wire:model="hourly_rate_office" icon="banknotes" required />
                        <flux:error name="hourly_rate_office" />
                    </flux:field>
                </div>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button wire:click="$set('showModal', false)" variant="ghost">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
