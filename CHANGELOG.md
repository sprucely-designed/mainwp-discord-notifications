# Changelog

All notable changes to this project will be documented in this file.

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
