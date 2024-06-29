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
								'thumbnail_url' => Helpers::get_cached_thumbnail_url( $plugin_info['PluginURI'] ),
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
				if ( Helpers::send_discord_message( $update, $this->webhook_urls['plugin_updates'] ) ) {
					$sent_notifications[ $key ] = true;
				}
				usleep( 500000 );
			}

			set_transient( 'sprucely_mwpdn_sent_plugin_notifications', $sent_notifications, WEEK_IN_SECONDS );
		}
	}
}
