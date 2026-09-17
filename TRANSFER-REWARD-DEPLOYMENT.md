# One-month competitor transfer reward

Open **Subscriptions**, click **Transfer reward** beside a subscription, choose the billing month, and enter the previous provider. Click **Review credit**, then **Apply one-month reward**. For a new customer, create the subscription first, then grant the reward.

One reward is allowed per subscription. The credit covers that month's recurring fee after monthly discounts and existing service credits. Add-ons and previous balances remain due. Months before the subscription began, future months, and months without a remaining recurring charge are ineligible. This uses the application's existing billing-month convention, not a rolling 30-day trial.

The credit amount is saved when granted; it does not change the regular monthly price. The next month bills normally. If the selected month was already paid, the credit carries forward. The previous provider, staff name, and timestamps are retained. Retries cannot create another transfer reward for the same subscription. Reward credits cannot be changed or deleted through the normal service-credit editor.

Rewards appear in **Service credits**, billing totals, billing statements, and statements of account. They are not cash receipts or payment-history entries.

## Deploy to Hostinger

Back up your database and application files first. This release assumes earlier operations and statements updates are installed.

1. Upload `backend/` into the Laravel backend root, preserving paths.
2. Run `php artisan migrate --force` to add the reward tracking columns and unique index.
3. Run `php artisan route:clear` and `php artisan view:clear` if caches are enabled.
4. Upload all `frontend/` contents, including `index.html` and assets, then hard-refresh.

No npm build is needed on Hostinger. Keep your production `.env`. This package also includes the payment-history controller; the subscriptions UI uses the configured authenticated API client and displays recorded and voided payment entries separately.

Validation: 20 tests passed with 136 assertions, covering transfer rewards, duplicate prevention, stale quotes, disconnected months, billing carry-forward, statements, and payment history. Production frontend build passed. The additive migration was applied locally; no reward was granted to a real customer. Production has not been changed and browser visual verification remains.
