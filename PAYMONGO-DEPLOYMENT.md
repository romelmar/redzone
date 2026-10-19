# Redzone online payments

Redzone uses PayMongo v2 Hosted Checkout for full current account balances, initially GCash. Private links open `/pay#TOKEN`; the fragment is not sent to the frontend host. Public API access uses the bearer token. Masked subscriber details are shown. QR codes are rendered locally using BaconQrCode; no external QR service sees payment links.

## Configure a test deployment

Use a separate test database: verified sandbox payments create normal payment records and reduce balances. Never test against customer production data. Do not put secret keys in frontend variables or paste them into chat.

1. Deploy backend and frontend changes together. Run `composer install` and `php artisan migrate` after backing up the database. Build the frontend with `npm run build` and the deployment's existing `VITE_API_URL`. Configure SPA routing so `/pay` serves `index.html`.
2. Configure backend variables (disabled by default):

```dotenv
PAYMONGO_ENABLED=true
PAYMONGO_MODE=test
PAYMONGO_SECRET_KEY=sk_test_replace_me
PAYMONGO_WEBHOOK_SECRET=replace_me
PAYMONGO_PUBLIC_URL=https://your-redzone-frontend.example
```

The public URL must be HTTPS. Include a deployment subdirectory if needed, with no query or fragment. Update CORS to allow the exact frontend origin. Set `TELESCOPE_ENABLED=false` for public payment deployments; private payment URLs also occur in API requests, HTTP client payloads, statements and email, so keep application/proxy logs and mail private and redact token paths. Public tokens are stored hashed for lookup and encrypted for authenticated staff recovery.

3. Run `php artisan optimize:clear` after configuration changes. Keep the application encryption key stable so existing links remain recoverable.
4. Register a PayMongo **test-mode** webhook for `checkout_session.payment.paid` at `https://your-backend.example/api/webhooks/paymongo`. Use its signing secret above. The webhook needs public HTTPS access. If the backend is mounted in a subdirectory, include that prefix.
5. Configure the Laravel scheduler (`php artisan schedule:run` every minute). `paymongo:reconcile` runs every five minutes, without overlapping, to recover missing webhooks and flag interrupted creation. Run it manually for recovery too.
6. Create a test subscription and open Billing & statements or Subscriptions. Use Payment link or download/email a billing statement. Statements include a private link and QR code whenever online payments are configured and a current payable balance exists. A historical document's link explicitly pays the current account balance.
7. Complete a provider-hosted test payment. Confirm one payment record, reduced balance, payment audit, gross/fee/net values, and exclusion from collector remittance reconciliation. Close the page after paying to verify background delivery. Deliver the same signed event twice to verify deduplication.

No keys, migrations against existing databases, live payments, email delivery, or provider webhook registration are performed automatically by implementation tests.

## Payment behavior

- `ready → creating → pending → paid` (or `paid_review`). `review` blocks another checkout. `expired` allows staff to create a replacement.
- New links are available for seven days. The bill is recalculated at checkout in Philippine time; an updated amount must be reviewed again. Pending sessions preserve their original snapshot and amount even when the current balance changes. Repeated clicks resume that same session.
- Returning from PayMongo does not record payment. Signed fresh webhooks or authenticated server-to-server retrieval must verify session, reference, mode, PHP amount, GCash method and paid timestamp. Public polling provides recovery while the page is open; the scheduler handles closed pages.
- Provider payment ID, checkout ID and event key have database uniqueness constraints. Posting holds a subscription row lock, creates an audited Payment model, and updates the gateway record in the same database transaction. Ordinary manual payment creation uses that same subscription lock.
- Gross amount is fully credited to the subscription. Provider fee and net amount are stored separately. Payments use the provider's `paid_at` date converted to Asia/Manila, including payments for older bills.
- Concurrent cash payments are accepted. A later verified gateway payment is retained as account credit and flagged `paid_review` if it exceeds the current balance. Disconnected subscriptions remain disconnected and are flagged for reconnection review.
- Gateway payment amounts, subscription assignment and voids are protected from the ordinary edit/delete path. Refunds and reversals require staff reconciliation against PayMongo and a separately audited account adjustment; this release does not initiate refunds or automatically process reversal events. Service credits stay separate from payments.

## Staff recovery and settlement

Online payments shows checkouts, references, payment IDs, mode, review reasons, gross, fee and net amounts. Sync re-reads PayMongo through the backend. A timed-out creation is never blindly retried: use Find checkout after locating the reference in the PayMongo dashboard and enter its session ID. Backend verification rejects another account's session. An unknown session must be resolved before replacement; there is no unsafe forced reset.

Expire verifies provider expiration before allowing another request. A local link expiry alone does not expire a provider session. If a provider refuses expiration due to a payment in progress, wait and sync. For an unconfirmed creation with no session ID, locate and recover the provider session first.

PayMongo payments are included in revenue and account history but excluded from collector cash/GCash/bank remittance reports. Record settlement only after checking the bank payout, with a payout/bank reference and date; it creates a gateway audit event and no second payment. Settlement recording is manual and immutable in this release; automated payout matching and refund handling are future work.

For live rollout use `PAYMONGO_MODE=live`, a live secret key and a separate live webhook signing secret in the production deployment. Test mode is rejected when `APP_ENV=production`. Verify merchant GCash activation, then perform an authorized end-to-end payment and settlement check. Keep testing and production databases separate.

## Verification

```text
cd be_redzone
php artisan test
php artisan route:list --path=online-payments
cd ../fe_redzone
npm run build
```

Tests use isolated in-memory SQLite databases and fake provider responses. They cover duplicate delivery, invalid signatures, wrong amounts/modes, changed balances, creation ambiguity, early webhooks, missing webhook recovery, cash overlap, expiry, protected payment edits, settlements, public masking and statement QR rendering. They do not prove an actual merchant account can complete payment.

Provider references:
- https://docs.paymongo.com/docs/payment-channels-hosted-checkout
- https://docs.paymongo.com/docs/developer-tools-webhook-setup-management
