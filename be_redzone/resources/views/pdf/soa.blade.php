<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Statement of Account</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #000;
            margin: 0;
            padding: 0;
        }

        /* DOMPDF-SAFE HEADER USING TABLE */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        .header-table td {
            vertical-align: middle;
            padding: 10px 20px;
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
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
        }


        .bill-header {
            background: #a02017;
            color: #fff;
            padding: 6px 12px;
            font-size: 11px;
            margin: 0;
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
    {{-- HEADER --}}
    <table class="header-table">
        <tr>
            <td class="header-left">
                <img src="{{ public_path('logo.png') }}" height="105">
            </td>

            <td class="header-right">
                <div class="highlight">{{ config('app.name', 'Redzone Wireless Internet Services') }}</div>
                <div>Brgy. Poblacion, Leon, Iloilo</div>
                <div>Email: leauncesan@yahoo.com</div>
                <div>Contact: +63 912 345 6789</div>
            </td>
        </tr>
    </table>


    {{-- Red Bar --}}
    <p class="bill-header">
        BIR Permit No. 123-456-789 | TIN: 000-000-000 | SOA No: {{ $subscription->id }}
    </p>

    {{-- Account & SOA Info --}}
    <div class="account-info">
        <p>Name:<strong> {{ $subscription->subscriber->name }}</strong></p>
        <p>Address:<strong> {{ $subscription->subscriber->address ?? 'N/A' }}</strong></p>
        <p>Contact:<strong> {{ $subscription->subscriber->contact ?? 'N/A' }}</strong></p>
        <div class="summary">
            <h3>Statement Summary</h3>

            <table>
                <thead>
                    <tr>
                        <th>Previous Bill Charges</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td></td>
                        <td></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="seperator-cell">
                            <!-- Add a div inside the cell -->
                            <div class="seperator-line"></div>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Remaining Balance from Previous Bill:</strong></td>
                        <td>P{{ number_format($soa['previous_balance'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
            <table>
                <thead>
                    <tr>
                        <th>Current Bill Charges</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Monthly Service Fee ({{ $subscription->plan->name }})</td>
                        <td style="text-align:right;">P{{ number_format($subscription->plan->price, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Addons</td>
                        <td style="text-align:right;">P{{ number_format($soa['addons_amount'], 2) }} </td>
                    </tr>
                    <tr>
                        <td>Amount Refunded ({{ $soa['credits_days'] }} day/s outage)</td>
                        <td style="text-align:right;">-P{{ number_format($soa['credits_amount'], 2) }} </td>
                    </tr>
                    <tr>
                        <td>VAT </td>
                        <td style="text-align:right;">P0.00</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="seperator-cell">
                            <!-- Add a div inside the cell -->
                            <div class="seperator-line"></div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2"><strong>Total Current Bill</strong></td>

                    </tr>

                    <tr>
                        <td colspan="2">
                            <p class="total">Total Amount Due: P{{ number_format($soa['total_due'], 2) }}</p>
                        </td>
                    </tr>

                </tfoot>
            </table>

        </div>

    </div>

    <div class="soa-info">
        <h1>Statement of Account</h1>
        <table>
            <tr>
                <td><strong>Bill Number:</strong></td>
                <td>{{ $subscription->id }}</td>
            </tr>
            <tr>
                <td><strong>Bill Period:</strong></td>
                <td>{{ now()->startOfMonth()->format('M d, Y') }} - {{ now()->endOfMonth()->format('M d, Y') }}</td>
            </tr>
            <tr>
                <td><strong>Account No:</strong></td>
                <td>{{ str_pad($subscription->id, 4, '0', STR_PAD_LEFT) }}</td>
            </tr>


            <tr>
                <td><strong>Previous Balance:</strong></td>
                <td>P{{ number_format($soa['previous_balance'], 2) }}</td>
            </tr>
            <tr>
                <td><strong>Current Charges:</strong></td>
                <td>P{{ number_format($soa['current_bill'], 2) }}</td>
            </tr>
            <tr>
                <td><strong>Total Due:</strong></td>
                {{-- <td><strong>P{{ number_format($subscription->balance, 2) }}</strong></td> --}}

                <td><strong>P{{ number_format($soa['total_due'], 2) }}</strong></td>
            </tr>
        </table>


    {{-- Notice --}}
    <div class="notice">
        <strong>NOTICE:</strong> For inquiries, please contact our support hotline or send us a message on our Facebook
        page.
    </div>

    {{-- Terms --}}
    <div class="terms">
        <p><strong>Terms and Conditions</strong></p>
        <p>Please send payment within 5 days upon receiving this invoice to avoid inconvenience.</p>
        <p>Thank You.</p>
    </div>
    </div>
</body>

</html>
