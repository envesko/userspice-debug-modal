# Debug Modal - AI instructions

Appends a Debug link to the footer **on localhost only** that opens a modal showing Session data,
User data, `$abs_us_root`, `$us_url_root`, POST, and quick dev links. Maintained by Envesko.

## Behaviour
- Renders only when `isLocalhost()` is true (and typically for a master/perm-2 user) - it is a
  development aid and must never expose data on a public host.
- Provides a session-destroy action and shortcuts (localhost login, admin panel).

## Dev extension point
Add custom sections to the dump: set `$GLOBALS['DebugModal_extra']['Label'] = $data;` anywhere before
the footer, or drop a `usersc/includes/debug_modal_custom.php` that populates that array. Each dump
section (Session/POST/User/Misc + extras) has a **Copy** button that copies clean JSON.

## Tables
None.

## Lifecycle files
install/activate/uninstall/delete (no tables)/migrate/configure · `files/` (webroot-copied modal +
`destroy_session.php`).

## Key conventions
Localhost gate is the security boundary - keep it as the first check in every entry point. Bumped to
UserSpice 6.1.0. `update.php` is deprecated (stub); future changes go in `migrate.php`.
