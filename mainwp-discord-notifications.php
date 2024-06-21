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

// Hook into the MainWP update check.
add_action( 'mainwp_child_plugin_activated', 'sprucely_setup_plugin_update_hook' );
add_action( 'mainwp_cronupdatescheck_action', 'sprucely_check_for_plugin_updates' );

/**
 * Set up the hook to check for plugin updates.
 */
function sprucely_setup_plugin_update_hook() {
	if ( ! wp_next_scheduled( 'sprucely_check_for_plugin_updates' ) ) {
		wp_schedule_event( time(), 'hourly', 'sprucely_check_for_plugin_updates' );
	}
}

/**
 * Check for plugin updates and send a message to Discord if an update is available.
 */
function sprucely_check_for_plugin_updates() {
	// Get all the plugins that have updates available.
	$plugins = get_site_transient( 'update_plugins' );

	if ( ! empty( $plugins->response ) ) {
		foreach ( $plugins->response as $plugin_slug => $plugin_info ) {
			$plugin_data   = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_slug );
			$plugin_name   = $plugin_data['Name'];
			$new_version   = $plugin_info->new_version;
			$changelog_url = $plugin_info->url;

			// Send message to Discord.
			sprucely_send_discord_message( $plugin_name, $new_version, $changelog_url );
		}
	}
}

/**
 * Send a message to Discord.
 *
 * @param string $plugin_name The name of the plugin.
 * @param string $new_version The new version of the plugin.
 * @param string $changelog_url The URL to the plugin's changelog.
 */
function sprucely_send_discord_message( $plugin_name, $new_version, $changelog_url ) {
	if ( ! defined( 'MAINWP_UPDATES_DISCORD_WEBHOOK_URL' ) ) {
		return;
	}
	$webhook_url = MAINWP_UPDATES_DISCORD_WEBHOOK_URL; // Store your Discord webhook URL in a constant in wp-config.php.

	$message = array(
		'content' => "Plugin Update Available: **$plugin_name**\nVersion: **$new_version**\n[Changelog]($changelog_url)",
	);

	$args = array(
		'body'        => wp_json_encode( $message ),
		'headers'     => array( 'Content-Type' => 'application/json' ),
		'method'      => 'POST',
		'data_format' => 'body',
	);

	$response = wp_remote_post( $webhook_url, $args );

	// Log the response for debugging purposes.
	if ( is_wp_error( $response ) ) {
		error_log( 'Discord Webhook Error: ' . $response->get_error_message() );
	} else {
		error_log( 'Discord Webhook Response: ' . wp_remote_retrieve_body( $response ) );
	}
}

// Register the hook to clear the scheduled event.
register_deactivation_hook( __FILE__, 'sprucely_clear_scheduled_hook' );
/**
 * Clear the scheduled event.
 */
function sprucely_clear_scheduled_hook() {
	wp_clear_scheduled_hook( 'sprucely_check_for_plugin_updates' );
}
