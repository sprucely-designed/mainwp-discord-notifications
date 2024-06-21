<?php
/**
 * Plugin Name: MainWP Discord Webhook Notifications
 * Description: Sends a message to a Discord server when a plugin or theme update is available.
 * Version: 1.0
 * Author: Isaac @ Sprucely Designed <support@sprucely.net>
 * Author URI: https://www.sprucely.net
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * GitHub Plugin URI: https://github.com/sprucelydesigned/mainwp-discord-notifications
 *
 * @package MainWP Discord Webhook Notifications
 */

add_action( 'mainwp_child_plugin_activated', 'sprucely_mwpdn_setup_plugin_update_hook' );
add_action( 'mainwp_cronupdatescheck_action', 'sprucely_mwpdn_check_for_updates' );

/**
 * Sets up the scheduled event for checking updates.
 */
function sprucely_mwpdn_setup_plugin_update_hook() {
	if ( ! wp_next_scheduled( 'sprucely_mwpdn_check_for_updates' ) ) {
		wp_schedule_event( time(), 'hourly', 'sprucely_mwpdn_check_for_updates' );
	}
}

/**
 * Checks for plugin and theme updates.
 */
function sprucely_mwpdn_check_for_updates() {
	sprucely_mwpdn_check_for_plugin_updates();
	sprucely_mwpdn_check_for_theme_updates();
}

/**
 * Checks for plugin updates and sends notifications if updates are available.
 */
function sprucely_mwpdn_check_for_plugin_updates() {
	global $wpdb;

	// Check if cached results exist.
	$cache_key = 'sprucely_mwpdn_plugin_updates';
	$results   = wp_cache_get( $cache_key );

	if ( false === $results ) {
		// Query to get plugin updates from the MainWP database, excluding ignored sites.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT
					plugin_upgrades
				FROM
					{$wpdb->prefix}mainwp_wp wp
				WHERE
					is_ignorePluginUpdates = %d
				",
				0
			)
		);

		// Cache the results for 15 minutes.
		wp_cache_set( $cache_key, $results, '', 300 ); // 300 = 5 minutes.
	}

	if ( empty( $results ) ) {
		return;
	}

	// Retrieve sent notifications from transient.
	$sent_notifications = get_transient( 'sprucely_mwpdn_sent_plugin_notifications' );
	if ( ! is_array( $sent_notifications ) ) {
		$sent_notifications = array();
	}

	$unique_updates = array();
	foreach ( $results as $result ) {
		$plugin_upgrades = json_decode( $result->plugin_upgrades, true );
		if ( is_array( $plugin_upgrades ) ) {
			foreach ( $plugin_upgrades as $plugin_slug => $plugin_info ) {
				if ( isset( $plugin_info['update'] ) && ! empty( $plugin_info['update'] ) ) {
					$update_info = $plugin_info['update'];
					$unique_key  = $plugin_slug . '|' . $update_info['new_version'];
					if ( ! isset( $unique_updates[ $unique_key ] ) && ! isset( $sent_notifications[ $unique_key ] ) ) {
						$unique_updates[ $unique_key ] = array(
							'plugin_name'   => $plugin_info['Name'],
							'new_version'   => $update_info['new_version'],
							'changelog_url' => $update_info['url'],
							'plugin_uri'    => $plugin_info['PluginURI'],
							'thumbnail_url' => sprucely_mwpdn_get_cached_thumbnail_url( $plugin_info['PluginURI'] ),
							'description'   => $plugin_info['Description'] ?? '',
							'author'        => $plugin_info['AuthorName'] ?? '',
							'changelog'     => $update_info['sections']['changelog'] ?? '',
						);
					}
				}
			}
		}
	}

	if ( ! empty( $unique_updates ) ) {
		foreach ( $unique_updates as $key => $update ) {
			if ( sprucely_mwpdn_send_discord_message( $update, 'MAINWP_UPDATES_DISCORD_WEBHOOK_URL' ) ) {
				$sent_notifications[ $key ] = true; // Mark this notification as sent.
			}
			usleep( 500000 ); // Sleep for 0.5 seconds to avoid rate limiting.
		}
		// Store the updated sent notifications.
		set_transient( 'sprucely_mwpdn_sent_plugin_notifications', $sent_notifications, WEEK_IN_SECONDS );
	}
}

/**
 * Checks for theme updates and sends notifications if updates are available.
 */
function sprucely_mwpdn_check_for_theme_updates() {
	global $wpdb;

	// Check if cached results exist.
	$cache_key = 'sprucely_mwpdn_theme_updates';
	$results   = wp_cache_get( $cache_key );

	if ( false === $results ) {
		// Query to get theme updates from the MainWP database, excluding ignored sites.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT
					theme_upgrades
				FROM
					{$wpdb->prefix}mainwp_wp wp
				WHERE
					is_ignoreThemeUpgrades = %d
				",
				0
			)
		);

		// Cache the results for 15 minutes.
		wp_cache_set( $cache_key, $results, '', 300 ); // 300 = 5 minutes.
	}

	if ( empty( $results ) ) {
		return;
	}

	// Retrieve sent notifications from transient.
	$sent_notifications = get_transient( 'sprucely_mwpdn_sent_theme_notifications' );
	if ( ! is_array( $sent_notifications ) ) {
		$sent_notifications = array();
	}

	$unique_updates = array();
	foreach ( $results as $result ) {
		$theme_upgrades = json_decode( $result->theme_upgrades, true );
		if ( is_array( $theme_upgrades ) ) {
			foreach ( $theme_upgrades as $theme_slug => $theme_info ) {
				if ( isset( $theme_info['update'] ) && ! empty( $theme_info['update'] ) ) {
					$update_info = $theme_info['update'];
					$unique_key  = $theme_slug . '|' . $update_info['new_version'];
					if ( ! isset( $unique_updates[ $unique_key ] ) && ! isset( $sent_notifications[ $unique_key ] ) ) {
						$unique_updates[ $unique_key ] = array(
							'theme_name'    => $theme_info['Name'],
							'new_version'   => $update_info['new_version'],
							'changelog_url' => $update_info['url'],
							'theme_uri'     => $update_info['url'],
							'thumbnail_url' => sprucely_mwpdn_get_cached_thumbnail_url( $update_info['url'] ),
							'description'   => $theme_info['Description'] ?? '',
							'author'        => $theme_info['AuthorName'] ?? '',
							'changelog'     => $update_info['sections']['changelog'] ?? '',
						);
					}
				}
			}
		}
	}

	if ( ! empty( $unique_updates ) ) {
		foreach ( $unique_updates as $key => $update ) {
			if ( sprucely_mwpdn_send_discord_message( $update, 'MAINWP_THEME_UPDATES_DISCORD_WEBHOOK_URL' ) ) {
				$sent_notifications[ $key ] = true; // Mark this notification as sent.
			}
			usleep( 500000 ); // Sleep for 0.5 seconds to avoid rate limiting.
		}
		// Store the updated sent notifications.
		set_transient( 'sprucely_mwpdn_sent_theme_notifications', $sent_notifications, WEEK_IN_SECONDS );
	}
}

// Deactivation hook.
register_deactivation_hook( __FILE__, 'sprucely_mwpdn_clear_scheduled_hook' );
/**
 * Clears the scheduled update check hook.
 */
function sprucely_mwpdn_clear_scheduled_hook() {
	wp_clear_scheduled_hook( 'sprucely_mwpdn_check_for_updates' );
}

/**
 * Retrieves the cached thumbnail URL for a given URL.
 *
 * @param string $url The URL of the site to get the thumbnail for.
 * @return string The thumbnail URL.
 */
function sprucely_mwpdn_get_cached_thumbnail_url( $url ) {
	$cache_key     = 'sprucely_mwpdn_thumbnail_url_' . md5( $url );
	$thumbnail_url = get_transient( $cache_key );

	if ( false === $thumbnail_url ) {
		$thumbnail_url = sprucely_mwpdn_get_thumbnail_url( $url );
		set_transient( $cache_key, $thumbnail_url, WEEK_IN_SECONDS );
	}

	return $thumbnail_url;
}

/**
 * Retrieves the thumbnail URL from the site's HTML.
 *
 * @param string $url The URL of the site to get the thumbnail for.
 * @return string The thumbnail URL.
 */
function sprucely_mwpdn_get_thumbnail_url( $url ) {
	$parsed_url = wp_parse_url( $url );
	$base_url   = $parsed_url['scheme'] . '://' . $parsed_url['host'];

	// Fetch the HTML content of the page.
	$response = wp_remote_get( $url );
	if ( is_wp_error( $response ) ) {
		return ''; // Return an empty string if fetching the HTML fails.
	}

	$html = wp_remote_retrieve_body( $response );
	libxml_use_internal_errors( true ); // Handle HTML parsing errors gracefully.
	$dom = new DOMDocument();
	$dom->loadHTML( $html );

	// Search for Open Graph image tags and standard favicon links.
	$meta_tags = $dom->getElementsByTagName( 'meta' );
	foreach ( $meta_tags as $meta ) {
		if ( $meta->getAttribute( 'property' ) === 'og:image' || $meta->getAttribute( 'name' ) === 'og:image' ) {
			return $meta->getAttribute( 'content' );
		}
	}

	$links = $dom->getElementsByTagName( 'link' );
	foreach ( $links as $link ) {
		if ( $link->getAttribute( 'rel' ) === 'icon' || $link->getAttribute( 'rel' ) === 'shortcut icon' ) {
			$favicon_url = $link->getAttribute( 'href' );
			if ( strpos( $favicon_url, 'http' ) === false ) {
				// Handle relative URLs.
				$favicon_url = $base_url . '/' . ltrim( $favicon_url, '/' );
			}
			return $favicon_url;
		}
	}

	return ''; // Return an empty string if no image is found.
}

/**
 * Sends a message to the Discord webhook URL.
 *
 * @param array  $update         The update information.
 * @param string $webhook_url_const The webhook URL constant name.
 * @return bool True if the message was sent successfully, false otherwise.
 */
function sprucely_mwpdn_send_discord_message( $update, $webhook_url_const ) {
	if ( ! defined( $webhook_url_const ) ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'Discord webhook URL not defined.' );
		return false;
	}

	$webhook_url = constant( $webhook_url_const );

	$changelog_summary = '';
	if ( ! empty( $update['changelog'] ) ) {
		$changelog_summary = wp_strip_all_tags( $update['changelog'] );
		$changelog_summary = mb_substr( $changelog_summary, 0, 850 ) . '...';
		$changelog_summary = "**Changelog Summary:** $changelog_summary\n";
	}

	$description = ! empty( $update['description'] ) ? "**Description:** {$update['description']}\n" : '';
	$author      = ! empty( $update['author'] ) ? "**Author:** {$update['author']}\n" : '';

	$embed = array(
		'title'       => $update['plugin_name'] ?? $update['theme_name'],
		'description' => sprintf(
			"**Version %s is available.**\n\n%s%s%s\n\n[View Full Changelog](%s)",
			$update['new_version'],
			$author,
			$description,
			$changelog_summary,
			$update['changelog_url']
		),
		'url'         => $update['plugin_uri'] ?? $update['theme_uri'],
	);

	if ( ! empty( $update['thumbnail_url'] ) ) {
		$embed['thumbnail'] = array(
			'url' => $update['thumbnail_url'],
		);
	}

	$payload = array(
		'content' => '',
		'embeds'  => array( $embed ),
	);

	$args = array(
		'body'        => wp_json_encode( $payload ),
		'headers'     => array( 'Content-Type' => 'application/json' ),
		'method'      => 'POST',
		'data_format' => 'body',
	);

	$response = wp_remote_post( $webhook_url, $args );

	if ( is_wp_error( $response ) ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'Discord Webhook Error: ' . $response->get_error_message() );
		return false;
	} else {
		$response_body = wp_remote_retrieve_body( $response );
		if ( wp_remote_retrieve_response_code( $response ) !== 204 ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Discord Webhook Response: ' . $response_body );
			return false;
		}
		return true;
	}
}
