# Changelog

## [1.1.1] - 2024-06-28
- Renamed plugin to Discord Webhook Notifications for MainWP to clarify branding and added disclaimer that this project is not associated with MainWP or an official extension
- Added contributing guidelines file and updated readme
- Updated plugin headers
- Added support link to plugin meta
- Refactored the sending method to be more readable and conditionally include the View Full Changelog url
- Added error logging if webhook URL constants are not defined

## [1.1.0] - 2024-06-27
- Updated the plugin constant to `MAINWP_PLUGIN_UPDATES_DISCORD_WEBHOOK_URL` with backward compatibility for the `MAINWP_UPDATES_DISCORD_WEBHOOK_URL`. Thanks @JosKlever for the suggestion.
- Added checks to ensure the respective constants are set before running the functions. If no constants are set, the plugin will not attempt to send any webhook data.

## [1.0.1] - 2024-06-27
- Fixed incorrect column header for ignored theme updates to `is_ignoreThemeUpdates`. Thanks @rwsiv and @JosKlever for the report and PR.

## [1.0] - 2024-06-21

### Added
- Initial release of MainWP Discord Webhook Notifications.
- Added functionality to send Discord notifications for plugin updates available on MainWP child sites.
- Included plugin details in the notifications: Plugin Name, Version, Author, Description, Changelog Summary, and a link to the full changelog.
- Added caching of plugin update results to improve performance and reduce database queries.
- Implemented rate limiting to avoid hitting Discord API limits.
- Added functionality to fetch and cache thumbnail images from the plugin's or theme's URL.
- Added functionality to exclude ignored plugin updates based on the `is_ignorePluginUpdates` column.
- Added transient to track and avoid sending duplicate notifications for the same plugin version.
- Implemented proper error handling and logging for Discord webhook failures.
- Added theme update notifications with similar details as plugin updates.
- Added functionality to exclude ignored theme updates based on the `is_ignoreThemeUpdates` column.

### Changed
- Enhanced notification message formatting for better readability.
- Updated code to comply with WordPress Coding Standards (WPCS).

### Fixed
- Fixed issues with fetching thumbnail images from plugin URLs.
- Corrected multiple phpcs warnings.
