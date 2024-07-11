<?php
/**
 * Plugin Name: Discord Webhook Notifications for MainWP
 * Description: Sends a message via webhook to a Discord server channel when a plugin or theme update is available.
 * Version: 1.1.5
 * Author: Isaac @ Sprucely Designed
 * Author URI: https://www.sprucely.net
 * Plugin URI: https://github.com/sprucely-designed/mainwp-discord-notifications
 * License: GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * GitHub Plugin URI: sprucely-designed/mainwp-discord-notifications
 * Primary Branch: main
 *
 * @package Sprucely_MWP_Discord
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Global variable to hold webhook URLs.
global $sprucely_mwpdn_webhook_urls;
$sprucely_mwpdn_webhook_urls = array(
	'plugin_updates' => '',
	'theme_updates'  => '',
);

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
	sprucely_mwpdn_set_webhook_urls();
	sprucely_mwpdn_check_for_plugin_updates();
	sprucely_mwpdn_check_for_theme_updates();
}

/**
 * Sets the webhook URLs if the constants are defined.
 */
function sprucely_mwpdn_set_webhook_urls() {
	global $sprucely_mwpdn_webhook_urls;

	// Check if the PLUGIN updates constant is defined.
	if ( defined( 'MAINWP_PLUGIN_UPDATES_DISCORD_WEBHOOK_URL' ) ) {
		$sprucely_mwpdn_webhook_urls['plugin_updates'] = MAINWP_PLUGIN_UPDATES_DISCORD_WEBHOOK_URL;
	} elseif ( defined( 'MAINWP_UPDATES_DISCORD_WEBHOOK_URL' ) ) {
		// Fallback to legacy constant if the latest one is not defined.
		$sprucely_mwpdn_webhook_urls['plugin_updates'] = MAINWP_UPDATES_DISCORD_WEBHOOK_URL;
	}

	// Check if the THEME updates constant is defined.
	if ( defined( 'MAINWP_THEME_UPDATES_DISCORD_WEBHOOK_URL' ) ) {
		$sprucely_mwpdn_webhook_urls['theme_updates'] = MAINWP_THEME_UPDATES_DISCORD_WEBHOOK_URL;
	}
}

/**
 * Checks for plugin updates and sends notifications if updates are available.
 */
function sprucely_mwpdn_check_for_plugin_updates() {
	global $sprucely_mwpdn_webhook_urls;

	// Check if the required webhook URL is set, if not return.
	if ( empty( $sprucely_mwpdn_webhook_urls['plugin_updates'] ) ) {
		return;
	}

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
							'changelog_url' => $update_info['url'] ?? '',
							'plugin_uri'    => $plugin_info['PluginURI'] ?? '',
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
			if ( sprucely_mwpdn_send_discord_message( $update, 'plugin_updates' ) ) {
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
	global $sprucely_mwpdn_webhook_urls;

	// Check if the required webhook URL is set, if not return.
	if ( empty( $sprucely_mwpdn_webhook_urls['theme_updates'] ) ) {
		return;
	}

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
                    is_ignoreThemeUpdates = %d
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
							'changelog_url' => $update_info['url'] ?? '',
							'theme_uri'     => $update_info['url'] ?? '',
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
			if ( sprucely_mwpdn_send_discord_message( $update, 'theme_updates' ) ) {
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
 * Converts HTML to Discord-supported Markdown.
 *
 * @param string $html The HTML content.
 * @return string The Markdown content.
 */
function sprucely_mwpdn_convert_html_to_markdown( $html ) {
	// Convert common HTML tags to Markdown.
	$markdown = $html;
	$markdown = preg_replace( '/<strong>(.*?)<\/strong>/', '**$1**', $markdown );
	$markdown = preg_replace( '/<b>(.*?)<\/b>/', '**$1**', $markdown );
	$markdown = preg_replace( '/<em>(.*?)<\/em>/', '*$1*', $markdown );
	$markdown = preg_replace( '/<i>(.*?)<\/i>/', '*$1*', $markdown );
	$markdown = preg_replace( '/<code>(.*?)<\/code>/', '`$1`', $markdown );
	$markdown = preg_replace( '/<a(.*?)href="(.*?)"(.*?)>(.*?)<\/a>/', '[$4]($2)', $markdown );

	// Convert heading tags to Markdown.
	$markdown = preg_replace( '/<h1>(.*?)<\/h1>/', '# $1', $markdown );
	$markdown = preg_replace( '/<h2>(.*?)<\/h2>/', '## $1', $markdown );
	$markdown = preg_replace( '/<h3>(.*?)<\/h3>/', '### $1', $markdown );
	$markdown = preg_replace( '/<h4>(.*?)<\/h4>/', '#### $1', $markdown );
	$markdown = preg_replace( '/<h5>(.*?)<\/h5>/', '##### $1', $markdown );
	$markdown = preg_replace( '/<h6>(.*?)<\/h6>/', '###### $1', $markdown );

	// Convert list tags to Markdown.
	$markdown = preg_replace( '/<ul>/', "\n", $markdown );
	$markdown = preg_replace( '/<\/ul>/', "\n", $markdown );
	$markdown = preg_replace( '/<ol>/', "\n", $markdown );
	$markdown = preg_replace( '/<\/ol>/', '', $markdown );
	$markdown = preg_replace( '/<li>/', '- ', $markdown );
	$markdown = preg_replace( '/<\/li>/', '', $markdown );

	// Remove any remaining HTML tags.
	// $markdown = wp_strip_all_tags( $markdown ); // Skip for debugging new potential tags.

	return $markdown;
}

/**
 * Sends a message to the Discord webhook URL.
 *
 * @param array  $update            The update information.
 * @param string $webhook_url_type The webhook URL type (plugin_updates or theme_updates).
 * @return bool True if the message was sent successfully, false otherwise.
 */
function sprucely_mwpdn_send_discord_message( $update, $webhook_url_type ) {
	global $sprucely_mwpdn_webhook_urls;

	// Check if the required webhook URL is set, if not return false.
	if ( empty( $sprucely_mwpdn_webhook_urls[ $webhook_url_type ] ) ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'Discord webhook URL not defined.' );
		return false;
	}

	$webhook_url = $sprucely_mwpdn_webhook_urls[ $webhook_url_type ];

	// Build the changelog summary if available.
	$changelog_summary = '';
	if ( ! empty( $update['changelog'] ) ) {
		$changelog_summary = sprucely_mwpdn_convert_html_to_markdown( $update['changelog'] );
		$changelog_summary = mb_substr( $changelog_summary, 0, 850 ) . '...';
		$changelog_summary = "**Changelog Summary:** $changelog_summary\n";
	}

	// Append the anchor tag for the correct changelog tab for plugins on wp.org.
	if ( ! empty( $update['changelog_url'] ) && ( false !== strpos( $update['changelog_url'], 'wordpress.org/plugins' ) ) ) {
		// Ensure the URL ends with a trailing slash then append the anchor.
		$update['changelog_url'] = trailingslashit( $update['changelog_url'] ) . '#developers';
	}

	// Build the description parts if available.
	$description   = ! empty( $update['description'] ) ? '**Description:** ' . sprucely_mwpdn_convert_html_to_markdown( $update['description'] ) . "\n" : '';
	$author        = ! empty( $update['author'] ) ? '**Author:** ' . sprucely_mwpdn_convert_html_to_markdown( $update['author'] ) . "\n" : '';
	$changelog_url = ! empty( $update['changelog_url'] ) ? "[View Full Changelog]({$update['changelog_url']})" : '';

	// Combine all parts of the description.
	$embed_description  = "**Version {$update['new_version']} is available.**\n\n";
	$embed_description .= $author;
	$embed_description .= $description;
	$embed_description .= $changelog_summary;
	$embed_description .= $changelog_url ? "\n\n{$changelog_url}" : '';

	// Build the embed array.
	$embed = array(
		'title'       => $update['plugin_name'] ?? $update['theme_name'],
		'description' => $embed_description,
	);

	if ( ! empty( $update['plugin_uri'] ) || ! empty( $update['theme_uri'] ) ) {
		$embed['url'] = $update['plugin_uri'] ?? $update['theme_uri'];
	}

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

/**
 * Add a "Support" link to the plugin meta links.
 *
 * @param array  $links The existing plugin meta links.
 * @param string $file  The plugin file.
 * @return array The modified plugin meta links.
 */
function sprucely_mwpdn_add_support_meta_link( $links, $file ) {
	if ( plugin_basename( __FILE__ ) === $file ) {
		$support_link = '<a href="https://github.com/sprucely-designed/mainwp-discord-notifications/issues">' . __( 'Support', 'mainwp-discord-webhook-notifications' ) . '</a>';
		$links[]      = $support_link;
	}
	return $links;
}

add_filter( 'plugin_row_meta', 'sprucely_mwpdn_add_support_meta_link', 10, 2 );
