<?php

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\ServiceInstance;
use App\Models\ServiceType;
use App\Models\InvoiceItem;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Flux\Flux;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    public $invoices = [];

    // Screens: 'list', 'select_customer', 'select_services'
    public string $screen = 'list';

    // Create Invoice state
    public $selectedCustomerId = null;
    public $pendingServices = [];
    public $selectedServiceIds = [];
    public $invoiceDate;
    public $dueDate;
    public $notes = '';

    // Manual Service state
    public bool $showManualModal = false;
    public $manualServiceTypeId = null;
    public $manualDescription = '';
    public $manualDate;
    public $manualHours = 1;
    public $manualRate = 0;

    public function mount(): void
    {
        $this->invoiceDate = now()->format('Y-m-d');
        $this->dueDate = now()->addDays(14)->format('Y-m-d');
        $this->manualDate = now()->format('Y-m-d');
        $this->refreshInvoices();
    }

    public function rendering($view): void
    {
        $view->title(__('Invoices'));
    }

    public function refreshInvoices(): void
    {
        $this->invoices = auth()->user()->company->invoices()
            ->with('customer')
            ->latest()
            ->get()
            ->toArray();
    }

    public function goToSelectCustomer(): void
    {
        $this->selectedCustomerId = null;
        $this->notes = '';
        $this->screen = 'list'; // To trigger state cleanup
        $this->screen = 'select_customer';
    }

    public function selectCustomer(int $id): void
    {
        $this->selectedCustomerId = $id;
        $this->loadPendingServices();
        $this->notes = auth()->user()->company->default_invoice_message ?? '';
        $this->screen = 'select_services';
    }

    public function loadPendingServices(): void
    {
        $this->pendingServices = ServiceInstance::query()
            ->where('company_id', auth()->user()->company->id)
            ->where('status', 'completed')
            ->where('customer_id', $this->selectedCustomerId)
            ->whereDoesntHave('invoiceItem')
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'date' => $item->date->format('d/m/y'),
                'description' => $item->description,
                'hours' => $item->duration_hours,
                'rate' => $item->hourly_rate,
                'total' => $item->duration_hours * $item->hourly_rate,
            ])
            ->toArray();
            
        $this->selectedServiceIds = array_column($this->pendingServices, 'id');
    }

    public function openManualModal(): void
    {
        $this->manualServiceTypeId = null;
        $this->manualDescription = '';
        $this->manualHours = 1;
        // Try to get the rate from the customer's first address if possible
        $customer = auth()->user()->company->customers()->with('addresses')->find($this->selectedCustomerId);
        if ($customer && $customer->addresses->isNotEmpty()) {
            $this->manualRate = $customer->addresses->first()->hourly_rate;
        }
        
        $this->showManualModal = true;
    }

    public function saveManualService(): void
    {
        $this->validate([
            'manualServiceTypeId' => 'required_without:manualDescription',
            'manualDate' => 'required|date',
            'manualHours' => 'required|numeric|min:0',
            'manualRate' => 'required|numeric|min:0',
        ]);

        $description = $this->manualDescription;
        if (empty($description) && $this->manualServiceTypeId) {
            $description = auth()->user()->company->serviceTypes()->findOrFail($this->manualServiceTypeId)->name;
        }

        ServiceInstance::create([
            'company_id' => auth()->user()->company->id,
            'customer_id' => $this->selectedCustomerId,
            'service_type_id' => $this->manualServiceTypeId,
            'description' => $description,
            'date' => $this->manualDate,
            'time' => '12:00',
            'duration_hours' => $this->manualHours,
            'hourly_rate' => $this->manualRate,
            'status' => 'completed',
        ]);

        $this->showManualModal = false;
        $this->loadPendingServices();
        Flux::toast(variant: 'success', text: __('Manual service added.'));
    }

    public function generateInvoice(): void
    {
        if (empty($this->selectedServiceIds)) {
            Flux::toast(variant: 'danger', text: __('Select at least one service.'));
            return;
        }

        DB::transaction(function() {
            // Simple invoice number generation
            $count = Invoice::where('company_id', auth()->user()->company->id)->count() + 1;
            $number = 'INV-' . now()->format('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            $invoice = Invoice::create([
                'company_id' => auth()->user()->company->id,
                'customer_id' => $this->selectedCustomerId,
                'number' => $number,
                'date' => $this->invoiceDate,
                'due_date' => $this->dueDate,
                'status' => 'draft',
                'total_amount' => 0,
                'notes' => $this->notes,
            ]);

            $total = 0;
            $services = ServiceInstance::findMany($this->selectedServiceIds);
            
            foreach ($services as $service) {
                $amount = $service->duration_hours * $service->hourly_rate;
                $total += $amount;

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'service_instance_id' => $service->id,
                    'description' => $service->description . ' (' . $service->date->format('d/m/Y') . ')',
                    'quantity' => $service->duration_hours,
                    'unit_price' => $service->hourly_rate,
                    'amount' => $amount,
                ]);
            }

            $invoice->update(['total_amount' => $total]);
        });

        $this->screen = 'list';
        $this->refreshInvoices();
        Flux::toast(variant: 'success', text: __('Invoice created successfully.'));
    }

    public function deleteInvoice(int $id): void
    {
        auth()->user()->company->invoices()->findOrFail($id)->delete();
        $this->refreshInvoices();
        Flux::toast(variant: 'success', text: __('Invoice deleted.'));
    }

    public function cancelInvoice(int $id): void
    {
        auth()->user()->company->invoices()->findOrFail($id)->delete();
        $this->refreshInvoices();
        Flux::toast(variant: 'success', text: __('Invoice cancelled. Services are now pending again.'));
    }

    public function markAsPaid(int $id): void
    {
        auth()->user()->company->invoices()->findOrFail($id)->update(['status' => 'paid']);
        $this->refreshInvoices();
        Flux::toast(variant: 'success', text: __('Invoice marked as paid.'));
    }

    public function getStatusColor(string $status): string
    {
        return match($status) {
            'paid' => 'emerald',
            'sent' => 'blue',
            'cancelled' => 'red',
            default => 'zinc',
        };
    }

    #[Computed]
    public function customers()
    {
        return auth()->user()->company->customers()->where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function serviceTypes()
    {
        return auth()->user()->company->serviceTypes()->orderBy('name')->get();
    }
};

?>

<div class="mx-auto max-w-5xl space-y-6 pb-24">
    @if($screen === 'list')
        <header class="flex items-center justify-between px-4 sm:px-0">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('Invoices') }}</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Manage your billing and payments.') }}</p>
            </div>
            <flux:button wire:click="goToSelectCustomer" variant="primary" icon="plus">{{ __('New Invoice') }}</flux:button>
        </header>

        <div class="bg-white dark:bg-zinc-900 border-y sm:border border-zinc-200 dark:border-zinc-700 sm:rounded-xl overflow-hidden shadow-sm">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Number') }}</flux:table.column>
                    <flux:table.column>{{ __('Customer') }}</flux:table.column>
                    <flux:table.column class="hidden md:table-cell">{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($invoices as $invoice)
                        <flux:table.row :key="$invoice['id']">
                            <flux:table.cell class="font-medium">{{ $invoice['number'] }}</flux:table.cell>
                            <flux:table.cell>
                                <span class="block">{{ $invoice['customer']['name'] }}</span>
                                <span class="md:hidden text-xs text-zinc-500">{{ \Carbon\Carbon::parse($invoice['date'])->format('d/m/y') }}</span>
                            </flux:table.cell>
                            <flux:table.cell class="hidden md:table-cell">{{ \Carbon\Carbon::parse($invoice['date'])->format('d/m/Y') }}</flux:table.cell>
                            <flux:table.cell class="font-semibold">{{ Number::currency($invoice['total_amount'] ?? 0, 'GBP') }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="$this->getStatusColor($invoice['status'])" inset="top">{{ __($invoice['status']) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:dropdown align="end">
                                    <flux:button variant="ghost" size="xs" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        @if($invoice['status'] !== 'paid')
                                            <flux:menu.item icon="check" wire:click="markAsPaid({{ $invoice['id'] }})">{{ __('Mark as Paid') }}</flux:menu.item>
                                        @endif
                                        <flux:menu.item icon="x-mark" variant="danger" wire:click="cancelInvoice({{ $invoice['id'] }})">{{ __('Cancel Invoice') }}</flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" wire:click="deleteInvoice({{ $invoice['id'] }})">{{ __('Delete Permanent') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            @if(empty($invoices))
                <div class="p-12 text-center text-zinc-500 dark:text-zinc-400">
                    <flux:icon.banknotes class="w-12 h-12 mx-auto mb-4 opacity-20" />
                    <p>{{ __('No invoices found.') }}</p>
                </div>
            @endif
        </div>
    @endif

    @if($screen === 'select_customer')
        <header class="flex items-center gap-3 px-4 sm:px-0">
            <flux:button wire:click="$set('screen', 'list')" variant="ghost" icon="chevron-left" size="sm" class="rounded-full" />
            <div>
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('Create Invoice') }}</h1>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">{{ __('Step 1: Select Customer') }}</p>
            </div>
        </header>

        <div class="grid gap-3 px-4 sm:px-0">
            @foreach($this->customers as $customer)
                <button wire:click="selectCustomer({{ $customer->id }})" class="flex items-center justify-between p-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl hover:border-zinc-900 dark:hover:border-white transition text-left group">
                    <div>
                        <p class="font-bold text-zinc-900 dark:text-white group-hover:text-zinc-900 dark:group-hover:text-white">{{ $customer->name }}</p>
                        <p class="text-sm text-zinc-500">{{ $customer->email }}</p>
                    </div>
                    <flux:icon.chevron-right class="w-5 h-5 text-zinc-300" />
                </button>
            @endforeach
        </div>
    @endif

    @if($screen === 'select_services')
        <header class="flex items-center justify-between px-4 sm:px-0">
            <div class="flex items-center gap-3">
                <flux:button wire:click="$set('screen', 'select_customer')" variant="ghost" icon="chevron-left" size="sm" class="rounded-full" />
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ Customer::find($selectedCustomerId)->name }}</h1>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">{{ __('Step 2: Select Services') }}</p>
                </div>
            </div>
            <flux:button wire:click="openManualModal" variant="outline" size="sm" icon="plus" class="rounded-full">{{ __('Add Manual') }}</flux:button>
        </header>

        <div class="space-y-6">
            <div class="bg-white dark:bg-zinc-900 border-y sm:border border-zinc-200 dark:border-zinc-700 sm:rounded-2xl overflow-hidden">
                <table class="w-full text-sm text-left">
                    <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 uppercase text-[10px] tracking-wider">
                        <tr>
                            <th class="px-4 py-3 w-10">
                                <input type="checkbox"
                                    x-on:change="
                                        if ($el.checked) {
                                            $wire.selectedServiceIds = @js(array_column($pendingServices, 'id'))
                                        } else {
                                            $wire.selectedServiceIds = []
                                        }
                                    "
                                    class="rounded border-zinc-300"
                                    {{ count($selectedServiceIds) === count($pendingServices) && count($pendingServices) > 0 ? 'checked' : '' }}
                                />
                            </th>
                            <th class="px-4 py-3">{{ __('Description') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse($pendingServices as $service)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                                <td class="px-4 py-4 text-center">
                                    <input type="checkbox" wire:model.live="selectedServiceIds" value="{{ $service['id'] }}" class="rounded border-zinc-300" />
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-medium text-zinc-900 dark:text-white">{{ $service['description'] }}</p>
                                    <p class="text-xs text-zinc-500">{{ $service['date'] }} • {{ $service['hours'] }}h</p>
                                </td>
                                <td class="px-4 py-4 text-right font-semibold text-zinc-900 dark:text-white">
                                    {{ Number::currency($service['total'], 'GBP') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-12 text-center text-zinc-400 italic">
                                    <flux:icon.document-magnifying-glass class="w-10 h-10 mx-auto mb-2 opacity-20" />
                                    <p>{{ __('No pending services found for this customer.') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(!empty($selectedServiceIds))
                <div class="fixed bottom-0 left-0 right-0 bg-white dark:bg-zinc-900 border-t border-zinc-200 dark:border-zinc-700 p-4 sm:relative sm:border sm:rounded-2xl sm:p-6 shadow-lg sm:shadow-sm animate-in slide-in-from-bottom duration-300 z-50">
                    <div class="max-w-5xl mx-auto space-y-4">
                        <flux:field>
                            <flux:label>{{ __('Invoice Message') }}</flux:label>
                            <flux:textarea wire:model="notes" rows="2" placeholder="{{ __('Add a message that will appear on this invoice...') }}" />
                        </flux:field>

                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                            <div class="text-center sm:text-left">
                                <p class="text-xs text-zinc-500 uppercase tracking-wider">{{ __('Total Selected') }} ({{ count($selectedServiceIds) }})</p>
                                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                                    {{ Number::currency(collect($pendingServices)->whereIn('id', $selectedServiceIds)->sum('total'), 'GBP') }}
                                </p>
                            </div>
                            
                            <div class="flex items-center gap-3 w-full sm:w-auto">
                                <div class="grid grid-cols-2 gap-3 w-full">
                                    <flux:field>
                                        <flux:label class="hidden sm:block">{{ __('Invoice Date') }}</flux:label>
                                        <flux:input type="date" wire:model="invoiceDate" size="sm" />
                                    </flux:field>
                                    <flux:field>
                                        <flux:label class="hidden sm:block">{{ __('Due Date') }}</flux:label>
                                        <flux:input type="date" wire:model="dueDate" size="sm" />
                                    </flux:field>
                                </div>
                                <flux:button wire:click="generateInvoice" variant="primary" class="h-auto py-3 px-8 rounded-xl font-bold">{{ __('Generate') }}</flux:button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- Manual Service Modal -->
    <flux:modal wire:model="showManualModal" class="md:w-96">
        <form wire:submit="saveManualService" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add Manual Service') }}</flux:heading>
                <flux:subheading>{{ __('One-off work not linked to a schedule.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Service Type') }}</flux:label>
                    <flux:select wire:model="manualServiceTypeId" placeholder="{{ __('Select a service type...') }}">
                        @foreach($this->serviceTypes as $type)
                            <flux:select.option value="{{ $type->id }}">{{ $type->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Custom Description (Optional)') }}</flux:label>
                    <flux:input wire:model="manualDescription" placeholder="{{ __('Extra Cleaning, Repairs...') }}" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Date') }}</flux:label>
                    <flux:input type="date" wire:model="manualDate" required />
                </flux:field>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>{{ __('Hours') }}</flux:label>
                        <flux:input type="number" step="0.5" wire:model="manualHours" required />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Rate') }}</flux:label>
                        <flux:input type="number" step="0.01" wire:model="manualRate" icon="banknotes" required />
                    </flux:field>
                </div>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button wire:click="$set('showManualModal', false)" variant="ghost">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Add to List') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
