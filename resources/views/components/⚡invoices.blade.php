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
    public array $emailLogs = [];
    public string $listTab = 'invoices';

    // Send confirmation state
    public bool $showSendConfirmationModal = false;
    public int $selectedInvoiceSentCount = 0;

    // Edit Invoice State
    public ?int $editingInvoiceId = null;

    // Edit Invoice Info State (for post-emission)
    public bool $showEditInfoModal = false;
    public $editInfoDueDate;
    public string $editInfoNotes = '';

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
    public string $filterAddressId = 'all';

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
    public string $manualBillingType = 'hourly';
    public array $manualUserIds = [];
    public $manualAddressId = null;
    public ?int $editingServiceInstanceId = null;

    public function mount(): void
    {
        if (auth()->user()->role !== 'management') {
            abort(403);
        }

        $this->invoiceDate = now()->format('Y-m-d');
        $this->dueDate = now()->addDays(14)->format('Y-m-d');
        $this->manualDate = now()->format('Y-m-d');

        // Check if status is passed as query string to filter invoices list
        $status = request()->query('status');
        if ($status && in_array($status, ['draft', 'sent', 'paid', 'cancelled'])) {
            $this->filterStatus = $status;
        }

        // Check if an invoice secure UUID is passed as query string to show detail screen immediately
        $uuid = request()->query('uuid');
        if ($uuid) {
            $invoice = auth()->user()->company->invoices()->where('uuid', $uuid)->first();
            if ($invoice) {
                $this->selectedInvoiceId = $invoice->id;
                $this->screen = 'detail';
            }
        }

        $this->refreshInvoices();
        $this->refreshEmailLogs();
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

    public function updatedListTab(): void
    {
        if ($this->listTab === 'email_logs') {
            $this->refreshEmailLogs();
        } else {
            $this->refreshInvoices();
        }
    }

    public function refreshEmailLogs(): void
    {
        $this->emailLogs = \App\Models\EmailLog::where('company_id', auth()->user()->company->id)
            ->with(['invoice'])
            ->latest()
            ->get()
            ->map(fn($log) => [
                'id' => $log->id,
                'invoice_number' => $log->invoice?->number ?? 'N/A',
                'recipient_email' => $log->recipient_email,
                'status' => $log->status,
                'error_message' => $log->error_message,
                'created_at' => $log->created_at->format('d/m/Y H:i'),
            ])
            ->toArray();
    }

    public function updatedFilterCustomer(): void { $this->refreshInvoices(); }
    public function updatedFilterStartDate(): void { $this->refreshInvoices(); }
    public function updatedFilterEndDate(): void { $this->refreshInvoices(); }
    public function updatedFilterNumber(): void { $this->refreshInvoices(); }
    public function updatedFilterStatus(): void { $this->refreshInvoices(); }
    public function updatedFilterAddressId(): void { $this->loadPendingServices(); }

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
        $this->editingInvoiceId = null;
        $this->filterAddressId = 'all';
        $this->screen = 'list'; // To trigger state cleanup
        $this->screen = 'select_customer';
    }

    public function selectCustomer(int $id): void
    {
        $this->selectedCustomerId = $id;
        $this->filterAddressId = 'all';
        $this->loadPendingServices();
        $this->notes = auth()->user()->company->default_invoice_message ?? '';
        $this->screen = 'select_services';
    }

    public function loadPendingServices(): void
    {
        $query = ServiceInstance::query()
            ->where('company_id', auth()->user()->company->id)
            ->where('status', 'completed')
            ->where('customer_id', $this->selectedCustomerId);

        if ($this->filterAddressId !== 'all') {
            $query->where('service_address_id', $this->filterAddressId);
        }

        if ($this->editingInvoiceId) {
            $query->where(function($q) {
                $q->whereDoesntHave('invoiceItem')
                  ->orWhereHas('invoiceItem', fn($sub) => $sub->where('invoice_id', $this->editingInvoiceId));
            });
        } else {
            $query->whereDoesntHave('invoiceItem');
        }

        $this->pendingServices = $query->orderBy('date', 'asc')
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'date' => $item->date->format('d/m/y'),
                'description' => $item->description,
                'hours' => $item->duration_hours,
                'rate' => $item->hourly_rate,
                'total' => $item->duration_hours * $item->hourly_rate,
                'billing_type' => $item->billing_type ?? 'hourly',
            ])
            ->toArray();

        // Only default selected checkboxes to everything if NOT editing an invoice!
        if (!$this->editingInvoiceId) {
            $this->selectedServiceIds = array_column($this->pendingServices, 'id');
        }
    }

    public function updatedManualAddressId($value): void
    {
        if ($value) {
            $address = auth()->user()->company->customers()
                ->find($this->selectedCustomerId)
                ?->addresses()
                ->find($value);

            if ($address) {
                $this->manualRate = $address->hourly_rate;
                $this->manualBillingType = $address->billing_type ?? 'hourly';
                if ($this->manualBillingType === 'hourly') {
                    $this->manualHours = \App\Brain\Helpers\TimeHelper::decimalToColon($address->duration_hours);
                } else {
                    $this->manualHours = (float) $address->duration_hours;
                }
            }
        }
    }

    public function updatedManualBillingType($value): void
    {
        if ($value === 'hourly') {
            $this->manualHours = \App\Brain\Helpers\TimeHelper::decimalToColon($this->manualHours);
        } else {
            $this->manualHours = \App\Brain\Helpers\TimeHelper::humanToDecimal($this->manualHours);
        }
    }

    public function openManualModal(): void
    {
        $this->manualServiceTypeId = null;
        $this->manualDescription = '';
        $this->manualHours = 1;
        $this->manualRate = 0;
        $this->manualDate = now()->format('Y-m-d');
        $this->manualBillingType = 'hourly';
        $this->manualUserIds = [];
        $this->manualAddressId = null;
        $this->editingServiceInstanceId = null;
        // Try to get the rate from the customer's first address if possible
        $customer = auth()->user()->company->customers()->with('addresses')->find($this->selectedCustomerId);
        if ($customer && $customer->addresses->isNotEmpty()) {
            $firstAddress = $customer->addresses->first();
            $this->manualRate = $firstAddress->hourly_rate;
            $this->manualAddressId = $firstAddress->id;
            $this->manualBillingType = $firstAddress->billing_type ?? 'hourly';
            if ($this->manualBillingType === 'hourly') {
                $this->manualHours = \App\Brain\Helpers\TimeHelper::decimalToColon($firstAddress->duration_hours);
            } else {
                $this->manualHours = (float) $firstAddress->duration_hours;
            }
        }

        $this->showManualModal = true;
    }

    public function openEditServiceModal(int $instanceId): void
    {
        $instance = ServiceInstance::with('users')->findOrFail($instanceId);

        $this->editingServiceInstanceId = $instance->id;
        $this->manualServiceTypeId = $instance->service_type_id;
        $this->manualDescription = $instance->description;
        $this->manualDate = $instance->date->format('Y-m-d');
        $this->manualBillingType = $instance->billing_type ?? 'hourly';
        if ($this->manualBillingType === 'hourly') {
            $this->manualHours = \App\Brain\Helpers\TimeHelper::decimalToColon($instance->duration_hours);
        } else {
            $this->manualHours = (float) $instance->duration_hours;
        }
        $this->manualRate = (float) $instance->hourly_rate;
        $this->manualAddressId = $instance->service_address_id;
        $this->manualUserIds = $instance->users->pluck('id')->toArray();

        $this->showManualModal = true;
    }

    public function deleteServiceInstance(int $id): void
    {
        $instance = ServiceInstance::where('company_id', auth()->user()->company->id)->findOrFail($id);
        $instance->delete();

        // Remove from selected list if it was selected
        $this->selectedServiceIds = array_diff($this->selectedServiceIds, [$id]);

        $this->loadPendingServices();
        Flux::toast(variant: 'success', text: __('Service deleted successfully.'));
    }

    public function saveManualService(): void
    {
        // Parse the manualHours format (handles formats like "2:30" or "2.5")
        if ($this->manualBillingType === 'hourly') {
            $this->manualHours = \App\Brain\Helpers\TimeHelper::humanToDecimal($this->manualHours);
        } else {
            $this->manualHours = (float) $this->manualHours;
        }

        $this->validate([
            'manualDate' => 'required|date',
            'manualHours' => 'required|numeric|min:0',
            'manualRate' => 'required|numeric|min:0',
            'manualAddressId' => 'required|exists:service_addresses,id',
        ]);

        $description = trim($this->manualDescription ?? '');

        if (!$this->manualServiceTypeId && $description === '') {
            $this->addError('manualServiceTypeId', __('Please select a service type or enter a custom description.'));
            return;
        }

        if ($description === '' && $this->manualServiceTypeId) {
            $serviceType = auth()->user()->company->serviceTypes()->find($this->manualServiceTypeId);
            if ($serviceType) {
                $description = $serviceType->name;
            }
        }

        if ($this->editingServiceInstanceId) {
            // EDIT mode
            $instance = ServiceInstance::findOrFail($this->editingServiceInstanceId);
            $instance->update([
                'service_address_id' => $this->manualAddressId,
                'service_type_id' => $this->manualServiceTypeId ?: null,
                'description' => $description,
                'date' => $this->manualDate,
                'duration_hours' => $this->manualHours,
                'hourly_rate' => $this->manualRate,
                'billing_type' => $this->manualBillingType,
            ]);

            Flux::toast(variant: 'success', text: __('Service updated successfully.'));
        } else {
            // CREATE mode
            $instance = ServiceInstance::create([
                'company_id' => auth()->user()->company->id,
                'customer_id' => $this->selectedCustomerId,
                'service_address_id' => $this->manualAddressId,
                'service_type_id' => $this->manualServiceTypeId ?: null,
                'description' => $description,
                'date' => $this->manualDate,
                'time' => '12:00',
                'duration_hours' => $this->manualHours,
                'hourly_rate' => $this->manualRate,
                'status' => 'completed',
                'billing_type' => $this->manualBillingType,
            ]);

            Flux::toast(variant: 'success', text: __('Manual service added.'));
        }

        if (!empty($this->manualUserIds)) {
            $instance->users()->sync($this->manualUserIds);
        } else {
            $instance->users()->detach();
        }

        $this->showManualModal = false;
        $this->editingServiceInstanceId = null;
        $this->loadPendingServices();
    }

    public function generateInvoice(): void
    {
        if (empty($this->selectedServiceIds)) {
            Flux::toast(variant: 'danger', text: __('Select at least one service.'));
            return;
        }

        if ($this->editingInvoiceId) {
            $invoice = auth()->user()->company->invoices()->findOrFail($this->editingInvoiceId);
            
            $invoice->update([
                'date' => $this->invoiceDate,
                'due_date' => $this->dueDate,
                'notes' => $this->notes ?: null,
            ]);

            // Clear old items
            $invoice->items()->delete();

            // Create new items using CreateInvoiceItemsAction
            \App\Brain\Invoices\Actions\CreateInvoiceItemsAction::run([
                'invoiceId' => $invoice->id,
                'selectedServiceIds' => $this->selectedServiceIds,
            ]);

            $this->selectedInvoiceId = $invoice->id;
            $this->editingInvoiceId = null;
            $this->screen = 'detail';
            $this->refreshInvoices();
            Flux::toast(variant: 'success', text: __('Invoice updated successfully.'));
            return;
        }

        // Run the GenerateInvoiceWorkflow!
        $payload = \App\Brain\Invoices\Workflows\GenerateInvoiceWorkflow::run([
            'companyId' => auth()->user()->company->id,
            'customerId' => $this->selectedCustomerId,
            'invoiceDate' => $this->invoiceDate,
            'dueDate' => $this->dueDate,
            'notes' => $this->notes,
            'selectedServiceIds' => $this->selectedServiceIds,
        ]);

        $this->selectedInvoiceId = $payload->invoiceId;
        $this->screen = 'detail';
        $this->refreshInvoices();
        Flux::toast(variant: 'success', text: __('Invoice created successfully.'));
    }

    public function editInvoice(): void
    {
        $invoice = auth()->user()->company->invoices()->with('items')->findOrFail($this->selectedInvoiceId);

        $this->editingInvoiceId = $invoice->id;
        $this->selectedCustomerId = $invoice->customer_id;
        $this->notes = $invoice->notes ?? '';
        $this->invoiceDate = $invoice->date->format('Y-m-d');
        $this->dueDate = $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '';

        $this->filterAddressId = 'all';
        $this->loadPendingServices();

        // Check the boxes for the services already in this invoice
        $this->selectedServiceIds = $invoice->items()
            ->whereNotNull('service_instance_id')
            ->pluck('service_instance_id')
            ->toArray();

        $this->screen = 'select_services';
    }

    public function openEditInfoModal(): void
    {
        $invoice = auth()->user()->company->invoices()->findOrFail($this->selectedInvoiceId);
        $this->editInfoDueDate = $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '';
        $this->editInfoNotes = $invoice->notes ?? '';
        $this->showEditInfoModal = true;
    }

    public function saveInvoiceInfo(): void
    {
        $this->validate([
            'editInfoDueDate' => 'nullable|date',
            'editInfoNotes' => 'nullable|string',
        ]);

        $invoice = auth()->user()->company->invoices()->findOrFail($this->selectedInvoiceId);
        $invoice->update([
            'due_date' => $this->editInfoDueDate ?: null,
            'notes' => $this->editInfoNotes ?: null,
        ]);

        $this->showEditInfoModal = false;
        $this->refreshInvoices();
        Flux::toast(variant: 'success', text: __('Invoice details updated successfully.'));
    }

    public function backFromSelectServices(): void
    {
        if ($this->editingInvoiceId) {
            $this->screen = 'detail';
            $this->selectedInvoiceId = $this->editingInvoiceId;
            $this->editingInvoiceId = null;
        } else {
            $this->screen = 'select_customer';
        }
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

    public function checkBeforeSendEmail(): void
    {
        $invoice = auth()->user()->company->invoices()->findOrFail($this->selectedInvoiceId);

        $this->selectedInvoiceSentCount = \App\Models\EmailLog::where('invoice_id', $invoice->id)
            ->where('status', 'success')
            ->count();

        if ($this->selectedInvoiceSentCount > 0) {
            $this->showSendConfirmationModal = true;
        } else {
            $this->sendEmail();
        }
    }

    public function confirmSendEmail(): void
    {
        $this->showSendConfirmationModal = false;
        $this->sendEmail();
    }

    public function sendEmail(): void
    {
        $invoice = auth()->user()->company->invoices()->with(['customer', 'company'])->findOrFail($this->selectedInvoiceId);

        if (!$invoice->customer->email) {
            Flux::toast(variant: 'danger', text: __('Customer has no email address.'));
            return;
        }

        try {
            // Run the SendInvoiceEmailWorkflow!
            \App\Brain\Invoices\Workflows\SendInvoiceEmailWorkflow::run([
                'invoiceId' => $invoice->id,
            ]);

            $this->refreshInvoices();
            Flux::toast(variant: 'success', text: __('Invoice sent by email successfully.'));
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', text: __('Failed to send email. Please check your mail settings.'));
        }

        $this->refreshEmailLogs();
    }

    public function resendEmail(int $logId): void
    {
        $log = \App\Models\EmailLog::findOrFail($logId);
        $invoice = auth()->user()->company->invoices()->with(['customer', 'company'])->findOrFail($log->invoice_id);

        if (!$log->recipient_email) {
            Flux::toast(variant: 'danger', text: __('No recipient email address.'));
            return;
        }

        try {
            // Run the SendInvoiceEmailWorkflow overriding the recipient email!
            \App\Brain\Invoices\Workflows\SendInvoiceEmailWorkflow::run([
                'invoiceId' => $invoice->id,
                'recipientEmail' => $log->recipient_email,
            ]);

            $this->refreshInvoices();
            Flux::toast(variant: 'success', text: __('Invoice resent by email successfully.'));
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', text: __('Failed to resend email. Please check your mail settings.'));
        }

        $this->refreshEmailLogs();
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
    public function collaborators()
    {
        return auth()->user()->company->users()->orderBy('name')->get();
    }

    #[Computed]
    public function pendingReceiptsAmount(): float
    {
        return (float) auth()->user()->company->invoices()
            ->where('status', 'sent')
            ->sum('total_amount');
    }

    #[Computed]
    public function manualAddresses()
    {
        if (!$this->selectedCustomerId) {
            return collect();
        }
        return auth()->user()->company->customers()
            ->find($this->selectedCustomerId)
            ?->addresses()
            ->where('is_active', true)
            ->get() ?? collect();
    }

    #[Computed]
    public function customerAddresses()
    {
        if (!$this->selectedCustomerId) {
            return collect();
        }
        return auth()->user()->company->customers()
            ->find($this->selectedCustomerId)
            ?->addresses()
            ->get() ?? collect();
    }

    #[Computed]
    public function serviceTypes()
    {
        return auth()->user()->company->serviceTypes()->orderBy('name')->get();
    }

    public function removeCollaborator(int $invoiceItemId, int $userId): void
    {
        $item = InvoiceItem::with('invoice.company')->findOrFail($invoiceItemId);

        if ($item->invoice->company_id !== auth()->user()->company->id) {
            abort(403);
        }

        $instance = $item->serviceInstance;

        if ($instance) {
            $instance->users()->detach($userId);
        }

        unset($this->selectedInvoice);
        Flux::toast(variant: 'success', text: __('Collaborator removed from service.'));
    }

    public function addCollaborator(int $invoiceItemId, int $userId): void
    {
        $item = InvoiceItem::with('invoice.company')->findOrFail($invoiceItemId);

        if ($item->invoice->company_id !== auth()->user()->company->id) {
            abort(403);
        }

        $instance = $item->serviceInstance;

        if (!$instance) {
            // Create a new ServiceInstance to associate with this InvoiceItem!
            $instance = ServiceInstance::create([
                'company_id' => $item->invoice->company_id,
                'customer_id' => $item->invoice->customer_id,
                'description' => $item->description,
                'date' => $item->invoice->date ?? now(),
                'time' => '12:00:00',
                'duration_hours' => $item->quantity,
                'hourly_rate' => $item->unit_price,
                'status' => 'completed',
                'billing_type' => $item->billing_type ?? 'hourly',
            ]);

            // Link the invoice item to this new service instance
            $item->update(['service_instance_id' => $instance->id]);
        }

        $instance->users()->attach($userId);

        unset($this->selectedInvoice);
        Flux::toast(variant: 'success', text: __('Collaborator added to service.'));
    }

    #[Computed]
    public function selectedInvoice()
    {
        return $this->selectedInvoiceId ? auth()->user()->company->invoices()->with(['customer', 'items.serviceInstance.users', 'quote'])->find($this->selectedInvoiceId) : null;
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

        <!-- Pending Receipt Summary Banner -->
        @if ($this->pendingReceiptsAmount > 0)
            <div class="mx-4 sm:mx-0 p-4 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/60 rounded-2xl flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-3">
                    <span class="p-2 rounded-xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400">
                        <flux:icon name="clock" variant="outline" class="w-5 h-5" />
                    </span>
                    <div>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium uppercase tracking-wider block">{{ __('Total Pending Receipts') }}</span>
                        <span class="text-lg font-bold text-zinc-900 dark:text-white block mt-0.5">{{ Number::currency($this->pendingReceiptsAmount, 'GBP') }}</span>
                    </div>
                </div>
                <flux:button wire:click="$set('filterStatus', 'sent')" variant="ghost" size="sm" class="text-amber-700 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-900/30">
                    {{ __('Filter Unpaid') }}
                </flux:button>
            </div>
        @endif

        <!-- Tabs (Invoices vs Email Logs) -->
        <div class="flex w-full rounded-lg bg-zinc-100 p-1 dark:bg-zinc-900/80 sm:w-auto self-start">
            <button type="button" wire:click="$set('listTab', 'invoices')" class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-all sm:flex-initial {{ $listTab === 'invoices' ? 'bg-white text-zinc-900 shadow-sm ring-1 ring-zinc-900/5 dark:bg-zinc-800 dark:text-white dark:ring-white/10' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
                {{ __('Invoices') }}
            </button>
            <button type="button" wire:click="$set('listTab', 'email_logs')" class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-all sm:flex-initial {{ $listTab === 'email_logs' ? 'bg-white text-zinc-900 shadow-sm ring-1 ring-zinc-900/5 dark:bg-zinc-800 dark:text-white dark:ring-white/10' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
                {{ __('Email Logs') }}
            </button>
        </div>

        @if ($listTab === 'invoices')
            <!-- Filters panel -->
            <div class="grid grid-cols-1 sm:grid-cols-5 gap-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800">
            <flux:field>
                <flux:label>{{ __('Invoice Number') }}</flux:label>
                <flux:input wire:model.live.debounce.300ms="filterNumber" placeholder="{{ __('0001...') }}" />
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
        @else
            <!-- Email Logs table -->
            <div class="bg-white dark:bg-zinc-900 border-y sm:border border-zinc-200 dark:border-zinc-700 sm:rounded-xl overflow-hidden shadow-sm">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Invoice Number') }}</flux:table.column>
                        <flux:table.column>{{ __('Recipient') }}</flux:table.column>
                        <flux:table.column>{{ __('Date & Time') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Details / Errors') }}</flux:table.column>
                        <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse($emailLogs as $log)
                            <flux:table.row :key="'log-'.$log['id']">
                                <flux:table.cell class="font-semibold text-zinc-900 dark:text-white">
                                    {{ $log['invoice_number'] }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span class="font-medium">{{ $log['recipient_email'] }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="text-zinc-500">
                                    {{ $log['created_at'] }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($log['status'] === 'success')
                                        <flux:badge size="sm" color="emerald" inset="top">{{ __('Success') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="red" inset="top">{{ __('Failed') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="max-w-xs truncate text-xs text-zinc-500" title="{{ $log['error_message'] }}">
                                    {{ $log['error_message'] ?: '-' }}
                                </flux:table.cell>
                                <flux:table.cell class="text-right">
                                    <flux:button wire:click="resendEmail({{ $log['id'] }})" variant="outline" size="xs" icon="paper-airplane">
                                        {{ __('Resend') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6" class="text-center py-8 text-zinc-400 text-sm">
                                    {{ __('No email logs recorded yet.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
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
                <flux:button wire:click="backFromSelectServices" variant="ghost" icon="chevron-left" size="sm" class="rounded-full" />
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ Customer::find($selectedCustomerId)->name }}</h1>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">{{ __('Step 2: Select Services') }}</p>
                </div>
            </div>
            <flux:button wire:click="openManualModal" variant="outline" size="sm" icon="plus" class="rounded-full">{{ __('Add Manual') }}</flux:button>
        </header>

        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row gap-4 items-end justify-between px-4 sm:px-0">
                <flux:field class="w-full sm:w-72">
                    <flux:label>{{ __('Filter by Location') }}</flux:label>
                    <flux:select wire:model.live="filterAddressId" placeholder="{{ __('All Locations') }}">
                        <flux:select.option value="all">{{ __('All Locations') }}</flux:select.option>
                        @foreach($this->customerAddresses as $addr)
                            <flux:select.option value="{{ $addr->id }}">{{ $addr->label }} ({{ $addr->address }})</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

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
                            <th class="px-4 py-3 w-20"></th>
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
                                    <p class="text-xs text-zinc-500">
                                        {{ $service['date'] }} • 
                                        @if(($service['billing_type'] ?? 'hourly') === 'hourly')
                                            {{ \App\Brain\Helpers\TimeHelper::decimalToColon($service['hours']) }}h
                                        @else
                                            {{ (float) $service['hours'] }} {{ (float) $service['hours'] === 1.0 ? __('unit') : __('units') }}
                                        @endif
                                    </p>
                                </td>
                                <td class="px-4 py-4 text-right font-semibold text-zinc-900 dark:text-white">
                                    {{ Number::currency($service['total'], 'GBP') }}
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <flux:button wire:click="openEditServiceModal({{ $service['id'] }})" variant="ghost" size="xs" icon="pencil" title="{{ __('Edit') }}" />
                                        <flux:button wire:click="deleteServiceInstance({{ $service['id'] }})" variant="ghost" size="xs" icon="trash" variant="danger" title="{{ __('Delete') }}" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-12 text-center text-zinc-400 italic">
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
                        <flux:button wire:click="editInvoice" variant="outline" icon="pencil">{{ __('Edit Invoice') }}</flux:button>
                        <flux:button wire:click="issueInvoice" variant="primary" icon="check">{{ __('Issue Invoice') }}</flux:button>
                    @else
                        <flux:button wire:click="openEditInfoModal" variant="outline" icon="pencil">{{ __('Edit Due Date & Notes') }}</flux:button>
                        <flux:button wire:click="checkBeforeSendEmail" variant="outline" icon="paper-airplane">{{ __('Send by Email') }}</flux:button>
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

            @if($inv->quote_id && $inv->quote)
                <div class="bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 p-4 rounded-2xl flex items-center gap-3">
                    <flux:icon.document-text class="w-5 h-5 text-blue-500" />
                    <div>
                        <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Generated from Quote') }}</span>
                        <span class="block text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                            {{ __('This invoice was generated from Quote :number on :date.', ['number' => $inv->quote->number, 'date' => $inv->quote->date->format('d/m/Y')]) }}
                        </span>
                    </div>
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
                                        <th class="px-3 py-2 text-right">{{ __('Hours/Qty') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('Rate') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @foreach($inv->items as $item)
                                        <tr>
                                            <td class="px-3 py-3 font-semibold dark:text-zinc-200">
                                                {{ $item->description }}
                                                @if($item->notes)
                                                    <div class="text-[10px] text-zinc-500 font-normal mt-1 italic whitespace-pre-wrap">{{ $item->notes }}</div>
                                                @endif

                                                @if(($item->billing_type ?? 'hourly') === 'hourly')
                                                    <div class="mt-2 flex flex-wrap gap-1.5 items-center">
                                                        <span class="text-[10px] text-zinc-400 dark:text-zinc-500 mr-0.5 font-normal">{{ __('Collaborators:') }}</span>
                                                        @if($item->serviceInstance)
                                                            @foreach($item->serviceInstance->users as $user)
                                                                <span class="inline-flex items-center gap-1 bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 text-[10px] px-2 py-0.5 rounded-full font-medium">
                                                                    {{ $user->name }}
                                                                    <button type="button" 
                                                                            wire:click="removeCollaborator({{ $item->id }}, {{ $user->id }})" 
                                                                            class="hover:text-red-600 focus:outline-none focus:text-red-600 ml-0.5 cursor-pointer"
                                                                            title="{{ __('Remove collaborator') }}">
                                                                        <flux:icon.x-mark class="w-3 h-3" />
                                                                    </button>
                                                                </span>
                                                            @endforeach
                                                        @endif

                                                        <!-- Add Collaborator Dropdown -->
                                                        <flux:dropdown align="start" class="inline-block">
                                                            <button type="button" class="inline-flex items-center gap-1 text-[10px] text-zinc-500 hover:text-zinc-900 dark:hover:text-white border border-dashed border-zinc-300 dark:border-zinc-700 rounded-full px-2 py-0.5 font-medium transition focus:outline-none cursor-pointer">
                                                                <flux:icon.plus class="w-2.5 h-2.5" />
                                                                <span>{{ __('Add') }}</span>
                                                            </button>
                                                            <flux:menu>
                                                                @php
                                                                    $assignedIds = $item->serviceInstance ? $item->serviceInstance->users->pluck('id')->toArray() : [];
                                                                    $availableCollabs = $this->collaborators->whereNotIn('id', $assignedIds);
                                                                @endphp
                                                                @forelse($availableCollabs as $collab)
                                                                    <flux:menu.item wire:click="addCollaborator({{ $item->id }}, {{ $collab->id }})">
                                                                        {{ $collab->name }}
                                                                    </flux:menu.item>
                                                                @empty
                                                                    <div class="px-3 py-1.5 text-xs text-zinc-400 italic">
                                                                        {{ __('No other collaborators') }}
                                                                    </div>
                                                                @endforelse
                                                            </flux:menu>
                                                        </flux:dropdown>
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 text-right text-zinc-600 dark:text-zinc-400">
                                                @if(($item->billing_type ?? 'hourly') === 'hourly')
                                                    {{ \App\Brain\Helpers\TimeHelper::decimalToColon($item->quantity) }}h
                                                @else
                                                    {{ number_format($item->quantity, 2) }}
                                                @endif
                                            </td>
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

                        <flux:separator />

                        <!-- Specific Invoice Email Logs -->
                        <div class="space-y-3">
                            <h4 class="text-xs font-bold text-zinc-900 dark:text-white uppercase tracking-wider">{{ __('Email History') }}</h4>

                            @php
                                $specificLogs = collect($this->emailLogs)->where('invoice_number', $inv->number);
                            @endphp

                            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/50">
                                @forelse($specificLogs as $log)
                                    <div class="py-2.5 first:pt-0 last:pb-0 flex items-center justify-between text-xs gap-3">
                                        <div class="min-w-0 flex-1">
                                            <span class="block font-medium text-zinc-800 dark:text-zinc-200 truncate" title="{{ $log['recipient_email'] }}">
                                                {{ $log['recipient_email'] }}
                                            </span>
                                            <span class="block text-[10px] text-zinc-400 mt-0.5">
                                                {{ $log['created_at'] }}
                                            </span>
                                            @if($log['error_message'])
                                                <span class="block text-[9px] text-red-500 mt-0.5 max-w-[150px] truncate" title="{{ $log['error_message'] }}">
                                                    {{ $log['error_message'] }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex-shrink-0">
                                            @if ($log['status'] === 'success')
                                                <flux:badge size="sm" color="emerald" class="text-[10px]">{{ __('Success') }}</flux:badge>
                                            @else
                                                <flux:badge size="sm" color="red" class="text-[10px]">{{ __('Failed') }}</flux:badge>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-[11px] text-zinc-400 italic py-1">
                                        {{ __('No email has been sent for this invoice yet.') }}
                                    </p>
                                @endforelse
                            </div>
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
                <flux:heading size="lg">{{ $editingServiceInstanceId ? __('Edit Service') : __('Add Manual Service') }}</flux:heading>
                <flux:subheading>{{ $editingServiceInstanceId ? __('Modify the service details and assigned team.') : __('One-off work not linked to a schedule.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Service Type') }}</flux:label>
                    <flux:select wire:model="manualServiceTypeId" placeholder="{{ __('Select a service type...') }}">
                        <flux:select.option value="">{{ __('Select a service type...') }}</flux:select.option>
                        @foreach($this->serviceTypes as $type)
                            <flux:select.option value="{{ $type->id }}">{{ $type->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="manualServiceTypeId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Custom Description (Optional)') }}</flux:label>
                    <flux:input wire:model="manualDescription" placeholder="{{ __('Extra Cleaning, Repairs...') }}" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Service Location') }}</flux:label>
                    <flux:select wire:model.live="manualAddressId" placeholder="{{ __('Select a location...') }}">
                        @foreach($this->manualAddresses as $addr)
                            <flux:select.option value="{{ $addr->id }}">{{ $addr->label }} ({{ $addr->address }})</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="manualAddressId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Date') }}</flux:label>
                    <flux:input type="date" wire:model="manualDate" required />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Assigned Team') }}</flux:label>
                    <div class="mt-2 space-y-2 max-h-36 overflow-y-auto border border-zinc-200 dark:border-zinc-800 rounded-lg p-3 bg-zinc-50/50 dark:bg-zinc-950/20">
                        @foreach($this->collaborators as $collab)
                            <flux:checkbox wire:model="manualUserIds" value="{{ $collab->id }}" label="{{ $collab->name }}" />
                        @endforeach
                    </div>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Billing Type') }}</flux:label>
                    <flux:select wire:model.live="manualBillingType" required>
                        <flux:select.option value="hourly">{{ __('Hourly') }}</flux:select.option>
                        <flux:select.option value="unit">{{ __('Unit') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>{{ $manualBillingType === 'hourly' ? __('Hours') : __('Quantity') }}</flux:label>
                        @if($manualBillingType === 'hourly')
                            <flux:input type="text" wire:model="manualHours" placeholder="Ex: 02:30" required wire:key="manual-hours-time" />
                        @else
                            <flux:input type="text" wire:model="manualHours" placeholder="Ex: 1, 2, 5" required wire:key="manual-hours-text" />
                        @endif
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ $manualBillingType === 'hourly' ? __('Rate') : __('Unit Price') }}</flux:label>
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

    <!-- Send Email Confirmation Modal -->
    <flux:modal wire:model="showSendConfirmationModal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Resend Invoice Email?') }}</flux:heading>
                <flux:subheading class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('This invoice email has already been sent :count times to the customer.', ['count' => $selectedInvoiceSentCount]) }}
                    <span class="block mt-2 font-medium text-zinc-700 dark:text-zinc-300">{{ __('Are you sure you want to send it again?') }}</span>
                </flux:subheading>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button wire:click="$set('showSendConfirmationModal', false)" variant="ghost">{{ __('Cancel') }}</flux:button>
                <flux:button wire:click="confirmSendEmail" variant="primary">{{ __('Send Again') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Edit Invoice Info Modal -->
    <flux:modal wire:model="showEditInfoModal" class="md:w-[480px]">
        <form wire:submit="saveInvoiceInfo" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit Due Date & Notes') }}</flux:heading>
                <flux:subheading>{{ __('Update the due date and notes for this invoice.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Due Date') }}</flux:label>
                    <flux:input type="date" wire:model="editInfoDueDate" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Message / Notes') }}</flux:label>
                    <flux:textarea wire:model="editInfoNotes" rows="4" placeholder="{{ __('Add a message that will appear on this invoice...') }}" />
                </flux:field>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button wire:click="$set('showEditInfoModal', false)" variant="ghost">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save Changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
