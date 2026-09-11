# REDZONE workspace interface update

The update applies shared typography, cards, form controls, table styling, responsive spacing, keyboard focus indicators, and dialog styling across the routed application pages.

Navigation groups customers and services, billing and collections, and reporting. List pages have page descriptions, loading feedback, clearer empty states, and keyboard support for existing sort controls. Collector assignment now uses the authenticated workspace layout. Account reports retain their column controls, compact mode, and print confirmation.

The login page uses REDZONE branding, credential autofill, loading protection, and inline errors. The account menu displays the current user and uses the server logout endpoint. Placeholder links and controls have been removed.

## Upload to Hostinger

1. Back up the currently deployed frontend files.
2. Upload the contents of `frontend/` in the ZIP to the frontend document root, preserving asset paths and existing server configuration.
3. Refresh the browser. No npm command is needed on Hostinger.

This frontend-only package assumes the previously supplied account management and operations backend is installed. There are no new migrations. The build uses `https://api.rosnel-partnership.com`.

## Verification

The Vite production build and whitespace checks passed. New navigation/login icons were checked against bundled icons. Browser interaction and visual checks were not available in this session.

After upload, check login and logout, each navigation destination, mobile navigation, light/dark themes, subscriber sorting, existing create/edit dialogs, collection assignment, and report printing. Production is not updated automatically by preparing this package.
