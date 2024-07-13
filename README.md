# Discord Webhook Notifications for MainWP

Discord Webhook Notifications for MainWP is a WordPress plugin that sends notifications to a Discord server when plugin or theme updates are available on MainWP child sites.

> **Disclaimer:** This is not an official MainWP plugin and is not affiliated with or endorsed by MainWP. It is an independent project developed by Sprucely Designed.


## Description

This plugin integrates with MainWP to monitor plugin and theme updates across all your connected child sites. When an update is detected, a notification is sent to a specified Discord webhook URL.

### Data Retrieval

- **Plugin Updates:** The plugin retrieves data from the `plugin_upgrades` column of the `wp_mainwp_wp` table, excluding sites with `is_ignorePluginUpdates` set to 1.
- **Theme Updates:** The plugin retrieves data from the `theme_upgrades` column of the `wp_mainwp_wp` table, excluding sites with `is_ignoreThemeUpdates` set to 1.

### Caching

To improve performance and reduce database load, the plugin uses caching:
- **Database Queries:** Results from database queries are cached for 15 minutes.
- **Thumbnail URLs:** Fetched thumbnail URLs are cached for one week.
- **Sent Notifications:** Notifications that have been sent are stored in a transient to prevent duplicate notifications, with a cache duration of one week.

## Installation

### Known Issues

#### Issue: Installation from GitHub Release Zip File

As tracked in [Issue #14](https://github.com/sprucely-designed/mainwp-discord-notifications/issues/14), when installing the plugin from the GitHub release `.zip` file, the folder created in the `wp-content/plugins` directory includes the `-v#.#.#` version info. This can cause issues during updates.

##### Workaround:
On the first install, follow these steps to remove the version info from the plugin folder name:

1. **Download the Latest Release:**
   - Download the latest release `.zip` file from GitHub.

2. **Extract and Rename:**
   - Extract the contents of the `.zip` file.
   - Rename the extracted folder from `mainwp-discord-notifications-v#.#.#` to `mainwp-discord-notifications`.

3. **Install the Plugin:**
   - Upload the renamed `mainwp-discord-notifications` folder to your `wp-content/plugins` directory.
   - Activate the plugin from the `wp-admin` Plugins page.

This ensures that future updates via Git Updater or manually will not encounter issues with folder name mismatches.

##### Future Fix:
Follow [Issue 14](https://github.com/sprucely-designed/mainwp-discord-notifications/issues/14) for updates. Future releases will include a build process to resolve this issue. Thank you for your understanding and patience.

1. **Download the Plugin:**
   - [Download](https://github.com/sprucely-designed/mainwp-discord-notifications/releases) the source code ZIP file from the latest release.

2. **Upload the Plugin:**
   - Go to your WordPress dashboard.
   - Navigate to `Plugins > Add New`.
   - Click on `Upload Plugin`.
   - Select the downloaded ZIP file and click `Install Now`.

3. **Activate the Plugin:**
   - After the plugin is installed, click on `Activate Plugin`.

4. **Configure Webhook URLs:**
   - Open your `wp-config.php` file.
   - Define the constants for the webhook URLs:
     ```php
     define( 'MAINWP_PLUGIN_UPDATES_DISCORD_WEBHOOK_URL', 'your_plugin_updates_webhook_url' );
     define( 'MAINWP_THEME_UPDATES_DISCORD_WEBHOOK_URL', 'your_theme_updates_webhook_url' );
     ```

## Updates

To get automatic updates for this plugin, you can use the [Git Updater](https://github.com/afragen/git-updater) plugin. For detailed instructions on how to install and configure Git Updater, please refer to the [Git Updater Documentation](https://git-updater.com/knowledge-base/general-usage/).

## Usage

Once installed and configured, the plugin will automatically check for updates following your `mainwp_cronupdatescheck_action` cron schedule (with an hourly fallback) and send notifications to the specified Discord channel if updates are available.

### Notification Format

The notifications will include:
- Plugin/Theme Name
- New Version
- Author (if available)
- Description (if available)
- Changelog Summary (if available)
- Link to the full changelog

## Contributing

Want to make MainWP better for everyone by contributing to Discord Notifications for MainWP? We'd love your help! Please read our contributing guide to learn about our development process and how to propose bug fixes and improvements.

## License

This plugin is licensed under the GPL3. See the [LICENSE](LICENSE) file for more details.

## Support

This plugin is provided as-is without any warranties. No official support is provided. However, you can submit an issue on GitHub if you encounter any problems or have any questions. Please note that submitting an issue does not guarantee a resolution or a response.

---

Developed by [Sprucely Designed](https://www.sprucely.net)
