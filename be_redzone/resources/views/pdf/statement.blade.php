<!DOCTYPE html>
<html>
<head>
    <title>Statement of Account</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h2>Statement of Account</h2>
    <p><strong>Subscription ID:</strong> {{ $subscription->id }}</p>
    <p><strong>Customer:</strong> {{ $subscription->user->name ?? 'N/A' }}</p>
    <p><strong>Start Date:</strong> {{ $subscription->start_date }}</p>

    <h3>Payment History</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
            <tr>
                <td>{{ $payment->created_at->format('Y-m-d') }}</td>
                <td>{{ ucfirst($payment->payment_type) }}</td>
                <td>{{ number_format($payment->amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h3>Subscription Histories</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Action</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach($histories as $history)
            <tr>
                <td>{{ $history->created_at->format('Y-m-d') }}</td>
                <td>{{ $history->action }}</td>
                <td>{{ $history->remarks ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
