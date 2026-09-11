<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Statement of Account</title>
    <style>
        @page {
            margin: 30px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #263445;
        }

        h1 {
            font-size: 22px;
            margin-bottom: 6px;
        }

        .brand {
            color: #a02017;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }

        th,
        td {
            padding: 7px 5px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }

        th {
            background: #f0f2f5;
            text-align: left;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }

        .money {
            text-align: right;
            white-space: nowrap;
        }

        .note {
            color: #666;
            font-size: 9px;
        }

        .summary {
            margin-top: 16px;
            font-size: 12px;
        }

        /* DOMPDF-SAFE HEADER USING TABLE */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        td>p {
            margin: 5px 0 0;
        }

        td>p.small {
            font-size: 10px;
        }

        .header-table td {
            vertical-align: middle;
            padding: 0 20px 10px;
        }

        .header-left {
            width: 35%;
        }

        .header-right {
            text-align: right;
            white-space: nowrap;
            font-size: 11px;
            line-height: 1.4;
        }

        .header-right .highlight {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            color: #A02017
        }


        .bill-header {
            /* Fallback for older browsers */
            /* background-color: #a02017; */
            /* Standard radial gradient starting as #a02017 in the center and expanding to black */
            background: #A02017;
            background: linear-gradient(90deg, rgba(160, 32, 23, 1) 0%, rgba(95, 19, 14, 1) 70%, rgba(77, 15, 11, 1) 85%, rgba(0, 0, 0, 1) 100%);

            color: #fff;
            padding: 6px 12px;
            font-size: 11px;
            margin: 0;
        }

        .bill-header table td {
            padding: 0 30px;
        }

        .account-info,
        .soa-info {
            padding: 15px 25px;
        }

        .account-info {
            width: 55%;
            float: left;
        }

        .soa-info {
            margin-top: 20px;
            width: 40%;
            float: right;

            font-size: 12px;
        }

        .soa-info table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }

        .soa-info td {
            padding: 6px;
            border-bottom: 1px solid #ccc;
        }

        .summary {
            clear: both;
            /* padding: 20px 25px; */
            margin-top: 100px;
        }

        .summary table {
            /* width: 100%; */
            border-collapse: collapse;
            margin-bottom: 20px;
            /* border: 1px solid #000;
             */
        }

        .summary th,
        .summary td {
            /* padding: 5px; */
            border-bottom: 1px solid #ddd;
            text-align: left;
            padding: 5px;
        }

        .summary thead {
            border-bottom: 5px solid #ddd;
        }

        /* Style the container TD to control padding */
        .seperator-cell {
            border: none !important;
            /* Remove any default table cell borders */
            padding: 10px 0;
            /* Add vertical spacing */
        }

        /* Style the DIV to be the dashed line itself */
        .seperator-line {
            border-top: 2px dashed #000;
            /* This border will be rendered evenly */
            height: 0;
            /* The div can be zero height, the border is its height */
            margin: 0 10px;
            /* Optional: adds a little space from the table edges */
        }


        /* Optional: Ensure the second row of the tfoot aligns nicely */
        tfoot td {
            padding: 5px 0;
        }

        .summary .total {
            font-size: 14px;
            font-weight: bold;
            border: 1px solid #000;
            padding: 8px;
            text-align: right;
            background: #f5e2c1;
        }

        .notice {
            margin-top: 50px;
            padding: 15px 25px;
            font-size: 11px;
        }

        .notice strong {
            color: red;
        }

        .terms {
            padding: 15px 25px;
            font-size: 11px;
        }
    </style>
</head>

<body>
    <table class="header-table">
        <tr>
            <td class="header-left">
                <img src="{{ public_path('logo.png') }}" height="105">
            </td>

            <td class="header-right">
                <div class="highlight">{{ config('app.name', 'Redzone Wireless Internet Services') }}</div>
                <div>Brgy. Poblacion, Leon, Iloilo</div>
                <div>Email: leauncesan@yahoo.com</div>
                <div>Contact: +63 930 211 4082</div>
            </td>
        </tr>
    </table>
    <h1>Statement of Account</h1>
    <p>{{ $statement['subscriber'] }}<br>Subscriber #{{ $statement['subscriber_id'] }} / Subscription
        #{{ $statement['subscription_id'] }}<br>{{ $statement['plan'] }}</p>
    <p>Period: {{ $statement['from'] }} to {{ $statement['to'] }}<br>Generated: {{ now()->format('Y-m-d H:i') }}</p>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Reference / Description</th>
                <th class="money">Charges (PHP)</th>
                <th class="money">Credits (PHP)</th>
                <th class="money">Balance (PHP)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $statement['from'] }}</td>
                <td>Opening balance</td>
                <td></td>
                <td></td>
                <td class="money">{{ number_format($statement['opening_balance'], 2) }}</td>
            </tr>
            @foreach ($statement['entries'] as $entry)
                <tr>
                    <td>{{ $entry['date'] }}</td>
                    <td>{{ $entry['reference'] }}<br>{{ $entry['description'] }}</td>
                    <td class="money">{{ number_format($entry['debit'], 2) }}</td>
                    <td class="money">{{ number_format($entry['credit'], 2) }}</td>
                    <td class="money">{{ number_format($entry['balance'], 2) }}</td>
                </tr>
            @endforeach
            @if (!count($statement['entries']))
                <tr>
                    <td colspan="5">No transactions in this period.</td>
                </tr>
            @endif
            <tr>
                <th colspan="2">Period totals / Closing balance</th>
                <th class="money">{{ number_format($statement['total_charges'], 2) }}</th>
                <th class="money">{{ number_format($statement['total_credits'], 2) }}</th>
                <th class="money">{{ number_format($statement['closing_balance'], 2) }}</th>
            </tr>
        </tbody>
    </table>
    <p class="summary">{{ $statement['closing_balance'] < 0 ? 'Credit balance' : 'Amount outstanding' }}: PHP
        {{ number_format(abs($statement['closing_balance']), 2) }}</p>
    <p class="note">Monthly charges and service credits are posted to their billing month on its first day. Payments
        use their recorded payment date. Voided payments are excluded. This statement reflects current records and is
        not a payment receipt. Negative balances represent account credit.</p>
</body>

</html>
