<?php
/**
 * Handles plugin updates for Discord Webhook Notifications for MainWP.
 *
 * @package Sprucely_MainWP_Discord
 */

namespace Sprucely\MainWP_Discord;

/**
 * Class Plugin_Updates
 */
class Plugin_Updates {

	/**
	 * Singleton instance of the class.
	 *
	 * @var Plugin_Updates|null
	 */
	private static $instance = null;

	/**
	 * Webhook URLs.
	 *
	 * @var array
	 */
	private $webhook_urls;

	/**
	 * Constructor.
	 *
	 * Sets up the plugin update hooks.
	 */
	private function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Get the singleton instance of the class.
	 *
	 * @return Plugin_Updates
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Set the webhook URLs.
	 *
	 * @param array $webhook_urls The webhook URLs.
	 */
	public function set_webhook_urls( $webhook_urls ) {
		$this->webhook_urls = $webhook_urls;
	}

	/**
	 * Setup hooks and filters.
	 */
	private function setup_hooks() {
		add_action( 'mainwp_child_plugin_activated', array( $this, 'setup_plugin_update_hook' ) );
		add_action( 'mainwp_cronupdatescheck_action', array( $this, 'check_for_plugin_updates' ) );
		// Ensure our scheduled hook runs the checker.
		add_action( 'sprucely_mwpdn_check_for_plugin_updates', array( $this, 'check_for_plugin_updates' ) );
	}

	/**
	 * Setup the scheduled event for checking updates.
	 */
	public function setup_plugin_update_hook() {
		if ( ! wp_next_scheduled( 'sprucely_mwpdn_check_for_plugin_updates' ) ) {
			wp_schedule_event( time(), 'hourly', 'sprucely_mwpdn_check_for_plugin_updates' );
		}
	}

	/**
	 * Check for plugin updates and send notifications if updates are available.
	 */
	public function check_for_plugin_updates() {
		global $wpdb;

		if ( empty( $this->webhook_urls['plugin_updates'] ) ) {
			return;
		}

		$results = Helpers::query_mainwp_db(
			'plugin',
			$wpdb->prefix . 'mainwp_wp',
			'plugin_upgrades',
			'is_ignorePluginUpdates'
		);

		if ( empty( $results ) ) {
			return;
		}

		foreach ( $results as $result ) {
			$plugin_upgrades = json_decode( $result->plugin_upgrades, true );
			if ( is_array( $plugin_upgrades ) ) {
				foreach ( $plugin_upgrades as $plugin_slug => $plugin_info ) {
					if ( isset( $plugin_info['update'] ) && ! empty( $plugin_info['update'] ) ) {
						$update_info = $plugin_info['update'];

						// Check if we already sent notification for this version
						if ( Helpers::is_notification_sent( 'plugin', $plugin_slug, $update_info['new_version'] ) ) {
							continue;
						}

						$update_data = array(
							'plugin_name'   => $plugin_info['Name'],
							'new_version'   => $update_info['new_version'],
							'changelog_url' => $update_info['url'] ?? '',
							'plugin_uri'    => $plugin_info['PluginURI'] ?? '',
							'thumbnail_url' => Helpers::get_cached_thumbnail_url( $plugin_info['PluginURI'] ),
							'description'   => $plugin_info['Description'] ?? '',
							'author'        => $plugin_info['AuthorName'] ?? '',
							'changelog'     => $update_info['sections']['changelog'] ?? '',
						);

						// Send Discord notification
						if ( Helpers::send_discord_message( $update_data, $this->webhook_urls['plugin_updates'] ) ) {
							// Mark as sent only if Discord message was successful
							Helpers::mark_notification_sent( 'plugin', $plugin_slug, $update_info['new_version'] );
						}

						usleep( 500000 ); // Sleep to avoid rate limiting
					}
				}
			}
		}
	}
}
