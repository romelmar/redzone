# Stop recurring fees after disconnection

Upload these two backend files to the matching paths in the Hostinger Laravel application:

- `app/Services/BillingService.php`
- `app/Http/Controllers/Api/SubscriptionController.php`

No frontend rebuild or new migration is required. This fix uses the existing subscription events table. Refresh reports after uploading; restart PHP through your hosting controls if old code remains cached.

Recurring monthly fees and monthly discounts stop from the month following the recorded disconnection. The disconnection month retains its existing full monthly charge; this fix does not introduce daily proration. Earlier unpaid charges remain collectible, and subsequent payments, service credits, and manually entered add-ons still apply.

Reconnection resumes charges in its month, while newly recorded disconnection/reconnection events preserve past inactive periods. Existing inactive accounts use their stored disconnection date, or end date if the disconnection date is absent. Reactivation saves that existing cutoff as an event before clearing it.

The service recalculates balances, dashboard totals, SOAs, and print reports using this rule. It does not delete payment records or alter previously exported invoice/PDF files.

An inactive account without a disconnection date, end date, or disconnection event requires the actual date before its balance can be calculated reliably. Previously reactivated gaps that were never saved cannot be reconstructed automatically. Do not substitute today's date for an unknown historical date.

Validation: 32 backend tests passed, including stopping recurring fees, collecting payments after disconnection, reactivation without charging inactive months, repeated disconnections, and the existing PDF/report tests.
