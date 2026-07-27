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
    public array $quotes = [];
    public bool $showModal = false;

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

        $this->quotes = $query->latest()->get()->toArray();
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

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->addItem(); // Start with at least one item row
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
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
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
            ];
        }

        $this->showModal = true;
    }

    public function addItem(): void
    {
        $this->items[] = [
            'service_type_id' => '',
            'description' => '',
            'quantity' => 1,
            'unit_price' => 0.00,
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
            }
        }
    }

    public function save(): void
    {
        $this->validate([
            'customerId' => 'required',
            'quoteDate' => 'required|date',
            'expiryDate' => 'required|date|after_or_equal:quoteDate',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
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

        $this->showModal = false;
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
        $quote->update(['status' => $status]);
        Flux::toast(variant: 'success', text: __('Quote status updated to :status.', ['status' => __($status)]));
        $this->refreshQuotes();
    }

    public function convertToInvoice(int $id): void
    {
        $action = \App\Brain\Quotes\Actions\ConvertQuoteToInvoiceAction::run([
            'quoteId' => $id,
        ]);

        Flux::toast(variant: 'success', text: __('Quote successfully converted to Invoice draft!'));
        $this->refreshQuotes();
    }

    public function delete(int $id): void
    {
        auth()->user()->company->quotes()->findOrFail($id)->delete();
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
};

?>

<div class="mx-auto max-w-5xl space-y-6 pb-24">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between sm:px-0">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('Quotes & Estimates') }}</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Create and manage estimates for your customers.') }}</p>
        </div>
        <flux:button wire:click="openCreateModal" variant="primary" icon="plus" class="rounded-full">
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
                    <flux:table.row :key="$q['id']">
                        <flux:table.cell class="font-bold text-zinc-900 dark:text-white">
                            {{ $q['number'] }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="block font-medium text-zinc-900 dark:text-white">{{ $q['customer']['name'] ?? 'N/A' }}</span>
                            <span class="block text-xs text-zinc-500">{{ $q['customer']['email'] ?? '' }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ \Carbon\Carbon::parse($q['date'])->format('d/m/Y') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ \Carbon\Carbon::parse($q['expiry_date'])->format('d/m/Y') }}
                        </flux:table.cell>
                        <flux:table.cell class="font-bold">
                            {{ Number::currency($q['total_amount'], 'GBP') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $badgeColor = match($q['status']) {
                                    'accepted' => 'green',
                                    'declined' => 'red',
                                    'sent' => 'blue',
                                    'expired' => 'yellow',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:badge :color="$badgeColor" size="sm" class="uppercase">
                                {{ __($q['status']) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown align="end">
                                <flux:button variant="ghost" size="xs" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil" wire:click="openEditModal({{ $q['id'] }})">{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.item icon="arrow-down-tray" href="{{ route('quotes.pdf', ['id' => $q['id']]) }}" target="_blank">{{ __('Download PDF') }}</flux:menu.item>
                                    
                                    @if($q['customer']['email'] ?? null)
                                        <flux:menu.item icon="paper-airplane" wire:click="sendQuoteEmail({{ $q['id'] }})">{{ __('Send by Email') }}</flux:menu.item>
                                    @endif

                                    @if($q['status'] !== 'accepted')
                                        <flux:menu.item icon="check" wire:click="changeStatus({{ $q['id'] }}, 'accepted')">{{ __('Mark as Accepted') }}</flux:menu.item>
                                    @endif
                                    @if($q['status'] !== 'declined')
                                        <flux:menu.item icon="x-mark" wire:click="changeStatus({{ $q['id'] }}, 'declined')">{{ __('Mark as Declined') }}</flux:menu.item>
                                    @endif

                                    @if($q['status'] === 'accepted')
                                        <flux:menu.separator />
                                        <flux:menu.item icon="document-duplicate" class="font-semibold text-green-600 dark:text-green-400" wire:click="convertToInvoice({{ $q['id'] }})">
                                            {{ __('Convert to Invoice') }}
                                        </flux:menu.item>
                                    @endif

                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $q['id'] }})">{{ __('Delete') }}</flux:menu.item>
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

    <!-- Modal Form -->
    <flux:modal wire:model="showModal" class="md:w-[600px]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $quoteId ? __('Edit Quote') : __('Add Quote') }}</flux:heading>
                <flux:subheading>{{ __('Configure your quote details and line items.') }}</flux:subheading>
            </div>

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

                    <div class="space-y-4 max-h-72 overflow-y-auto pr-1">
                        @foreach($items as $idx => $item)
                            <div class="grid grid-cols-12 gap-3 items-end border border-zinc-100 dark:border-zinc-800 p-3 rounded-xl relative" :key="'item-'.$idx">
                                <div class="col-span-12 sm:col-span-5">
                                    <flux:field>
                                        <flux:label>{{ __('Service Type / Name') }}</flux:label>
                                        <flux:select wire:model="items.{{ $idx }}.service_type_id">
                                            <flux:select.option value="">{{ __('Custom Item / Service...') }}</flux:select.option>
                                            @foreach($this->serviceTypes as $st)
                                                <flux:select.option value="{{ $st->id }}">{{ $st->name }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </flux:field>
                                </div>

                                <div class="col-span-12 sm:col-span-7">
                                    <flux:field>
                                        <flux:label>{{ __('Description') }}</flux:label>
                                        <flux:input wire:model="items.{{ $idx }}.description" required />
                                    </flux:field>
                                </div>

                                <div class="col-span-4 sm:col-span-3">
                                    <flux:field>
                                        <flux:label>{{ __('Hours/Qty') }}</flux:label>
                                        <flux:input type="number" step="0.01" wire:model.live="items.{{ $idx }}.quantity" required />
                                    </flux:field>
                                </div>

                                <div class="col-span-5 sm:col-span-4">
                                    <flux:field>
                                        <flux:label>{{ __('Unit Price') }}</flux:label>
                                        <flux:input type="number" step="0.01" wire:model.live="items.{{ $idx }}.unit_price" icon="banknotes" required />
                                    </flux:field>
                                </div>

                                <div class="col-span-3 sm:col-span-4 text-right pr-2">
                                    <span class="block text-xs text-zinc-500 uppercase">{{ __('Total') }}</span>
                                    <span class="block font-bold text-zinc-950 dark:text-white mt-1">
                                        {{ Number::currency(((float)($item['quantity'] ?? 0)) * ((float)($item['unit_price'] ?? 0)), 'GBP') }}
                                    </span>
                                </div>

                                @if(count($items) > 1)
                                    <button type="button" wire:click="removeItem({{ $idx }})" class="absolute top-2 right-2 text-zinc-400 hover:text-red-500 transition">
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
                <flux:button wire:click="$set('showModal', false)" variant="ghost">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
