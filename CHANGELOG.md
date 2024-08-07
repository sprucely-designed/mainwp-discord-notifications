# Changelog

## [1.2.3] - 2024-07-19
- Switched from transient-based storage to update_option for storing sent notifications. Fixes an issue where notifications were sent multiple times due to the transient not being reliable with object cached setups.

## [1.2.2] - 2024-07-12
- Improved the sprucely_mwpdn_convert_html_to_markdown function to strip unnecessary attributes from HTML tags.
- Fixed an issue where incomplete URLs in changelog links were not transformed correctly into Markdown links.
- Optimized the handling of list tags to reduce unnecessary whitespace.
- Bumped version to correct issue from dev branch rebase.

## [1.1.6] - 2024-07-12
- Updated plugin header data to improve update changelog data handling.
- Added requirement for MainWP Dashboard plugin as this plugin does nothing without it.

## [1.1.5] - 2024-07-11
- Add the #developers anchor tag to the changelog url for plugins on wordpress.org, so the changelog tab is directly opened. Thanks @JosKlever for the idea and PR.

## [1.1.4] - 2024-06-28
- Correct typo in download link. (Thanks @JosKlever)
- Removed non-working Github Actions workflow in favor of later testing.
- Refactored the constant checks to correct issues noted in #9 PR by @JosKlever

## [1.1.3] - 2024-06-28
- Added heading tags to markdown conversion

## [1.1.2] - 2024-06-28
- Update .gitattributes to exclude .github directory and .gitattributes file from release zip
- Added Github Actions workflow to automatically tag releases on version changes
- Added helper function to convert common HTML to markdown

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
