# Billing statements and statements of account

Open **Billing & statements** in the sidebar. This replaces **Subscribers with dues**; the old address redirects here. Use **Overdue accounts** for collection follow-up.

The statement workspace lists active and disconnected subscriptions. Search for a customer or subscription ID, choose the billing month, and open **Statements** beside the subscription. Shortcuts are also available from subscription actions and account report details.

- **Billing statement**: the selected month's bill, including previous balance, fees, discounts, add-ons, service credits, payments, and total due. Existing monthly PDFs and billing emails now use this name.
- **Statement of account**: choose a start date and through date, then click **Review** to see opening balance, dated activity, and running/closing balance. Download or email after reviewing. Negative balances show account credit. Periods may span up to ten years and cannot end in the future.

Statements follow the existing billing calculation: monthly charges and service credits are posted on the first day of their billing month; payments use their recorded payment date. Recurring charges stop after the disconnection month under the existing rules. Voided payments are excluded. Monthly charges show the fee, discount, and add-on breakdown in their description. These are regenerated from current records, not immutable copies of previously issued documents.

Email goes to the subscriber's recorded email address after confirmation. Missing or invalid addresses are rejected. No customer emails were sent during development.

## Hostinger deployment

1. Back up the current application files.
2. Merge `backend/` into the Laravel backend root, preserving paths.
3. Clear cached routes and views with `php artisan route:clear` and `php artisan view:clear` if caching is enabled. If deployment uses an authoritative Composer classmap, regenerate it to include the new classes.
4. Upload all `frontend/` contents to the frontend document root, including `index.html` and assets. Hard-refresh afterward.

This release assumes the previous operations backend and migrations are installed. No new migration is required. Keep production `.env` settings. Email uses the existing Laravel mail configuration; frontend API remains `https://api.rosnel-partnership.com`. No npm build is needed on Hostinger.

Validation: 25 billing, operations, and statement tests passed (214 assertions). New statement tests additionally passed after final route changes. Tests exercise real PDF rendering and fake mail delivery, including recipient, balances, disconnections, voids, opening balances, invalid dates, and missing email. Production frontend build passed. Hosted browser rendering and live mail delivery have not been verified.
