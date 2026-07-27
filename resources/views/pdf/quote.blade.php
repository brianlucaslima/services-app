<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quote - {{ $quote->number }}</title>
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
            border-bottom: 3px solid {{ $quote->company->primary_color ?? '#18181b' }};
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
            color: {{ $quote->company->primary_color ?? '#18181b' }};
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
            border-top: 3px solid {{ $quote->company->primary_color ?? '#18181b' }};
        }
        th {
            background-color: #f4f4f5;
            color: #71717a;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
            padding: 10px 15px;
            border-bottom: 1px solid #e4e4e7;
            text-align: left;
        }
        td {
            padding: 12px 15px;
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
            color: {{ $quote->company->primary_color ?? '#18181b' }};
        }
        .payment-info {
            background-color: #fafafa;
            border: 1px solid #e4e4e7;
            border-left: 4px solid {{ $quote->company->primary_color ?? '#18181b' }};
            border-radius: 4px;
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
    </style>
</head>
<body>
    @if($quote->status === 'draft')
        <div class="watermark">
            {{ __('DRAFT / RASCUNHO') }}
        </div>
    @endif

    <div class="header">
        <div class="company-logo">
            @if($quote->company->logo)
                <img src="{{ public_path('storage/' . $quote->company->logo) }}" style="height: 45px;" />
            @else
                <span class="logo-placeholder">{{ $quote->company->name }}</span>
            @endif
        </div>
        <div class="invoice-number-date">
            <div class="title">{{ __('QUOTE') }} {{ $quote->number }}</div>
            <div style="margin-top: 5px; color: #71717a; font-size: 11px;">
                <strong>{{ __('Date') }}:</strong> {{ $quote->date->format('d/m/Y') }}<br>
            </div>
        </div>
        <div class="clearfix"></div>
    </div>

    <div class="addresses-box">
        <div class="bill-to">
            <strong style="color: #71717a; text-transform: uppercase; font-size: 10px; display: block; margin-bottom: 5px;">{{ __('Quote To') }}</strong>
            <strong style="font-size: 13px; color: #111;">{{ $quote->customer->name }}</strong>
            <div style="margin-top: 5px; color: #52525b; line-height: 1.5;">
                {!! nl2br(e($quote->customer->address)) !!}<br>
                {{ $quote->customer->email }}
            </div>
        </div>
        <div class="bill-from">
            <strong style="color: #71717a; text-transform: uppercase; font-size: 10px; display: block; margin-bottom: 5px;">{{ __('From') }}</strong>
            <strong style="font-size: 13px; color: #111;">{{ $quote->company->name }}</strong>
            <div style="margin-top: 5px; color: #52525b; line-height: 1.5;">
                {!! nl2br(e($quote->company->address)) !!}<br>
                {{ $quote->company->email }}<br>
                {{ $quote->company->phone }}
            </div>
        </div>
        <div class="clearfix"></div>
    </div>

    <div style="text-align: center; margin-top: 10px; margin-bottom: 25px; font-weight: bold; font-size: 14px; color: #ef4444; text-transform: uppercase; letter-spacing: 1.5px;">
        {{ __('This is not a VAT invoice') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th style="width: 15%;" class="text-right">{{ __('Quantity / Hours') }}</th>
                <th style="width: 15%;" class="text-right">{{ __('Price') }}</th>
                <th style="width: 15%;" class="text-right">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quote->items as $item)
                <tr>
                    <td>
                        <span class="font-semibold" style="font-size: 13px; color: #18181b;">{{ $item->description }}</span>
                        @if($item->notes)
                            <div style="font-size: 10px; color: #71717a; margin-top: 4px; font-style: italic; white-space: pre-wrap;">{{ $item->notes }}</div>
                        @endif
                    </td>
                    <td class="text-right font-medium text-zinc-700">
                        {{ number_format($item->quantity, 2) }}
                    </td>
                    <td class="text-right text-zinc-600">
                        {{ Number::currency($item->unit_price, 'GBP') }}
                    </td>
                    <td class="text-right font-semibold text-zinc-950">
                        {{ Number::currency($item->amount, 'GBP') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 30px;">
        <!-- Left: message -->
        <div style="float: left; width: 55%; margin-right: 5%;">
            @if($quote->notes)
                <div style="margin-top: 0; font-size: 10px; color: #52525b;">
                    <strong style="font-size: 11px; color: #111; display: block; margin-bottom: 4px;">{{ __('Message / Notes') }}</strong>
                    <p style="margin: 0; line-height: 1.5;">{!! nl2br(e($quote->notes)) !!}</p>
                </div>
            @endif
        </div>

        <!-- Right: Totals and Expiry Date -->
        <div style="float: right; width: 40%; text-align: right;">
            <table class="totals-table">
                @if($quote->expiry_date)
                    <tr>
                        <td style="text-align: left; color: #71717a; padding: 4px 0;">{{ __('Due Date') }}</td>
                        <td class="text-right font-semibold" style="padding: 4px 0; color: #111;">{{ $quote->expiry_date->format('d/m/Y') }}</td>
                    </tr>
                @endif
                <tr class="grand-total">
                    <td style="text-align: left; padding: 8px 0; border-top: 1px solid #e4e4e7;">{{ __('Total Quote Amount') }}</td>
                    <td class="text-right" style="padding: 8px 0; border-top: 1px solid #e4e4e7; color: #111;">{{ Number::currency($quote->total_amount, 'GBP') }}</td>
                </tr>
            </table>
        </div>
        <div class="clearfix"></div>
    </div>

    <div class="footer">
        {{ __('Quote generated on') }} {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
