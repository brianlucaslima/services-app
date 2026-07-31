<?php

use App\Models\Quote;
use App\Models\Customer;
use App\Models\ServiceType;
use App\Models\QuoteItem;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Flux\Flux;
use Illuminate\Support\Facades\Mail;
use App\Mail\QuoteMail;
use Barryvdh\DomPDF\Facade\Pdf;

new class extends Component
{
    public $quotes = [];
    public string $screen = 'list'; // 'list', 'form'

    // Quote Form State
    public ?int $quoteId = null;
    public $customerId = null;
    public $quoteDate;
    public $expiryDate;
    public $notes = '';
    public array $items = [];

    // Filters State
    public string $filterCustomer = 'all';
    public string $filterStatus = 'all';
    public string $filterNumber = '';

    public function mount(): void
    {
        if (auth()->user()->role !== 'management') {
            abort(403);
        }

        $this->quoteDate = now()->format('Y-m-d');
        $this->expiryDate = now()->addDays(14)->format('Y-m-d');
        $this->refreshQuotes();
    }

    public function rendering($view): void
    {
        $view->title(__('Quotes & Estimates'));
    }

    public function refreshQuotes(): void
    {
        $query = auth()->user()->company->quotes()->with('customer');

        if ($this->filterCustomer !== 'all') {
            $query->where('customer_id', $this->filterCustomer);
        }

        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterNumber !== '') {
            $query->where('number', 'like', "%{$this->filterNumber}%");
        }

        $this->quotes = $query->latest()->get();
    }

    #[Computed]
    public function customers()
    {
        return auth()->user()->company->customers()->orderBy('name')->get();
    }

    #[Computed]
    public function serviceTypes()
    {
        return auth()->user()->company->serviceTypes()->orderBy('name')->get();
    }

    public function openCreateForm(): void
    {
        $this->resetForm();
        $this->addItem(); // Start with at least one item row
        $this->notes = auth()->user()->company->default_quote_message ?? '';
        $this->screen = 'form';
    }

    public function openEditForm(int $id): void
    {
        $this->resetForm();
        $quote = auth()->user()->company->quotes()->with('items')->findOrFail($id);
        
        $this->quoteId = $quote->id;
        $this->customerId = $quote->customer_id;
        $this->quoteDate = $quote->date->format('Y-m-d');
        $this->expiryDate = $quote->expiry_date->format('Y-m-d');
        $this->notes = $quote->notes ?? '';
        
        foreach ($quote->items as $item) {
            $this->items[] = [
                'service_type_id' => $item->service_type_id,
                'description' => $item->description,
                'notes' => $item->notes ?? '',
                'quantity' => $item->billing_type === 'hourly' ? \App\Brain\Helpers\TimeHelper::decimalToColon((float) $item->quantity) : (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'billing_type' => $item->billing_type ?? 'hourly',
            ];
        }

        $this->screen = 'form';
    }

    public function addItem(): void
    {
        $this->items[] = [
            'service_type_id' => '',
            'description' => '',
            'notes' => '',
            'quantity' => 1,
            'unit_price' => 0.00,
            'billing_type' => 'hourly',
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function updatedItems($value, $key): void
    {
        // When service type is chosen, autofill the name and rate if possible
        if (str_contains($key, 'service_type_id')) {
            $parts = explode('.', $key);
            $index = (int) $parts[0];
            $typeId = $this->items[$index]['service_type_id'];

            if ($typeId) {
                $type = ServiceType::find($typeId);
                if ($type) {
                    $this->items[$index]['description'] = $type->name;
                    // Try to guess default price or keep 0
                    $this->items[$index]['unit_price'] = 25.00; // Sensible default
                }
            } else {
                $this->items[$index]['description'] = '';
            }
        }

        if (str_contains($key, 'billing_type')) {
            $parts = explode('.', $key);
            $index = (int) $parts[0];
            $billingType = $this->items[$index]['billing_type'];
            $quantity = $this->items[$index]['quantity'] ?? 0;

            if ($billingType === 'hourly') {
                $this->items[$index]['quantity'] = \App\Brain\Helpers\TimeHelper::decimalToColon($quantity);
            } else {
                $this->items[$index]['quantity'] = \App\Brain\Helpers\TimeHelper::humanToDecimal($quantity);
            }
        }
    }

    public function save(): void
    {
        foreach ($this->items as $idx => $item) {
            if (($item['billing_type'] ?? 'hourly') === 'hourly') {
                $this->items[$idx]['quantity'] = \App\Brain\Helpers\TimeHelper::humanToDecimal($item['quantity']);
            } else {
                $this->items[$idx]['quantity'] = (float) $item['quantity'];
            }
        }

        $this->validate([
            'customerId' => 'required',
            'quoteDate' => 'required|date',
            'expiryDate' => 'required|date|after_or_equal:quoteDate',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.notes' => 'nullable|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        \App\Brain\Quotes\Workflows\SaveQuoteWorkflow::run([
            'companyId' => auth()->user()->company->id,
            'quoteId' => $this->quoteId,
            'customerId' => $this->customerId,
            'quoteDate' => $this->quoteDate,
            'expiryDate' => $this->expiryDate,
            'notes' => $this->notes ?: null,
            'items' => $this->items,
        ]);

        if ($this->quoteId) {
            Flux::toast(variant: 'success', text: __('Quote updated successfully.'));
        } else {
            Flux::toast(variant: 'success', text: __('Quote created successfully.'));
        }

        $this->screen = 'list';
        $this->refreshQuotes();
        $this->resetForm();
    }

    public function sendQuoteEmail(int $id): void
    {
        $quote = auth()->user()->company->quotes()->with(['customer', 'company', 'items'])->findOrFail($id);

        if (empty($quote->customer->email)) {
            Flux::toast(variant: 'danger', text: __('This customer does not have an email address.'));
            return;
        }

        // 1. Generate PDF
        app()->setLocale('en'); // Generate PDF in English
        $pdf = Pdf::loadView('pdf.quote', ['quote' => $quote]);
        $pdfData = $pdf->output();
        $fileName = 'quote-' . strtolower($quote->number) . '.pdf';

        // 2. Send email
        try {
            Mail::to($quote->customer->email)->send(new QuoteMail($quote, $pdfData, $fileName));
            
            // Update status to sent
            $quote->update(['status' => 'sent']);
            
            Flux::toast(variant: 'success', text: __('Quote email sent successfully.'));
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', text: __('Failed to send email: ') . $e->getMessage());
        }

        $this->refreshQuotes();
    }

    public function changeStatus(int $id, string $status): void
    {
        $quote = auth()->user()->company->quotes()->findOrFail($id);

        if ($quote->hasActiveInvoice()) {
            Flux::toast(variant: 'danger', text: __('Cannot change the status of a quote with an active invoice.'));
            return;
        }

        $quote->update(['status' => $status]);
        Flux::toast(variant: 'success', text: __('Quote status updated to :status.', ['status' => __($status)]));
        $this->refreshQuotes();
    }

    public function convertToInvoice(int $id)
    {
        $quote = auth()->user()->company->quotes()->findOrFail($id);

        if ($quote->hasActiveInvoice()) {
            Flux::toast(variant: 'danger', text: __('This quote already has an active invoice.'));
            return;
        }

        $action = \App\Brain\Quotes\Actions\ConvertQuoteToInvoiceAction::run([
            'quoteId' => $id,
        ]);

        $invoice = \App\Models\Invoice::findOrFail($action->invoiceId);

        Flux::toast(variant: 'success', text: __('Quote successfully converted to Invoice draft!'));
        
        return $this->redirect(route('invoices', ['uuid' => $invoice->uuid]), navigate: true);
    }

    public function delete(int $id): void
    {
        $quote = auth()->user()->company->quotes()->findOrFail($id);

        if ($quote->hasActiveInvoice()) {
            Flux::toast(variant: 'danger', text: __('Cannot delete a quote with an active invoice.'));
            return;
        }

        $quote->delete();
        Flux::toast(variant: 'success', text: __('Quote deleted successfully.'));
        $this->refreshQuotes();
    }

    public function resetForm(): void
    {
        $this->quoteId = null;
        $this->customerId = null;
        $this->quoteDate = now()->format('Y-m-d');
        $this->expiryDate = now()->addDays(14)->format('Y-m-d');
        $this->notes = '';
        $this->items = [];
    }

    public function parseQuantity(string|float|int|null $value): float
    {
        return \App\Brain\Helpers\TimeHelper::humanToDecimal($value);
    }
};

?>

<div class="mx-auto max-w-5xl space-y-6 pb-24">
    @if($screen === 'list')
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between sm:px-0">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('Quotes & Estimates') }}</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Create and manage estimates for your customers.') }}</p>
            </div>
            <flux:button wire:click="openCreateForm" variant="primary" icon="plus" class="rounded-full">
                {{ __('Add Quote') }}
            </flux:button>
        </header>

        <!-- Filters Section -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800">
            <flux:field>
                <flux:label>{{ __('Customer') }}</flux:label>
                <flux:select wire:model.live="filterCustomer">
                    <flux:select.option value="all">{{ __('All Customers') }}</flux:select.option>
                    @foreach($this->customers as $cust)
                        <flux:select.option value="{{ $cust->id }}">{{ $cust->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Status') }}</flux:label>
                <flux:select wire:model.live="filterStatus">
                    <flux:select.option value="all">{{ __('All Statuses') }}</flux:select.option>
                    <flux:select.option value="draft">{{ __('Draft') }}</flux:select.option>
                    <flux:select.option value="sent">{{ __('Sent') }}</flux:select.option>
                    <flux:select.option value="accepted">{{ __('Accepted') }}</flux:select.option>
                    <flux:select.option value="declined">{{ __('Declined') }}</flux:select.option>
                    <flux:select.option value="expired">{{ __('Expired') }}</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Quote Number') }}</flux:label>
                <flux:input wire:model.live.debounce.300ms="filterNumber" type="search" placeholder="{{ __('Search Quote #...') }}" />
            </flux:field>
        </div>

        <!-- Table Section -->
        <div class="bg-white dark:bg-zinc-900 border-y sm:border border-zinc-200 dark:border-zinc-700 sm:rounded-xl overflow-hidden shadow-sm">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Number') }}</flux:table.column>
                    <flux:table.column>{{ __('Customer') }}</flux:table.column>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Valid Until') }}</flux:table.column>
                    <flux:table.column>{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($quotes as $q)
                        <flux:table.row :key="$q->id">
                            <flux:table.cell class="font-bold text-zinc-900 dark:text-white">
                                {{ $q->number }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="block font-medium text-zinc-900 dark:text-white">{{ $q->customer->name ?? 'N/A' }}</span>
                                <span class="block text-xs text-zinc-500">{{ $q->customer->email ?? '' }}</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $q->date->format('d/m/Y') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $q->expiry_date->format('d/m/Y') }}
                            </flux:table.cell>
                            <flux:table.cell class="font-bold">
                                {{ Number::currency($q->total_amount, 'GBP') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @php
                                    $badgeColor = match($q->status) {
                                        'accepted' => 'green',
                                        'declined' => 'red',
                                        'sent' => 'blue',
                                        'expired' => 'yellow',
                                        default => 'zinc',
                                    };
                                @endphp
                                <flux:badge :color="$badgeColor" size="sm" class="uppercase">
                                    {{ __($q->status) }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:dropdown align="end">
                                    <flux:button variant="ghost" size="xs" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        @if(!$q->hasActiveInvoice())
                                            <flux:menu.item icon="pencil" wire:click="openEditForm({{ $q->id }})">{{ __('Edit') }}</flux:menu.item>
                                        @endif
                                        <flux:menu.item icon="arrow-down-tray" href="{{ route('quotes.pdf', ['id' => $q->id]) }}" target="_blank">{{ __('Download PDF') }}</flux:menu.item>
                                        
                                        @if($q->customer->email ?? null)
                                            <flux:menu.item icon="paper-airplane" wire:click="sendQuoteEmail({{ $q->id }})">{{ __('Send by Email') }}</flux:menu.item>
                                        @endif

                                        @if(!$q->hasActiveInvoice())
                                            @if($q->status !== 'accepted')
                                                <flux:menu.item icon="check" wire:click="changeStatus({{ $q->id }}, 'accepted')">{{ __('Mark as Accepted') }}</flux:menu.item>
                                            @endif
                                            @if($q->status !== 'declined')
                                                <flux:menu.item icon="x-mark" wire:click="changeStatus({{ $q->id }}, 'declined')">{{ __('Mark as Declined') }}</flux:menu.item>
                                            @endif
                                        @endif

                                        @if($q->status === 'accepted' && !$q->hasActiveInvoice())
                                            <flux:menu.separator />
                                            <flux:menu.item icon="document-duplicate" class="font-semibold text-green-600 dark:text-green-400" wire:click="convertToInvoice({{ $q->id }})">
                                                {{ __('Convert to Invoice') }}
                                            </flux:menu.item>
                                        @endif

                                        @if(!$q->hasActiveInvoice())
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $q->id }})">{{ __('Delete') }}</flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            @if(count($quotes) === 0)
                <div class="p-12 text-center text-zinc-500 dark:text-zinc-400">
                    <flux:icon.document class="w-12 h-12 mx-auto mb-4 opacity-20" />
                    <p>{{ __('No quotes found.') }}</p>
                </div>
            @endif
        </div>
    @endif

    @if($screen === 'form')
        <header class="flex items-center gap-3 px-4 sm:px-0">
            <flux:button wire:click="$set('screen', 'list')" variant="ghost" icon="chevron-left" size="sm" class="rounded-full" />
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $quoteId ? __('Edit Quote') : __('Add Quote') }}</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Configure your quote details and line items.') }}</p>
            </div>
        </header>

        <form wire:submit="save" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl p-6 shadow-sm space-y-6">
            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Customer') }}</flux:label>
                    <flux:select wire:model="customerId" required>
                        <flux:select.option value="">{{ __('Select Customer...') }}</flux:select.option>
                        @foreach($this->customers as $cust)
                            <flux:select.option value="{{ $cust->id }}">{{ $cust->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="customerId" />
                </flux:field>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>{{ __('Quote Date') }}</flux:label>
                        <flux:input type="date" wire:model="quoteDate" required />
                        <flux:error name="quoteDate" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Expiry Date') }}</flux:label>
                        <flux:input type="date" wire:model="expiryDate" required />
                        <flux:error name="expiryDate" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Message / Notes') }}</flux:label>
                    <flux:textarea wire:model="notes" placeholder="{{ __('Optional message to the customer...') }}" rows="2" />
                    <flux:error name="notes" />
                </flux:field>

                <div class="border-t border-zinc-200 dark:border-zinc-800 pt-4">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="md">{{ __('Line Items') }}</flux:heading>
                        <flux:button wire:click="addItem" type="button" size="sm" icon="plus" variant="ghost">
                            {{ __('Add Item') }}
                        </flux:button>
                    </div>

                    <div class="space-y-4">
                        @foreach($items as $idx => $item)
                            <div class="border border-zinc-200 dark:border-zinc-800 p-5 rounded-2xl relative space-y-4 bg-zinc-50/50 dark:bg-zinc-950/20 shadow-xs" :key="'item-'.$idx">
                                <div class="grid grid-cols-12 gap-4 items-end">
                                    <!-- Service Type Select -->
                                    <div class="col-span-12 {{ empty($item['service_type_id']) ? 'md:col-span-2' : 'md:col-span-4' }} transition-all duration-200">
                                        <flux:field>
                                            <flux:label>{{ __('Service Type') }}</flux:label>
                                            <flux:select wire:model.live="items.{{ $idx }}.service_type_id">
                                                <flux:select.option value="">{{ __('Custom Item / Service...') }}</flux:select.option>
                                                @foreach($this->serviceTypes as $st)
                                                    <flux:select.option value="{{ $st->id }}">{{ $st->name }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        </flux:field>
                                    </div>

                                    <!-- Service Name Input (only shown if Custom Item is selected) -->
                                    @if(empty($item['service_type_id']))
                                        <div class="col-span-12 md:col-span-2 transition-all duration-200">
                                            <flux:field>
                                                <flux:label>{{ __('Service') }}</flux:label>
                                                <flux:input wire:model="items.{{ $idx }}.description" required />
                                            </flux:field>
                                        </div>
                                    @endif

                                    <!-- Billing Type Select -->
                                    <div class="col-span-12 md:col-span-2">
                                        <flux:field>
                                            <flux:label>{{ __('Billing Type') }}</flux:label>
                                            <flux:select wire:model.live="items.{{ $idx }}.billing_type">
                                                <flux:select.option value="hourly">{{ __('Hourly') }}</flux:select.option>
                                                <flux:select.option value="unit">{{ __('Unit') }}</flux:select.option>
                                            </flux:select>
                                        </flux:field>
                                    </div>

                                    <!-- Hours / Quantity -->
                                    <div class="col-span-4 md:col-span-2">
                                        <flux:field>
                                            <flux:label>{{ ($item['billing_type'] ?? 'hourly') === 'hourly' ? __('Hours') : __('Quantity') }}</flux:label>
                                            @if(($item['billing_type'] ?? 'hourly') === 'hourly')
                                                <flux:input type="time" wire:model.live="items.{{ $idx }}.quantity" placeholder="Ex: 02:30" required wire:key="quote-qty-time-{{ $idx }}" />
                                            @else
                                                <flux:input type="text" wire:model.live="items.{{ $idx }}.quantity" placeholder="Ex: 10" required wire:key="quote-qty-text-{{ $idx }}" />
                                            @endif
                                        </flux:field>
                                    </div>

                                    <!-- Unit Price -->
                                    <div class="col-span-4 md:col-span-2">
                                        <flux:field>
                                            <flux:label>{{ ($item['billing_type'] ?? 'hourly') === 'hourly' ? __('Hourly Rate') : __('Unit Price') }}</flux:label>
                                            <flux:input type="number" step="0.01" wire:model.live="items.{{ $idx }}.unit_price" icon="banknotes" required />
                                        </flux:field>
                                    </div>

                                    <!-- Total Display -->
                                    <div class="col-span-4 md:col-span-2 text-right pr-2 pb-2">
                                        <span class="block text-xs text-zinc-500 uppercase font-semibold">{{ __('Total') }}</span>
                                        <span class="block text-base font-bold text-zinc-950 dark:text-white mt-1">
                                            {{ Number::currency($this->parseQuantity($item['quantity'] ?? 0) * ((float)($item['unit_price'] ?? 0)), 'GBP') }}
                                        </span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-3">
                                    <flux:field>
                                        <flux:label>{{ __('Item Details / Notes') }}</flux:label>
                                        <flux:textarea wire:model="items.{{ $idx }}.notes" placeholder="{{ __('Provide complete long details for this specific service item...') }}" rows="1" />
                                    </flux:field>
                                </div>

                                @if(count($items) > 1)
                                    <button type="button" wire:click="removeItem({{ $idx }})" class="absolute top-3 right-3 text-zinc-450 hover:text-red-500 dark:text-zinc-500 dark:hover:text-red-400 transition p-1 rounded-full hover:bg-zinc-200/50 dark:hover:bg-zinc-800/50">
                                        <flux:icon.x-mark class="w-4 h-4" />
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button wire:click="$set('screen', 'list')" variant="ghost">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    @endif
</div>
