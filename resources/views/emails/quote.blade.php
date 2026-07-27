<x-mail::message>
@if($quote->company->logo && file_exists(public_path('storage/' . $quote->company->logo)))
<img src="{{ $message->embed(public_path('storage/' . $quote->company->logo)) }}" style="height: 50px; max-height: 50px; margin-bottom: 20px;" alt="{{ $quote->company->name }}" />
@endif

# {{ __('Hello, :name!', ['name' => $quote->customer->name]) }}

{{ __('Please find attached your quote :number from :company.', [
    'number' => $quote->number,
    'company' => $quote->company->name,
]) }}

**{{ __('Quote Details:') }}**
- **{{ __('Quote Number') }}:** {{ $quote->number }}
- **{{ __('Date') }}:** {{ $quote->date->format('d/m/Y') }}
- **{{ __('Total Amount') }}:** {{ Number::currency($quote->total_amount, 'GBP') }}
- **{{ __('Valid Until') }}:** {{ $quote->expiry_date ? $quote->expiry_date->format('d/m/Y') : __('N/A') }}

@if($quote->notes)
**{{ __('Message / Notes:') }}**
{{ $quote->notes }}
@endif

{{ __('If you have any questions about this quote, please do not hesitate to contact us.') }}

{{ __('Thanks,') }}<br>
**{{ $quote->company->name }}**
</x-mail::message>
