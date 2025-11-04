=== Github Plugin Installer and Updater ===
Contributors: websagesolutionslab
Tags: github, updater, plugin installer, deployment, automation
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
Github Plugin Installer and Updater empowers WordPress administrators to pull plugins straight from GitHub without leaving the dashboard. Map each plugin to its repository, authorize private downloads with a personal access token, and trigger manual refreshes whenever a new release ships. The helper plugin even maintains itself, so your deployment workflow stays consistent across projects.

== Features ==
* Install any plugin directly from a GitHub repository URL.
* Associate existing plugins with their GitHub projects for one-click updates.
* Manage multiple plugins from a single screen using the Managed Plugins table.
* Authorize private repositories with a token that is stored securely in WordPress options.
* Trigger on-demand updates from the dashboard or the Plugins list.
* Keep the helper current with built-in self-update tools.

== Why teams choose Websage Solutions ==
Manual zip uploads slow down releases, especially when juggling client sites. Websage Solutions created this plugin so agencies and product teams can:

* Standardize deployments across staging and production.
* Cut the wait for WordPress.org approvals when iterating on private or custom plugins.
* Give non-technical site managers a safe way to refresh plugins without Git access.
* Audit which repositories power each plugin at a glance, reducing onboarding time for new teammates.

== Installation ==
1. Download the latest release zip from the Websage Solutions Lab repository or use the "Download Plugin" button on the product page.
2. In the WordPress admin, navigate to **Plugins → Add New → Upload Plugin** and upload the zip file.
3. Activate the plugin and open **Tools → Github Plugin Installer and Updater** to configure repository settings.

== Frequently Asked Questions ==
= Does it work with private repositories? =
Yes. Generate a personal access token with `repo` scope and paste it into the settings page to authenticate downloads.

= Can I manage multiple plugins? =
Absolutely. Use the Managed Plugins table to map each installed plugin to its GitHub repository and branch or tag.

= How do self-updates work? =
Provide the helper plugin's own repository URL and it will notify you when a new release is available. You can trigger the update from the settings screen or directly from the Plugins list.

== Changelog ==
= 2.0.0 =
* Added the Managed Plugins table for mapping multiple sites to repositories.
* Introduced dropdown-powered manual updates.
* Added self-update buttons in the settings screen and Plugins list.
* Refreshed the plugin header with WordPress compatibility metadata.

= 1.0.2 =
* Bumped the plugin version to surface the latest build while testing self-updates.

= 1.0.1 =
* Exposed the plugin version constant for asset cache-busting.
* Cleared the GitHub response cache whenever WordPress refreshes plugin updates and reduced cache TTL to five minutes.

= 1.0.0 =
* Initial release.
