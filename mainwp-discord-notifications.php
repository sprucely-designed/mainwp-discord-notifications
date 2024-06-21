<?php
/**
 * Plugin Name: MainWP Discord Webhook Notifications
 * Description: Sends a message to a Discord server when a plugin update is available.
 * Version: 1.0
 * Author: Sprucely Designed
 * Author URI: https://www.sprucely.net
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * GitHub: https://github.com/sprucelydesigned/mainwp-discord-notifications
 *
 * @package MainWP Discord Webhook Notifications
 */

add_action( 'mainwp_child_plugin_activated', 'sprucely_setup_plugin_update_hook' );
add_action( 'mainwp_cronupdatescheck_action', 'sprucely_check_for_plugin_updates' );

function sprucely_setup_plugin_update_hook() {
	if ( ! wp_next_scheduled( 'sprucely_check_for_plugin_updates' ) ) {
		wp_schedule_event( time(), 'hourly', 'sprucely_check_for_plugin_updates' );
	}
}

function sprucely_check_for_plugin_updates() {
	global $wpdb;

	// Check if cached results exist.
	$cache_key = 'sprucely_plugin_updates';
	$results   = wp_cache_get( $cache_key );

	if ( false === $results ) {
		// Query to get plugin updates from the MainWP database, excluding ignored sites.
		$sql = "
			SELECT
				wp.plugin_upgrades
			FROM
				{$wpdb->prefix}mainwp_wp wp
			WHERE
				wp.is_ignorePluginUpdates = 0
		";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_results( $sql );

		// Cache the results for 15 minutes.
		wp_cache_set( $cache_key, $results, '', 300 ); // 300 = 5 minutes.
	}

	if ( empty( $results ) ) {
		return;
	}

	// Retrieve sent notifications from transient.
	$sent_notifications = get_transient( 'sprucely_sent_notifications' );
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
							'thumbnail_url' => sprucely_get_cached_thumbnail_url( $plugin_info['PluginURI'] ),
							'description'   => $plugin_info['Description'],
							'author'        => $plugin_info['AuthorName'],
							'changelog'     => $update_info['sections']['changelog'] ?? '',
						);
					}
				}
			}
		}
	}

	if ( ! empty( $unique_updates ) ) {
		foreach ( $unique_updates as $key => $update ) {
			if ( sprucely_send_discord_message( $update ) ) {
				$sent_notifications[ $key ] = true; // Mark this notification as sent.
			}
			usleep( 500000 ); // Sleep for 0.5 seconds to avoid rate limiting.
		}
		// Store the updated sent notifications.
		set_transient( 'sprucely_sent_notifications', $sent_notifications, WEEK_IN_SECONDS );
	}
}

register_deactivation_hook( __FILE__, 'sprucely_clear_scheduled_hook' );
function sprucely_clear_scheduled_hook() {
	wp_clear_scheduled_hook( 'sprucely_check_for_plugin_updates' );
}

function sprucely_get_cached_thumbnail_url( $url ) {
	$cache_key     = 'sprucely_thumbnail_url_' . md5( $url );
	$thumbnail_url = get_transient( $cache_key );

	if ( false === $thumbnail_url ) {
		$thumbnail_url = sprucely_get_thumbnail_url( $url );
		set_transient( $cache_key, $thumbnail_url, WEEK_IN_SECONDS );
	}

	return $thumbnail_url;
}

function sprucely_get_thumbnail_url( $url ) {
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

function sprucely_send_discord_message( $update ) {
	if ( ! defined( 'MAINWP_UPDATES_DISCORD_WEBHOOK_URL' ) ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'Discord webhook URL not defined.' );
		return false;
	}
	$webhook_url = MAINWP_UPDATES_DISCORD_WEBHOOK_URL;

	// Extract a summary of the changelog (first 800 characters)
	$changelog_summary = wp_strip_all_tags( $update['changelog'] );
	$changelog_summary = mb_substr( $changelog_summary, 0, 800 ) . '...';

	$embed = array(
		'title'       => $update['plugin_name'],
		'description' => sprintf(
			"**Version %s is available.**\n\n**Author:** %s\n**Description:** %s\n\n**Changelog Summary:** %s\n\n[View Full Changelog](%s)",
			$update['new_version'],
			$update['author'],
			$update['description'],
			$changelog_summary,
			$update['changelog_url']
		),
		'url'         => $update['plugin_uri'],
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
