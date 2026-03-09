<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">

    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #000;
            margin: 0;
            padding: 0;
        }

        DOMPDF-SAFE HEADER USING TABLE
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

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table th,
        .header-table td {
            border: 0
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 6px;
        }

        .header-table th {
            background: #f0f0f0;
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
                <div>Contact: +63 912 345 6789</div>
            </td>
        </tr>
    </table>
    @if(!empty($collector))
        <p><strong>Collector Filter:</strong> {{ $collector }}</p>
    @endif
    <p> Date: <strong>{{ \Carbon\Carbon::parse($date)->format('F d, Y') }}</strong> </p>

    <table>

        <thead id="data">
            <tr>
                <th>#</th>
                <th>Subscriber</th>
                <th>Plan</th>
                <th>Amount Due</th>
                <th>Phone</th>
                <th>Address</th>
            </tr>
        </thead>

        <tbody>

            @foreach ($rows as $i => $r)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $r['subscriber'] }}</td>
                    <td>{{ $r['plan'] }} {{ $r['speed'] }} Mbps</td>
                    <td>P {{ number_format($r['amount_due'], 2) }}</td>
                    <td>{{ $r['phone'] }}</td>
                    <td>{{ $r['address'] }}</td>
                </tr>
            @endforeach

        </tbody>

    </table>

</body>

</html>
