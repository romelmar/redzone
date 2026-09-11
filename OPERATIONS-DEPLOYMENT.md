# REDZONE operations release

This release adds the daily dashboard, payment audit history, and collector reconciliation.

The upload ZIP contains `backend/` (merge into the Laravel backend root), `frontend/` (upload its contents to the frontend document root), and this guide. It contains no environment files, database exports, or credentials. The production database has not been migrated by this local build.

## Hostinger deployment

1. Back up the production database and current application files.
2. In the backend directory, run `php artisan down` while updating the release.
3. Upload the backend files listed below, keeping their directory structure. Keep the production `.env`, `vendor`, and storage files.
4. Run `php artisan migrate --force` from the backend directory. This creates payment audit and remittance tables, adds payment collector/method/void fields, and records a baseline for existing payments. It requires the earlier REDZONE migrations to be installed. Do not skip this step: the new payment model requires the new columns.
5. Run `php artisan optimize:clear`.
6. Upload the contents of `fe_redzone/dist` to the frontend document root.
7. Run `php artisan up`, refresh the frontend, and sign in.

The frontend is built locally with `npm run build`. The `.env.production` file points builds at `https://api.rosnel-partnership.com`; the development `.env` can continue using localhost. No Node build is required on Hostinger.

Backend files:

- `app/Http/Controllers/Api/OperationsController.php`
- `app/Http/Controllers/Api/PaymentController.php`
- `app/Http/Controllers/Api/SubscriptionController.php`
- `app/Models/CollectorRemittance.php`
- `app/Models/Payment.php`
- `app/Models/PaymentAudit.php`
- `database/migrations/2026_09_08_000003_add_operations_records.php`
- `routes/api.php`

## Using the release

- **Dashboard:** live collections for the Philippines business date, active/inactive subscription counts, overdue balances, and due dates within seven days. Collections exclude offsets, adjustments, and voided payments. Due dates today are not yet overdue. Future-dated payments do not reduce today's dashboard balances.
- **Payments:** record the collector name and cash, GCash, or bank method. Payment corrections require a reason. Void replaces deletion and retains the record while excluding it from balances and collections.
- **Payment Audit:** search by payment ID and inspect before/after values, staff name, reason, and time. Earlier payments receive a baseline entry; edits made before this release cannot be reconstructed. Audit entries are read-only through the application.
- **Cash Reconciliation:** select the date payments were collected. Record actual verified remittances for the same collector and method. Positive differences represent unremitted money; negative differences represent excess remittances to investigate. Partial remittances are supported. Mistaken remittances can be voided with a reason; their original records remain visible. Remittances do not create subscriber payments.
- Existing payments initially remain **Unassigned / unspecified**. Correct their collector and method where records support doing so. Collector names are grouped without regard to capitalization; use distinct names for different people.
- Existing authenticated accounts retain access. This release does not introduce separate owner/cashier/collector roles or external bank/payment verification.

The migration intentionally has no destructive rollback. Retain audit records and use a forward correction if a deployment issue arises.

## Verification

Run `php artisan test` in `be_redzone` and `npm run build` in `fe_redzone`.
After deploying, confirm the dashboard and new navigation links load. Validate a real payment's collector/method during normal processing, inspect its audit entry, and reconcile against an actual remittance. Avoid entering dummy financial records in production.
