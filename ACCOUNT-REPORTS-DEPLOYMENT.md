# Account report management

Open **Overdue Accounts** or **Disconnected Accounts** from the sidebar or Dashboard. Both open the account report management page, with the appropriate report selected. The combined view is also available.

1. Choose overdue, disconnected, or combined accounts.
2. Select the balance date and search by subscriber name, subscriber/subscription ID, phone, address, or plan.
3. Click **Apply filters**. Click any table heading to toggle ascending/descending order. Sorting applies across all matching records before pagination.
4. Review account counts and total balances. Change rows per page or navigate pages as needed.
5. Use **Columns** to choose visible fields or switch between comfortable and compact rows. Click a subscriber name to review account details.
6. Click **Print report**, review the scope, then choose **Generate PDF**. Use the PDF viewer's Print button. The PDF includes all report columns regardless of table visibility.

Changing filters disables printing until those changes have been applied. Printing includes all filtered pages. PDF data is recalculated when requested, so payments or status changes made after reviewing the table can affect the report. Status is the account's current status; the date filter applies to balances. No account records are edited by this page.

## Hostinger upload

The ZIP contains a locally built `frontend/` folder and backend updates. This update assumes the operations release is already installed and requires no new migration.

- Upload the contents of `frontend/` to the frontend document root.
- Merge `backend/` into the Laravel backend root, preserving paths. It includes the report controller, API routes, PDF template, and the disconnection billing fix.
- Keep the production `.env` and existing data. The frontend build uses `https://api.rosnel-partnership.com`.
- Clear cached routes and views if enabled: `php artisan route:clear` and `php artisan view:clear` in the backend directory.
- Refresh the browser and sign in.

No Node build is required on Hostinger. Production has not been updated by preparing this package.

Verification: operations tests cover searching, sorting before pagination, full-result printing in matching order, authenticated access, and PDF generation.
