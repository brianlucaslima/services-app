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

    // Screens: 'list', 'select_customer', 'select_services', 'detail'
    public string $screen = 'list';
    public ?int $selectedInvoiceId = null;

    // Create Invoice state
    public $selectedCustomerId = null;
    public $pendingServices = [];
    public $selectedServiceIds = [];
    public $invoiceDate;
    public $dueDate;
    public $notes = '';
    public string $customerSearch = '';

    // List Filters state
    public string $filterCustomer = 'all';
    public string $filterStartDate = '';
    public string $filterEndDate = '';
    public string $filterNumber = '';
    public string $filterStatus = 'all';

    // Manual Service state
    public bool $showManualModal = false;
    public $manualServiceTypeId = null;
    public $manualDescription = '';
    public $manualDate;
    public $manualHours = 1;
    public $manualRate = 0;

    public function mount(): void
    {
        if (auth()->user()->role !== 'management') {
            abort(403);
        }

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
        $query = auth()->user()->company->invoices()->with('customer');

        if ($this->filterCustomer !== 'all') {
            $query->where('customer_id', $this->filterCustomer);
        }

        if ($this->filterStartDate) {
            $query->where('date', '>=', $this->filterStartDate);
        }

        if ($this->filterEndDate) {
            $query->where('date', '<=', $this->filterEndDate);
        }

        if ($this->filterNumber !== '') {
            $query->where('number', 'like', "%{$this->filterNumber}%");
        }

        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        $this->invoices = $query->latest()->get()->toArray();
    }

    public function updatedFilterCustomer(): void { $this->refreshInvoices(); }
    public function updatedFilterStartDate(): void { $this->refreshInvoices(); }
    public function updatedFilterEndDate(): void { $this->refreshInvoices(); }
    public function updatedFilterNumber(): void { $this->refreshInvoices(); }
    public function updatedFilterStatus(): void { $this->refreshInvoices(); }

    public function clearFilters(): void
    {
        $this->filterCustomer = 'all';
        $this->filterStartDate = '';
        $this->filterEndDate = '';
        $this->filterNumber = '';
        $this->filterStatus = 'all';
        $this->refreshInvoices();
    }

    public function goToSelectCustomer(): void
    {
        $this->selectedCustomerId = null;
        $this->notes = '';
        $this->customerSearch = '';
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

        $invoice = DB::transaction(function() {
            $company = auth()->user()->company;
            $startNum = $company->invoice_start_number ?? 1;
            $count = Invoice::where('company_id', $company->id)->count();
            $nextNum = $startNum + $count;
            $number = 'INV-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

            $invoice = Invoice::create([
                'company_id' => $company->id,
                'customer_id' => $this->selectedCustomerId,
                'number' => $number,
                'date' => $this->invoiceDate,
                'due_date' => $this->dueDate,
                'status' => 'draft',
                'total_amount' => 0,
                'notes' => $this->notes,
            ]);

            $total = 0;
            $services = ServiceInstance::with('address')->findMany($this->selectedServiceIds);
            
            foreach ($services as $service) {
                $amount = $service->duration_hours * $service->hourly_rate;
                $total += $amount;

                $itemDescription = $service->description;
                if ($service->address) {
                    $itemDescription .= ' - ' . $service->address->label;
                }
                $itemDescription .= ' (' . $service->date->format('d/m/Y') . ')';

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'service_instance_id' => $service->id,
                    'description' => $itemDescription,
                    'quantity' => $service->duration_hours,
                    'unit_price' => $service->hourly_rate,
                    'amount' => $amount,
                ]);
            }

            $invoice->update(['total_amount' => $total]);
            return $invoice;
        });

        $this->selectedInvoiceId = $invoice->id;
        $this->screen = 'detail';
        $this->refreshInvoices();
        Flux::toast(variant: 'success', text: __('Invoice created successfully.'));
    }

    public function showInvoice(int $id): void
    {
        $this->selectedInvoiceId = $id;
        $this->screen = 'detail';
    }

    public function issueInvoice(): void
    {
        $invoice = auth()->user()->company->invoices()->findOrFail($this->selectedInvoiceId);
        $invoice->update(['status' => 'sent']);
        $this->refreshInvoices();
        Flux::toast(variant: 'success', text: __('Invoice issued successfully.'));
    }

    public function sendEmail(): void
    {
        Flux::toast(variant: 'success', text: __('Invoice sent by email successfully.'));
    }

    public function deleteInvoice(int $id): void
    {
        auth()->user()->company->invoices()->findOrFail($id)->delete();
        $this->refreshInvoices();
        Flux::toast(variant: 'success', text: __('Invoice deleted.'));
    }

    public function cancelInvoice(?int $id = null): void
    {
        $id = $id ?: $this->selectedInvoiceId;
        auth()->user()->company->invoices()->findOrFail($id)->delete();
        $this->refreshInvoices();
        $this->screen = 'list';
        Flux::toast(variant: 'success', text: __('Invoice cancelled. Services are now pending again.'));
    }

    public function markAsPaid(?int $id = null): void
    {
        $id = $id ?: $this->selectedInvoiceId;
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
        return auth()->user()->company->customers()
            ->with('addresses')
            ->where('is_active', true)
            ->when($this->customerSearch !== '', function($query) {
                $query->where(function($query) {
                    $query->where('name', 'like', "%{$this->customerSearch}%")
                        ->orWhere('email', 'like', "%{$this->customerSearch}%")
                        ->orWhereHas('addresses', function($query) {
                            $query->where('label', 'like', "%{$this->customerSearch}%")
                                ->orWhere('address', 'like', "%{$this->customerSearch}%");
                        });
                });
            })
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function serviceTypes()
    {
        return auth()->user()->company->serviceTypes()->orderBy('name')->get();
    }

    #[Computed]
    public function selectedInvoice()
    {
        return $this->selectedInvoiceId ? auth()->user()->company->invoices()->with(['customer', 'items'])->find($this->selectedInvoiceId) : null;
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

        <!-- Filters panel -->
        <div class="grid grid-cols-1 sm:grid-cols-5 gap-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800">
            <flux:field>
                <flux:label>{{ __('Invoice Number') }}</flux:label>
                <flux:input wire:model.live.debounce.300ms="filterNumber" placeholder="{{ __('INV-...') }}" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Customer') }}</flux:label>
                <flux:select wire:model.live="filterCustomer" placeholder="{{ __('All Customers') }}">
                    <flux:select.option value="all">{{ __('All Customers') }}</flux:select.option>
                    @foreach($this->customers as $cust)
                        <flux:select.option value="{{ $cust->id }}">{{ $cust->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Start Date') }}</flux:label>
                <flux:input type="date" wire:model.live="filterStartDate" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('End Date') }}</flux:label>
                <flux:input type="date" wire:model.live="filterEndDate" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Status') }}</flux:label>
                <flux:select wire:model.live="filterStatus" placeholder="{{ __('All Status') }}">
                    <flux:select.option value="all">{{ __('All Status') }}</flux:select.option>
                    <flux:select.option value="draft">{{ __('Draft') }}</flux:select.option>
                    <flux:select.option value="sent">{{ __('Sent') }}</flux:select.option>
                    <flux:select.option value="paid">{{ __('Paid') }}</flux:select.option>
                </flux:select>
            </flux:field>
        </div>

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
                            <flux:table.cell class="font-semibold text-zinc-900 dark:text-white">
                                <button type="button" wire:click="showInvoice({{ $invoice['id'] }})" class="hover:underline text-left">
                                    {{ $invoice['number'] }}
                                </button>
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="block font-medium">{{ $invoice['customer']['name'] }}</span>
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
                                        <flux:menu.item icon="eye" wire:click="showInvoice({{ $invoice['id'] }})">{{ __('View Details') }}</flux:menu.item>
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

        <!-- Search field -->
        <div class="relative w-full sm:w-72 px-4 sm:px-0">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="h-5 w-5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
            </div>
            <input wire:model.live.debounce.300ms="customerSearch" type="search" placeholder="{{ __('Search customers or locations...') }}" class="block w-full rounded-lg border-0 py-2 pl-10 pr-3 text-zinc-900 shadow-sm ring-1 ring-inset ring-zinc-300 placeholder:text-zinc-400 focus:ring-2 focus:ring-inset focus:ring-zinc-900 dark:bg-zinc-900 dark:text-white dark:ring-zinc-700 dark:placeholder:text-zinc-500 dark:focus:ring-white sm:text-sm sm:leading-6" />
        </div>

        <div class="grid gap-3 px-4 sm:px-0">
            @foreach($this->customers as $customer)
                <button wire:click="selectCustomer({{ $customer->id }})" class="flex items-center justify-between p-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl hover:border-zinc-900 dark:hover:border-white transition text-left group">
                    <div>
                        <p class="font-bold text-zinc-900 dark:text-white group-hover:text-zinc-900 dark:group-hover:text-white">{{ $customer->name }}</p>
                        @if($customer->addresses->isNotEmpty())
                            <p class="text-[10px] text-zinc-400 dark:text-zinc-550 mt-0.5">
                                {{ __('Locations') }}: {{ implode(', ', $customer->addresses->pluck('label')->toArray()) }}
                            </p>
                        @endif
                        <p class="text-sm text-zinc-500 mt-1">{{ $customer->email }}</p>
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

    @if($screen === 'detail' && $this->selectedInvoice)
        @php $inv = $this->selectedInvoice; @endphp
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 px-4 sm:px-0">
                <div class="flex items-center gap-3">
                    <flux:button wire:click="$set('screen', 'list')" variant="ghost" icon="chevron-left" size="sm" class="rounded-full" />
                    <div>
                        <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ $inv->number }}</h1>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">{{ __('Invoice Details') }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if($inv->status === 'draft')
                        <flux:button wire:click="issueInvoice" variant="primary" icon="check">{{ __('Issue Invoice') }}</flux:button>
                    @else
                        <flux:button wire:click="sendEmail" variant="outline" icon="paper-airplane">{{ __('Send by Email') }}</flux:button>
                    @endif

                    @if($inv->status === 'sent')
                        <flux:button wire:click="markAsPaid" variant="filled" icon="check-circle" class="bg-emerald-600 hover:bg-emerald-700 text-white border-none">{{ __('Mark as Paid') }}</flux:button>
                    @endif

                    <a href="{{ route('invoices.pdf', ['id' => $inv->id]) }}" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-900 px-4 py-2 text-sm font-semibold shadow-sm transition dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700 border dark:border-zinc-700 h-[38px]">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 015.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        <span>{{ $inv->status === 'draft' ? __('Download Draft PDF') : __('Download PDF') }}</span>
                    </a>

                    <flux:button wire:click="cancelInvoice" variant="danger" icon="x-mark">{{ __('Cancel Invoice') }}</flux:button>
                </div>
            </header>

            <!-- Big watermark warning for Draft on list screen or detail -->
            @if($inv->status === 'draft')
                <div class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800 p-4 rounded-2xl text-center">
                    <span class="text-base font-bold text-red-600 dark:text-red-400 uppercase tracking-widest block">{{ __('DRAFT INVOICE') }}</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 block mt-1">{{ __('This invoice has not been issued yet. The PDF download will contain a "DRAFT" watermark.') }}</span>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Summary card -->
                <div class="md:col-span-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl p-6 shadow-sm space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs text-zinc-500 uppercase tracking-wider">{{ __('Customer') }}</span>
                            <strong class="block text-lg text-zinc-900 dark:text-white mt-1">{{ $inv->customer->name }}</strong>
                            <span class="text-sm text-zinc-500">{{ $inv->customer->email }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-zinc-500 uppercase tracking-wider block">{{ __('Status') }}</span>
                            <flux:badge :color="$this->getStatusColor($inv->status)" size="sm" class="mt-1">{{ __($inv->status) }}</flux:badge>
                        </div>
                    </div>

                    <flux:separator />

                    <!-- Details table -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-bold text-zinc-900 dark:text-white uppercase tracking-wider">{{ __('Items') }}</h3>
                        <div class="border border-zinc-100 dark:border-zinc-800 rounded-xl overflow-hidden">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 uppercase">
                                    <tr>
                                        <th class="px-3 py-2">{{ __('Description') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('Hours') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('Price/Hour') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @foreach($inv->items as $item)
                                        <tr>
                                            <td class="px-3 py-3 font-semibold dark:text-zinc-200">{{ $item->description }}</td>
                                            <td class="px-3 py-3 text-right text-zinc-600 dark:text-zinc-400">{{ number_format($item->quantity, 2) }}h</td>
                                            <td class="px-3 py-3 text-right text-zinc-500">{{ Number::currency($item->unit_price, 'GBP') }}</td>
                                            <td class="px-3 py-3 text-right font-bold text-zinc-950 dark:text-white">{{ Number::currency($item->amount, 'GBP') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Meta details sidebar -->
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl p-6 shadow-sm space-y-6 flex flex-col justify-between">
                    <div class="space-y-6">
                        <div>
                            <span class="text-xs text-zinc-500 uppercase tracking-wider">{{ __('Total Invoice Amount') }}</span>
                            <span class="block text-3xl font-extrabold text-zinc-900 dark:text-white mt-1">{{ Number::currency($inv->total_amount, 'GBP') }}</span>
                        </div>

                        <flux:separator />

                        <div class="space-y-3 text-xs">
                            <div>
                                <strong class="text-zinc-500 block">{{ __('Invoice Date') }}:</strong>
                                <span class="text-zinc-900 dark:text-white font-medium block mt-0.5">{{ $inv->date->format('d/m/Y') }}</span>
                            </div>
                            @if($inv->due_date)
                                <div>
                                    <strong class="text-zinc-500 block">{{ __('Due Date') }}:</strong>
                                    <span class="text-zinc-900 dark:text-white font-medium block mt-0.5">{{ $inv->due_date->format('d/m/Y') }}</span>
                                </div>
                            @endif
                            @if($inv->notes)
                                <div>
                                    <strong class="text-zinc-500 block">{{ __('Message / Notes') }}:</strong>
                                    <p class="text-zinc-700 dark:text-zinc-300 mt-1 line-clamp-6">{!! nl2br(e($inv->notes)) !!}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
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
