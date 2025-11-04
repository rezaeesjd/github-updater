# GitHub Plugin Installer and Updater

This helper plugin lets administrators install or update any WordPress plugin directly from GitHub. Map installed plugins to repositories, trigger manual downloads, and even update the helper itself without leaving the dashboard.

## Release notes

### 2.0.0 – Manage every plugin from GitHub
- Add a managed plugin table so you can map multiple installed plugins to their GitHub repositories.
- Introduce a dropdown-powered updater that lets you select the plugin to refresh on demand.
- Provide manual self-update buttons on the settings screen and the Plugins list.
- Refresh the plugin header to include WordPress compatibility metadata.

### 1.0.2 – Test self-update notification
- Bump the plugin version again so WordPress surfaces the latest build when testing the self-update workflow.

### 1.0.1 – Test update
- Bump the plugin version so WordPress surfaces an available update when the self-update repository is configured.
- Expose the plugin version constant for enqueueing assets, ensuring cache-busting when updates ship.
- Purge the GitHub response cache whenever WordPress clears plugin updates (and when forcing checks) while reducing the cache TTL to five minutes, so new releases show up immediately in the dashboard.

### 1.0.0 – Initial release
- Initial public release.
