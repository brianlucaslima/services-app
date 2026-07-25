<x-settings.layout :heading="__('Company Profile')" :subheading="__('Update your business details and payment information')">
    <form wire:submit="save" class="mt-6 space-y-6">
        <div class="flex items-center gap-4">
            @if ($company->logo)
                <img src="{{ asset('storage/' . $company->logo) }}" class="w-16 h-14 rounded-xl object-contain border dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800" />
            @endif
            <flux:field class="flex-1">
                <flux:label>{{ __('Company Logo') }}</flux:label>
                <flux:input type="file" wire:model="logo" />
                <flux:error name="logo" />
            </flux:field>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <flux:field class="md:col-span-2">
                <flux:label>{{ __('Business Name') }}</flux:label>
                <flux:input wire:model="name" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Business Email') }}</flux:label>
                <flux:input type="email" wire:model="email" />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Business Phone') }}</flux:label>
                <flux:input wire:model="phone" />
                <flux:error name="phone" />
            </flux:field>

            <flux:field class="md:col-span-2">
                <flux:label>{{ __('Address') }}</flux:label>
                <flux:input wire:model="address" />
                <flux:error name="address" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Branding Color') }}</flux:label>
                <div class="flex gap-2 items-center">
                    <input type="color" wire:model="primary_color" class="w-10 h-10 border border-zinc-200 dark:border-zinc-700 rounded-lg cursor-pointer p-0 bg-transparent" />
                    <flux:input wire:model="primary_color" class="flex-1" />
                </div>
                <flux:error name="primary_color" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Invoice Start Number') }}</flux:label>
                <flux:input type="number" wire:model="invoice_start_number" min="1" />
                <flux:error name="invoice_start_number" />
            </flux:field>
        </div>

        <flux:separator />

        <div>
            <flux:heading size="lg">{{ __('Payment Details') }}</flux:heading>
            <flux:subheading>{{ __('This information will appear on your invoices.') }}</flux:subheading>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <flux:field>
                <flux:label>{{ __('Account Holder Name') }}</flux:label>
                <flux:input wire:model="payment_name" />
                <flux:error name="payment_name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Account Number') }}</flux:label>
                <flux:input wire:model="payment_account_number" />
                <flux:error name="payment_account_number" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Sort Code') }}</flux:label>
                <flux:input wire:model="payment_sort_code" />
                <flux:error name="payment_sort_code" />
            </flux:field>
        </div>

        <flux:separator />

        <flux:field>
            <flux:label>{{ __('Default Invoice Message') }}</flux:label>
            <flux:textarea wire:model="default_invoice_message" placeholder="{{ __('e.g. Thank you for your business!') }}" />
            <flux:error name="default_invoice_message" />
        </flux:field>

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">{{ __('Save Changes') }}</flux:button>
        </div>
    </form>
</x-settings.layout>
