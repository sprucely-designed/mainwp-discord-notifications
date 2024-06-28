# MainWP Discord Webhook Notifications

MainWP Discord Webhook Notifications is a WordPress plugin that sends notifications to a Discord server when plugin or theme updates are available on MainWP child sites.

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

1. **Download the Plugin:**
   - Clone this repository or download the ZIP file from the GitHub repository.

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

Contributions are welcome! Please follow these steps to contribute:

1. **Fork the Repository:**
   - Click on the `Fork` button at the top of this repository page to create a copy of the repository under your GitHub account.

2. **Clone Your Fork:**
   - Clone your fork to your local machine:
     ```sh
     git clone https://github.com/your-username/mainwp-discord-notifications.git
     ```

3. **Create a Branch:**
   - Create a new branch for your changes:
     ```sh
     git checkout -b my-feature-branch
     ```

4. **Make Your Changes:**
   - Make your changes to the codebase.

5. **Commit Your Changes:**
   - Commit your changes with a meaningful commit message:
     ```sh
     git add .
     git commit -m "Add my new feature"
     ```

6. **Push to Your Fork:**
   - Push your changes to your fork:
     ```sh
     git push origin my-feature-branch
     ```

7. **Submit a Pull Request:**
   - Go to the original repository and submit a pull request with a description of your changes.

## License

This plugin is licensed under the GPL2. See the [LICENSE](LICENSE) file for more details.

## Support

This plugin is provided as-is without any warranties. No official support is provided. However, you can submit an issue on GitHub if you encounter any problems or have any questions. Please note that submitting an issue does not guarantee a resolution or a response.

---

Developed by [Sprucely Designed](https://www.sprucely.net)
