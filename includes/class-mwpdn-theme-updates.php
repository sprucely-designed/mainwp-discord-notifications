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
		// Ensure our scheduled hook runs the checker.
		add_action( 'sprucely_mwpdn_check_for_theme_updates', array( $this, 'check_for_theme_updates' ) );
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
		/** @var \wpdb|null $wpdb */

		if ( empty( $this->webhook_urls['theme_updates'] ) || ! $wpdb instanceof \wpdb ) {
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

		foreach ( $results as $result ) {
			$theme_upgrades = json_decode( $result->theme_upgrades, true );
			if ( is_array( $theme_upgrades ) ) {
				foreach ( $theme_upgrades as $theme_slug => $theme_info ) {
					if ( ! is_scalar( $theme_slug ) || ! is_array( $theme_info ) || empty( $theme_info['update'] ) || ! is_array( $theme_info['update'] ) ) {
						continue;
					}

					$theme_slug   = (string) $theme_slug;
					$update_info  = $theme_info['update'];
					$new_version  = is_scalar( $update_info['new_version'] ?? null ) ? trim( (string) $update_info['new_version'] ) : '';
					$theme_name   = is_scalar( $theme_info['Name'] ?? null ) ? trim( (string) $theme_info['Name'] ) : '';
					$theme_uri    = Helpers::sanitize_remote_url( $update_info['url'] ?? '' );

					if ( '' === $new_version || '' === $theme_name ) {
						continue;
					}

					// Check if we already sent notification for this version
					if ( Helpers::is_notification_sent( 'theme', $theme_slug, $new_version ) ) {
						continue;
					}

					$update_data = array(
						'theme_name'    => $theme_name,
						'new_version'   => $new_version,
						'changelog_url' => $theme_uri,
						'theme_uri'     => $theme_uri,
						'thumbnail_url' => Helpers::get_cached_thumbnail_url( $theme_uri ),
						'description'   => is_scalar( $theme_info['Description'] ?? null ) ? (string) $theme_info['Description'] : '',
						'author'        => is_scalar( $theme_info['AuthorName'] ?? null ) ? (string) $theme_info['AuthorName'] : '',
						'changelog'     => is_scalar( $update_info['sections']['changelog'] ?? null ) ? (string) $update_info['sections']['changelog'] : '',
					);

					// Send Discord notification
					if ( Helpers::send_discord_message( $update_data, $this->webhook_urls['theme_updates'] ) ) {
						// Mark as sent only if Discord message was successful
						Helpers::mark_notification_sent( 'theme', $theme_slug, $new_version );
					}

					usleep( 500000 ); // Sleep to avoid rate limiting
				}
			}
		}
	}
}
