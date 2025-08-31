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

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 25px;
      border-bottom: 2px solid #000;
    }

    .company-info {
      font-size: 11px;
      line-height: 1.5;
    }

    .company-info .highlight {
      font-weight: bold;
    }

    .bill-header {
      background: #f44336;
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
      width: 40%;
      float: right;
      border: 1px solid #000;
      font-size: 12px;
    }

    .soa-info table {
      width: 100%;
      border-collapse: collapse;
    }

    .soa-info td {
      padding: 6px;
      border-bottom: 1px solid #ccc;
    }

    .summary {
      clear: both;
      padding: 20px 25px;
    }

    .summary table {
      width: 100%;
      border-collapse: collapse;
    }

    .summary th,
    .summary td {
      padding: 6px;
      border-bottom: 1px solid #ddd;
      text-align: left;
    }

    .summary .total {
      font-size: 14px;
      font-weight: bold;
      border: 1px solid #000;
      padding: 8px;
      text-align: right;
    }

    .notice {
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
  {{-- Header --}}
  <div class="header">
    <div>
      <img src="{{ public_path('logo.png') }}" alt="Company Logo" height="60">
    </div>
    <div class="company-info">
      <div class="highlight">{{ config('app.name') }}</div>
      <div>Brgy. Poblacion, Leon, Iloilo</div>
      <div>Email: support@company.com</div>
      <div>Contact: +63 912 345 6789</div>
    </div>
  </div>

  {{-- Red Bar --}}
  <p class="bill-header">
    BIR Permit No. 123-456-789 | TIN: 000-000-000 | SOA No: {{ $subscription->id }}
  </p>

  {{-- Account & SOA Info --}}
  <div class="account-info">
    <p><strong>Name:</strong> {{ $subscription->subscriber->name }}</p>
    <p><strong>Address:</strong> {{ $subscription->subscriber->address ?? 'N/A' }}</p>
    <p><strong>Contact:</strong> {{ $subscription->subscriber->contact ?? 'N/A' }}</p>
  </div>

  <div class="soa-info">
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
        <td>Previous Balance:</td>
        <td>P{{ number_format(0, 2) }}</td>
      </tr>
      <tr>
        <td>Current Balance:</td>
        <td>P{{ number_format($subscription->balance, 2) }}</td>
      </tr>
      <tr>
        <td><strong>Total Due:</strong></td>
        <td><strong>P{{ number_format($subscription->balance, 2) }}</strong></td>
      </tr>
    </table>
  </div>

  {{-- Statement Summary --}}
  <div class="summary">
    <table>
      <thead>
        <tr>
          <th>Description</th>
          <th style="text-align:right;">Amount</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Monthly Service Fee ({{ $subscription->plan->name }})</td>
          <td style="text-align:right;">P{{ number_format($subscription->plan->price, 2) }}</td>
        </tr>
        @foreach ($subscription->payments as $payment)
        <tr>
          <td>Payment - {{ \Carbon\Carbon::parse($payment->date)->format('M d, Y') }}</td>
          <td style="text-align:right;">-P{{ number_format($payment->amount, 2) }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    <p class="total">Total Amount Due: P{{ number_format($subscription->balance, 2) }}</p>
  </div>

  {{-- Notice --}}
  <div class="notice">
    <strong>NOTICE:</strong> For inquiries, please contact our support hotline or send us a message on our Facebook page.
  </div>

  {{-- Terms --}}
  <div class="terms">
    <p><strong>Terms and Conditions</strong></p>
    <p>Please send payment within 5 days upon receiving this invoice to avoid inconvenience.</p>
    <p>Thank You.</p>
  </div>

</body>
</html>
