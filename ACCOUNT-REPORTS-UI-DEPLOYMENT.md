# Account reports interface update

This frontend update adds report tabs, balance summaries, configurable columns, compact rows, account detail dialogs, loading and empty states, and a print scope confirmation.

Upload the contents of `frontend/` from the UI ZIP to your frontend document root on Hostinger, then refresh the browser. The production API is `https://api.rosnel-partnership.com`. No server-side npm build is needed.

This package requires the account report management backend from `ACCOUNT-REPORTS-DEPLOYMENT.md` to already be installed. It contains no backend changes or database migrations.

Validation: Vite production build. Browser interaction and the hosted deployment still need a smoke check: open both report categories, search, sort, change columns, open an account, and generate a PDF.
