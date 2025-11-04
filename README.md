# GitHub Plugin Installer and Updater

**Publisher:** Websage Solutions Lab  •  **Company:** Websage Solutions

Install or refresh any WordPress plugin straight from GitHub without leaving wp-admin. Map installed plugins to their repositories, authorize private downloads with a token, and keep this helper plugin updated from the same screen.

## Why release it

Agencies and product teams repeatedly ship private plugins that never touch WordPress.org. Manual zip uploads slow the process, break automation, and make it hard for non-technical site managers to help. GitHub Plugin Installer and Updater replaces that manual work with an interface that understands GitHub releases.

## Key capabilities

- Install plugins by pasting a GitHub repository URL and selecting a branch or tag.
- Maintain multiple plugins at once through the Managed Plugins table.
- Trigger on-demand updates from wp-admin or the Plugins list.
- Configure a personal access token so private repositories download securely.
- Enable self-updates by pointing the helper at its own repository.

## Getting started

1. Download the latest release from the Websage Solutions Lab repository or use the **Download Plugin** button on the product landing page.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the downloaded zip, and activate it.
3. Open **Tools → Github Plugin Installer and Updater** to connect your GitHub repositories.

You will find full plugin metadata, feature breakdowns, and SEO content in [`readme.txt`](readme.txt) for WordPress.org and [`website/index.html`](website/index.html) for the public landing page.

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
