<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>{{ $title }}</title>
<style>
@page { margin: 22px 24px 32px; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
h1 { font-size: 18px; margin: 0 0 5px; }
p { margin: 4px 0; }
table { width: 100%; border-collapse: collapse; margin-top: 12px; table-layout: fixed; }
thead { display: table-header-group; }
tr { page-break-inside: avoid; }
th, td { border: 1px solid #bbb; padding: 5px; text-align: left; vertical-align: top; overflow-wrap: break-word; }
th { background: #eee; }
.money { text-align: right; }
.muted { color: #555; font-size: 8px; }
.footer { position: fixed; bottom: -20px; right: 0; font-size: 8px; }
.page:after { content: counter(page); }
</style></head>
<body>
<h1>REDZONE · {{ $title }}</h1>
<p>Balances as of {{ $date }} · Printed {{ $printedAt }} (Philippines)</p>
<p>{{ $rows->count() }} accounts / {{ $rows->pluck('subscriber_id')->unique()->count() }} subscribers · One row per subscription.</p>
@if (!empty($search))<p>Search: {{ $search }}</p>@endif
@isset($sortBy)<p>Sorted by {{ ucwords(str_replace('_', ' ', $sortBy)) }} ({{ $sortDir === 'desc' ? 'descending' : 'ascending' }}).</p>@endisset
<p class="muted">Disconnected means currently marked inactive in REDZONE, not live network monitoring. Accounts due today are not yet overdue. Balance includes this billing month's charges; voided and future-dated payments are excluded.</p>
<table>
<thead><tr><th style="width:8%">Subscriber / Subscription ID</th><th style="width:19%">Subscriber / Contact</th><th style="width:18%">Address</th><th style="width:13%">Plan</th><th style="width:13%">Status / Disconnected</th><th style="width:9%">Current month's due date</th><th style="width:10%" class="money">Overdue (PHP)</th><th style="width:10%" class="money">Balance (PHP)</th></tr></thead>
<tbody>
@forelse ($rows as $row)
<tr><td>{{ $row['subscriber_id'] }} / {{ $row['subscription_id'] }}</td><td>{{ $row['name'] }}<br><span class="muted">{{ $row['phone'] ?: 'No phone recorded' }}</span></td><td>{{ $row['address'] ?: '—' }}</td><td>{{ $row['plan'] }}</td><td>{{ $row['active'] ? 'Active' : 'Disconnected' }}<br>{{ $row['disconnected_at'] ?: '' }}</td><td>{{ $row['due_date'] }}</td><td class="money">{{ number_format($row['overdue'], 2) }}</td><td class="money">{{ number_format($row['balance'], 2) }}</td></tr>
@empty
<tr><td colspan="8">No matching accounts.</td></tr>
@endforelse
<tr><th colspan="6">Totals</th><th class="money">{{ number_format($rows->sum('overdue'), 2) }}</th><th class="money">{{ number_format($rows->sum('balance'), 2) }}</th></tr>
</tbody></table>
<div class="footer">REDZONE · Page <span class="page"></span></div>
</body></html>
