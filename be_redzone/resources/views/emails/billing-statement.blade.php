<p>Hello {{ $soa['subscriber']->name }},</p><p>Your billing statement for {{ $soa['billing_period']->format('F Y') }} is attached.</p><p>Total due: PHP {{ number_format($soa['total_due'], 2) }}</p><p>Thank you,<br>REDZONE</p>
@if(!empty($online_payment))
<p><a href="{{ $online_payment['url'] }}">Pay your current Redzone account balance online</a>{{ $online_payment['mode'] === 'test' ? ' — TEST MODE, no real money is collected.' : '' }}</p>
<p>This private link displays the current account balance, which may differ from the attached statement. The attached PDF also includes a payment QR code.</p>
@endif
