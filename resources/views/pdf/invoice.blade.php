<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice - {{ $invoice->number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #f4f4f5;
            padding-bottom: 20px;
        }
        .logo-placeholder {
            font-size: 22px;
            font-weight: bold;
            color: #18181b;
        }
        .title {
            text-align: right;
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
            color: {{ $invoice->company->primary_color ?? '#18181b' }};
            letter-spacing: 1px;
        }
        .company-logo {
            float: left;
            width: 50%;
        }
        .invoice-number-date {
            float: right;
            width: 50%;
            text-align: right;
        }
        .clearfix {
            clear: both;
        }
        .addresses-box {
            margin-bottom: 40px;
        }
        .bill-to {
            float: left;
            width: 50%;
        }
        .bill-from {
            float: right;
            width: 50%;
            text-align: right;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th {
            background-color: #f4f4f5;
            color: #71717a;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
            padding: 10px;
            border-bottom: 1px solid #e4e4e7;
            text-align: left;
        }
        td {
            padding: 12px 10px;
            border-bottom: 1px solid #f4f4f5;
            vertical-align: top;
        }
        .text-right {
            text-align: right;
        }
        .font-semibold {
            font-weight: bold;
        }
        .totals-table {
            width: 100%;
        }
        .totals-table td {
            border: none;
            padding: 6px 10px;
        }
        .totals-table tr.grand-total td {
            border-top: 2px solid #e4e4e7;
            font-size: 14px;
            font-weight: bold;
            color: #111;
        }
        .payment-info {
            background-color: #fafafa;
            border: 1px solid #f4f4f5;
            border-radius: 12px;
            padding: 15px;
            font-size: 11px;
        }
        .footer {
            margin-top: 60px;
            border-top: 1px solid #f4f4f5;
            padding-top: 15px;
            text-align: center;
            font-size: 10px;
            color: #71717a;
        }
        /* Draft watermark */
        .watermark {
            position: absolute;
            top: 30%;
            left: 5%;
            width: 90%;
            text-align: center;
            font-size: 70px;
            font-weight: bold;
            color: rgba(239, 68, 68, 0.15); /* red-500 opacity */
            border: 8px solid rgba(239, 68, 68, 0.15);
            border-radius: 20px;
            padding: 20px;
            text-transform: uppercase;
            transform: rotate(-15deg);
            z-index: 1000;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-paid {
            background-color: #d1fae5;
            color: #065f46;
        }
        .badge-draft {
            background-color: #f4f4f5;
            color: #3f3f46;
        }
        .badge-sent {
            background-color: #dbeafe;
            color: #1e40af;
        }
    </style>
</head>
<body>
    @if($invoice->status === 'draft')
        <div class="watermark">
            {{ __('DRAFT / RASCUNHO') }}
        </div>
    @endif

    <div class="header">
        <div class="company-logo">
            @if($invoice->company->logo)
                <img src="{{ public_path('storage/' . $invoice->company->logo) }}" style="height: 45px;" />
            @else
                <span class="logo-placeholder">{{ $invoice->company->name }}</span>
            @endif
        </div>
        <div class="invoice-number-date">
            <div class="title">{{ __('INVOICE') }}</div>
            <div style="margin-top: 5px; color: #71717a; font-size: 11px;">
                <strong>{{ __('Invoice Number') }}:</strong> {{ $invoice->number }}<br>
                <strong>{{ __('Date') }}:</strong> {{ $invoice->date->format('d/m/Y') }}<br>
            </div>
        </div>
        <div class="clearfix"></div>
    </div>

    <div class="addresses-box">
        <div class="bill-to">
            <strong style="color: #71717a; text-transform: uppercase; font-size: 10px; display: block; margin-bottom: 5px;">{{ __('Bill To') }}</strong>
            <strong style="font-size: 13px; color: #111;">{{ $invoice->customer->name }}</strong>
            <div style="margin-top: 5px; color: #52525b; line-height: 1.5;">
                {!! nl2br(e($invoice->customer->address)) !!}<br>
                {{ $invoice->customer->email }}
            </div>
        </div>
        <div class="bill-from">
            <strong style="color: #71717a; text-transform: uppercase; font-size: 10px; display: block; margin-bottom: 5px;">{{ __('From') }}</strong>
            <strong style="font-size: 13px; color: #111;">{{ $invoice->company->name }}</strong>
            <div style="margin-top: 5px; color: #52525b; line-height: 1.5;">
                {!! nl2br(e($invoice->company->address)) !!}<br>
                {{ $invoice->company->email }}<br>
                {{ $invoice->company->phone }}
            </div>
        </div>
        <div class="clearfix"></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th style="width: 15%;" class="text-right">{{ __('Hours') }}</th>
                <th style="width: 15%;" class="text-right">{{ __('Rate') }}</th>
                <th style="width: 15%;" class="text-right">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>
                        <span class="font-semibold" style="font-size: 13px; color: #18181b;">{{ $item->description }}</span>
                    </td>
                    <td class="text-right font-medium text-zinc-700">
                        {{ number_format($item->quantity, 2) }}h
                    </td>
                    <td class="text-right text-zinc-600">
                        {{ Number::currency($item->unit_price, 'GBP') }}/h
                    </td>
                    <td class="text-right font-semibold text-zinc-950">
                        {{ Number::currency($item->amount, 'GBP') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 30px;">
        <!-- Left: Payment details and message -->
        <div style="float: left; width: 55%; margin-right: 5%;">
            @if($invoice->company->payment_name || $invoice->company->payment_account_number)
                <div class="payment-info" style="margin-top: 0; padding: 12px;">
                    <strong style="font-size: 11px; color: #111; display: block; margin-bottom: 4px;">{{ __('Payment Details') }}</strong>
                    <p style="margin: 0; color: #52525b; line-height: 1.5; font-size: 10px;">
                        <strong>{{ __('Account Holder Name') }}:</strong> {{ $invoice->company->payment_name }}<br>
                        <strong>{{ __('Account Number') }}:</strong> {{ $invoice->company->payment_account_number }}<br>
                        <strong>{{ __('Sort Code') }}:</strong> {{ $invoice->company->payment_sort_code }}
                    </p>
                </div>
            @endif

            @if($invoice->notes)
                <div style="margin-top: 15px; font-size: 10px; color: #52525b;">
                    <strong style="font-size: 11px; color: #111; display: block; margin-bottom: 4px;">{{ __('Message / Notes') }}</strong>
                    <p style="margin: 0; line-height: 1.5;">{!! nl2br(e($invoice->notes)) !!}</p>
                </div>
            @endif
        </div>

        <!-- Right: Totals and Due Date -->
        <div style="float: right; width: 40%; text-align: right;">
            <table class="totals-table">
                @if($invoice->due_date)
                    <tr>
                        <td style="text-align: left; color: #71717a; padding: 4px 0;">{{ __('Due Date') }}</td>
                        <td class="text-right font-semibold" style="padding: 4px 0; color: #111;">{{ $invoice->due_date->format('d/m/Y') }}</td>
                    </tr>
                @endif
                <tr class="grand-total">
                    <td style="text-align: left; padding: 8px 0; border-top: 1px solid #e4e4e7;">{{ __('Total Amount') }}</td>
                    <td class="text-right" style="padding: 8px 0; border-top: 1px solid #e4e4e7; color: #111;">{{ Number::currency($invoice->total_amount, 'GBP') }}</td>
                </tr>
            </table>
        </div>
        <div class="clearfix"></div>
    </div>

    <div class="footer">
        {{ __('Invoice generated on') }} {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
