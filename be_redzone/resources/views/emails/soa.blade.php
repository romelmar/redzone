<p>Hi {{ $subscription->subscriber->name }},</p>
<p>Please find attached your Statement of Account for {{ $month->isoFormat('MMMM YYYY') }}.</p>
<p>Total Amount Due: <strong>₱{{ number_format($billing['total_due'],2) }}</strong></p>
<p>Due Date: <strong>{{ $billing['due_date']->toFormattedDateString() }}</strong></p>
<p>Thank you.</p>
