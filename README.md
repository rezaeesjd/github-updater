# GitHub Plugin Installer and Updater

This helper plugin installs or updates the Bokun Bookings Management plugin directly from GitHub. It also supports self-updates when you provide the repository that hosts this helper.

## Release notes

### 1.0.2 – Test self-update notification
- Bump the plugin version again so WordPress surfaces the latest build when testing the self-update workflow.

### 1.0.1 – Test update
- Bump the plugin version so WordPress surfaces an available update when the self-update repository is configured.
- Expose the plugin version constant for enqueueing assets, ensuring cache-busting when updates ship.
- Purge the GitHub response cache whenever WordPress clears plugin updates (and when forcing checks) while reducing the cache TTL to five minutes, so new releases show up immediately in the dashboard.

### 1.0.0 – Initial release
- Initial public release.
