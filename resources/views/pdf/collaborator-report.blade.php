<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payout Report - {{ $user->name }}</title>
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
            border-bottom: 2px solid #eaeaea;
            padding-bottom: 20px;
        }
        .logo-placeholder {
            font-size: 18px;
            font-weight: bold;
            color: #111;
        }
        .title {
            text-align: right;
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
            color: #ef4444; /* red-500 */
        }
        .company-details {
            float: left;
            width: 50%;
        }
        .report-details {
            float: right;
            width: 50%;
            text-align: right;
        }
        .clearfix {
            clear: both;
        }
        .summary-box {
            background-color: #f9f9f9;
            border: 1px solid #eaeaea;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 30px;
        }
        .summary-col {
            float: left;
            width: 33.33%;
            text-align: center;
        }
        .summary-value {
            font-size: 18px;
            font-weight: bold;
            color: #111;
            margin-top: 5px;
        }
        .summary-value.payout {
            color: #ef4444;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th {
            background-color: #f4f4f5;
            color: #52525b;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
            padding: 8px 10px;
            border-bottom: 1px solid #e4e4e7;
            text-align: left;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #f4f4f5;
            vertical-align: top;
        }
        .text-right {
            text-align: right;
        }
        .font-semibold {
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-house {
            background-color: #e4e4e7;
            color: #3f3f46;
        }
        .badge-office {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .badge-paid {
            background-color: #d1fae5;
            color: #065f46;
        }
        .badge-unpaid {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .footer {
            margin-top: 50px;
            border-top: 1px solid #eaeaea;
            padding-top: 15px;
            text-align: center;
            font-size: 10px;
            color: #71717a;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-details">
            <span class="logo-placeholder">{{ $company->name }}</span>
            <div style="margin-top: 5px; color: #71717a;">
                {{ $company->address }}<br>
                {{ $company->email }} | {{ $company->phone }}
            </div>
        </div>
        <div class="report-details">
            <div class="title">{{ __('Payout Report') }}</div>
            <div style="margin-top: 5px; color: #71717a;">
                <strong>{{ __('Collaborator') }}:</strong> {{ $user->name }}<br>
                <strong>{{ __('Period') }}:</strong> {{ $startDate }} - {{ $endDate }}
            </div>
        </div>
        <div class="clearfix"></div>
    </div>

    <div class="summary-box">
        <div class="summary-col">
            <span style="color: #71717a; text-transform: uppercase; font-size: 10px;">{{ __('Hourly Rate') }}</span>
            <div class="summary-value">{{ Number::currency($user->hourly_rate, 'GBP') }}/h</div>
        </div>
        <div class="summary-col">
            <span style="color: #71717a; text-transform: uppercase; font-size: 10px;">{{ __('Total Hours') }}</span>
            <div class="summary-value">{{ number_format(collect($services)->sum('share_hours'), 2) }}h</div>
        </div>
        <div class="summary-col">
            <span style="color: #71717a; text-transform: uppercase; font-size: 10px;">{{ __('Total Payout') }}</span>
            <div class="summary-value payout">{{ Number::currency(collect($services)->sum('payout'), 'GBP') }}</div>
        </div>
        <div class="clearfix"></div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 15%;">{{ __('Date') }}</th>
                <th>{{ __('Service / Location') }}</th>
                <th style="width: 15%;">{{ __('Type') }}</th>
                <th style="width: 15%;" class="text-right">{{ __('Your Hours') }}</th>
                <th style="width: 15%;">{{ __('Status') }}</th>
                <th style="width: 15%;" class="text-right">{{ __('Payout') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($services as $service)
                <tr>
                    <td>
                        <span class="font-semibold">{{ $service['date'] }}</span><br>
                        <span style="color: #71717a; font-size: 10px;">{{ $service['time'] }}</span>
                    </td>
                    <td>
                        <span class="font-semibold" style="font-size: 13px;">{{ $service['description'] }}</span><br>
                        <span style="color: #71717a;">{{ $service['customer_name'] }} - {{ $service['location'] }}</span>
                    </td>
                    <td>
                        <span class="badge badge-{{ $service['location_type'] }}">{{ __($service['location_type']) }}</span>
                    </td>
                    <td class="text-right font-semibold">
                        {{ number_format($service['share_hours'], 2) }}h<br>
                        <span style="color: #71717a; font-size: 10px; font-weight: normal;">{{ __('of') }} {{ number_format($service['total_duration'], 2) }}h</span>
                    </td>
                    <td>
                        <span class="badge badge-{{ $service['payout_status'] }}">{{ __($service['payout_status']) }}</span>
                    </td>
                    <td class="text-right font-semibold" style="color: #ef4444; font-size: 13px;">
                        {{ Number::currency($service['payout'], 'GBP') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        {{ __('Payout Report generated on') }} {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
