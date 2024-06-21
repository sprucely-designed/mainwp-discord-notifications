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

	// Query to get plugin updates from the MainWP database, excluding ignored sites.
	$sql = "
        SELECT
            wp.plugin_upgrades
        FROM
            {$wpdb->prefix}mainwp_wp wp
        WHERE
            wp.is_ignorePluginUpdates = 0
    ";

	$results = $wpdb->get_results( $sql );

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
						);
					}
				}
			}
		}
	}

	if ( ! empty( $unique_updates ) ) {
		foreach ( $unique_updates as $key => $update ) {
			$message = sprintf(
				'Update available for %s: Version %s - Changelog: %s',
				$update['plugin_name'],
				$update['new_version'],
				$update['changelog_url']
			);
			if ( sprucely_send_discord_message( $message ) ) {
				$sent_notifications[ $key ] = true; // Mark this notification as sent.
			}
			usleep( 500000 ); // Sleep for 0.5 seconds to avoid rate limiting.
		}
		// Store the updated sent notifications.
		set_transient( 'sprucely_sent_notifications', $sent_notifications, 12 * HOUR_IN_SECONDS );
	}
}

register_deactivation_hook( __FILE__, 'sprucely_clear_scheduled_hook' );
function sprucely_clear_scheduled_hook() {
	wp_clear_scheduled_hook( 'sprucely_check_for_plugin_updates' );
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'mainwp-notify-discord', 'mainwp_notify_discord_updates' );
}

function mainwp_notify_discord_updates() {
	sprucely_check_for_plugin_updates();
}

function sprucely_send_discord_message( $message ) {
	if ( ! defined( 'MAINWP_UPDATES_DISCORD_WEBHOOK_URL' ) ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		error_log( 'Discord webhook URL not defined.' );
		return false;
	}
	$webhook_url = MAINWP_UPDATES_DISCORD_WEBHOOK_URL;

	$payload = array(
		'content' => $message,
	);

	$args = array(
		'body'        => wp_json_encode( $payload ),
		'headers'     => array( 'Content-Type' => 'application/json' ),
		'method'      => 'POST',
		'data_format' => 'body',
	);

	$response = wp_remote_post( $webhook_url, $args );

	if ( is_wp_error( $response ) ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		error_log( 'Discord Webhook Error: ' . $response->get_error_message() );
		return false;
	} else {
		$response_body = wp_remote_retrieve_body( $response );
		if ( wp_remote_retrieve_response_code( $response ) !== 204 ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions
			error_log( 'Discord Webhook Response: ' . $response_body );
			return false;
		}
		return true;
	}
}
