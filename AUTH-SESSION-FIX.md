# Authentication session recovery

The previous account menu waited for a successful server logout before returning to login. An expired session returned 401 and left cached user information visible.

This update clears local authentication state on every logout outcome, accepts 401 as already signed out, retries logout once after refreshing an expired CSRF token, and returns protected API sessions receiving 401 to the login page. Network/server errors still display a warning that server logout could not be confirmed.

Upload the ZIP's `frontend/` contents to your Hostinger frontend document root, then hard-refresh the page. This package includes the workspace UI and requires no backend changes or migrations.

Verification: six automated checks cover successful logout, 401, server failure, CSRF retry, network failure, and API expiration handling. The production frontend build uses `https://api.rosnel-partnership.com`.

The hosted server was not inspected. If 401 occurs immediately after a fresh login with this build, capture the failing request URL and the frontend website address so the production cookie/session configuration can be checked. This update repairs session recovery; it does not change hosting configuration.
