<?php
/**
 * Handles theme updates for Discord Webhook Notifications for MainWP.
 *
 * @package Sprucely_MainWP_Discord
 */

namespace Sprucely\MainWP_Discord;

/**
 * Class Theme_Updates
 */
class Theme_Updates {

	/**
	 * Singleton instance of the class.
	 *
	 * @var Theme_Updates|null
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
	 * Sets up the theme update hooks.
	 */
	private function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Get the singleton instance of the class.
	 *
	 * @return Theme_Updates
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
		add_action( 'mainwp_child_plugin_activated', array( $this, 'setup_theme_update_hook' ) );
		add_action( 'mainwp_cronupdatescheck_action', array( $this, 'check_for_theme_updates' ) );
	}

	/**
	 * Setup the scheduled event for checking updates.
	 */
	public function setup_theme_update_hook() {
		if ( ! wp_next_scheduled( 'sprucely_mwpdn_check_for_theme_updates' ) ) {
			wp_schedule_event( time(), 'hourly', 'sprucely_mwpdn_check_for_theme_updates' );
		}
	}

	/**
	 * Check for theme updates and send notifications if updates are available.
	 */
	public function check_for_theme_updates() {
		global $wpdb;

		if ( empty( $this->webhook_urls['theme_updates'] ) ) {
			return;
		}

		$results = Helpers::query_mainwp_db(
			'theme',
			$wpdb->prefix . 'mainwp_wp',
			'theme_upgrades',
			'is_ignoreThemeUpdates'
		);

		if ( empty( $results ) ) {
			return;
		}

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
								'thumbnail_url' => Helpers::get_cached_thumbnail_url( $update_info['url'] ),
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
				if ( Helpers::send_discord_message( $update, $this->webhook_urls['theme_updates'] ) ) {
					$sent_notifications[ $key ] = true;
				}
				usleep( 500000 );
			}

			set_transient( 'sprucely_mwpdn_sent_theme_notifications', $sent_notifications, WEEK_IN_SECONDS );
		}
	}
}
