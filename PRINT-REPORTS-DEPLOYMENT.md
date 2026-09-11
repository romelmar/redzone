# Printable account reports

On the Dashboard, use **Print overdue subscribers**, **Print disconnected accounts**, or **Print combined report**. The PDF opens in another tab; use its Print button or download it. If popups are blocked, the file downloads instead.

Reports contain all matching subscriptions, sorted by subscriber name, with subscriber and subscription IDs, phone, address, plan, current status, disconnection date when recorded, current month's due date, overdue amount, and balance totals. The combined report includes each subscription only once. Disconnected means currently inactive in REDZONE; it does not measure live network connectivity. Disconnected accounts with zero balances are included. Accounts due today are not yet overdue.

## Upload to Hostinger

This update assumes the previous operations release is already installed. It requires no new migration or Node build on the server.

1. Merge the ZIP's `backend/` contents into the Laravel backend root:
   - `app/Http/Controllers/Api/OperationsController.php`
   - `routes/api.php`
   - `resources/views/pdf/account-status-report.blade.php`
2. Replace the frontend with the contents of the ZIP's `frontend/` directory (the locally built `fe_redzone/dist`).
3. If Laravel routes or views are cached, run `php artisan route:clear` and `php artisan view:clear` in the backend directory.
4. Refresh the browser, sign in, and use the print buttons on the Dashboard.

The build uses `https://api.rosnel-partnership.com`. Keep the server's existing production `.env`. Production data is not modified by this feature.
