<x-mail::message>
# {{ __('Hello, :name!', ['name' => $manager->name]) }}

{{ __('Here is your daily summary of sent invoices that are currently unpaid for :company.', [
    'company' => $manager->company->name,
]) }}

<x-mail::table>
| {{ __('Number') }} | {{ __('Customer') }} | {{ __('Due Date') }} | {{ __('Amount') }} |
| :--- | :--- | :--- | :--- |
@foreach ($invoices as $invoice)
| **{{ $invoice->number }}** | {{ $invoice->customer->name }} | {{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : __('N/A') }} | **{{ Number::currency($invoice->total_amount, 'GBP') }}** |
@endforeach
</x-mail::table>

**{{ __('Total Outstanding:') }}** {{ Number::currency($invoices->sum('total_amount'), 'GBP') }}

<x-mail::button :url="route('invoices')">
{{ __('Manage Invoices') }}
</x-mail::button>

{{ __('Thanks,') }}<br>
**{{ config('app.name', 'Invoease') }}**
</x-mail::message>
