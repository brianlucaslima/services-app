<x-mail::message>
@if($invoice->company->logo && file_exists(public_path('storage/' . $invoice->company->logo)))
<img src="{{ $message->embed(public_path('storage/' . $invoice->company->logo)) }}" style="height: 50px; max-height: 50px; margin-bottom: 20px;" alt="{{ $invoice->company->name }}" />
@endif

# {{ __('Hello, :name!', ['name' => $invoice->customer->name]) }}

{{ __('Please find attached your invoice :number from :company.', [
    'number' => $invoice->number,
    'company' => $invoice->company->name,
]) }}

**{{ __('Invoice Details:') }}**
- **{{ __('Invoice Number') }}:** {{ $invoice->number }}
- **{{ __('Date') }}:** {{ $invoice->date->format('d/m/Y') }}
- **{{ __('Total Amount') }}:** {{ Number::currency($invoice->total_amount, 'GBP') }}
- **{{ __('Due Date') }}:** {{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : __('N/A') }}

@if($invoice->notes)
**{{ __('Message / Notes:') }}**
{{ $invoice->notes }}
@endif

{{ __('If you have any questions about this invoice, please do not hesitate to contact us.') }}

{{ __('Thanks,') }}<br>
**{{ $invoice->company->name }}**
</x-mail::message>
