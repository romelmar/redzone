<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Collection Route Sheet</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }

        table {
            width:100%;
            border-collapse: collapse;
        }

        th, td {
            border:1px solid #ccc;
            padding:6px;
        }

        th {
            background:#eee;
        }

        .header{
            margin-bottom:20px;
        }

    </style>
</head>

<body>

<div class="header">

<h2>Redzone Wireless Internet Services</h2>

<p>
Collector: <strong>{{ $collector }}</strong><br>
Date: <strong>{{ \Carbon\Carbon::parse($date)->format('F d, Y') }}</strong>
</p>

</div>

<table>

<thead>
<tr>
<th>#</th>
<th>Subscriber</th>
<th>Plan</th>
<th>Amount Due</th>
<th>Phone</th>
<th>Address</th>
<th>Notes</th>
</tr>
</thead>

<tbody>

@foreach($rows as $i => $row)

<tr>
<td>{{ $i + 1 }}</td>
<td>{{ $row['subscriber_name'] }}</td>
<td>{{ $row['plan'] }} {{ $row['speed'] }} Mbps</td>
<td>₱{{ number_format($row['amount_due'],2) }}</td>
<td>{{ $row['phone'] }}</td>
<td>{{ $row['address'] }}</td>
<td>{{ $row['notes'] }}</td>
</tr>

@endforeach

</tbody>

</table>

</body>
</html>