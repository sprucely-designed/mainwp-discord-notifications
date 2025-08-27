<?php
/**
 * The main plugin class.
 *
 * @package Sprucely_MainWP_Discord
 */

namespace Sprucely\MainWP_Discord;

use Sprucely\MainWP_Discord\Plugin_Updates;
use Sprucely\MainWP_Discord\Theme_Updates;
use Sprucely\MainWP_Discord\Helpers;

/**
 * Main plugin class for Sprucely_MainWP_Discord.
 */
class Main {

	/**
	 * Singleton instance of the class.
	 *
	 * @var Main|null
	 */
	private static $instance = null;

	/**
	 * Webhook URLs.
	 *
	 * @var array
	 */
	private $webhook_urls = array();

	/**
	 * Constructor.
	 *
	 * Sets up the plugin by setting webhook URLs, loading dependencies, and initializing classes.
	 */
	private function __construct() {
		$this->set_webhook_urls();
		$this->load_dependencies();
		$this->initialize_classes();
		$this->setup_hooks();

		// Register deactivation hook
		register_deactivation_hook( MAINWP_DISCORD_DIR . 'mainwp-discord-notifications.php', array( $this, 'deactivate' ) );
	}

	/**
	 * Get the singleton instance of the class.
	 *
	 * @return Main
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Set the webhook URLs if the constants are defined.
	 */
	private function set_webhook_urls() {
		if ( defined( 'MAINWP_PLUGIN_UPDATES_DISCORD_WEBHOOK_URL' ) ) {
			$this->webhook_urls['plugin_updates'] = MAINWP_PLUGIN_UPDATES_DISCORD_WEBHOOK_URL;
		} elseif ( defined( 'MAINWP_UPDATES_DISCORD_WEBHOOK_URL' ) ) {
			$this->webhook_urls['plugin_updates'] = MAINWP_UPDATES_DISCORD_WEBHOOK_URL;
		}
		if ( defined( 'MAINWP_THEME_UPDATES_DISCORD_WEBHOOK_URL' ) ) {
			$this->webhook_urls['theme_updates'] = MAINWP_THEME_UPDATES_DISCORD_WEBHOOK_URL;
		}
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {
		require_once MAINWP_DISCORD_DIR . 'includes/class-mwpdn-plugin-updates.php';
		require_once MAINWP_DISCORD_DIR . 'includes/class-mwpdn-theme-updates.php';
		require_once MAINWP_DISCORD_DIR . 'includes/class-mwpdn-helpers.php';
	}

	/**
	 * Initialize the classes for handling plugin and theme updates.
	 */
	private function initialize_classes() {
		Plugin_Updates::get_instance()->set_webhook_urls( $this->webhook_urls );
		Theme_Updates::get_instance()->set_webhook_urls( $this->webhook_urls );
	}

	/**
	 * Setup hooks and filters.
	 */
	private function setup_hooks() {
		add_filter( 'plugin_row_meta', array( $this, 'add_support_meta_link' ), 10, 2 );
	}

	/**
	 * Add support link to plugin meta row.
	 *
	 * @param array  $links An array of the plugin's metadata.
	 * @param string $file  Path to the plugin file relative to the plugins directory.
	 * @return array Modified array of plugin metadata.
	 */
	public function add_support_meta_link( $links, $file ) {
		if ( plugin_basename( MAINWP_DISCORD_DIR . 'mainwp-discord-notifications.php' ) === $file ) {
			$support_link = '<a href="https://github.com/sprucely-designed/mainwp-discord-notifications/issues">' . __( 'Support', 'mainwp-discord-webhook-notifications' ) . '</a>';
			$links[] = $support_link;
		}
		return $links;
	}

	/**
	 * Plugin deactivation cleanup.
	 *
	 * Clears scheduled hooks and options when plugin is deactivated.
	 */
	public function deactivate() {
		// Clear any scheduled hooks
		wp_clear_scheduled_hook( 'sprucely_mwpdn_check_for_plugin_updates' );
		wp_clear_scheduled_hook( 'sprucely_mwpdn_check_for_theme_updates' );

		// Clear plugin-related options
		$this->clear_plugin_options();
	}

	/**
	 * Clear all plugin-related options.
	 */
	private function clear_plugin_options() {
		global $wpdb;

		// Remove the notification storage options
		delete_option( 'sprucely_mwpdn_sent_plugin_notifications' );
		delete_option( 'sprucely_mwpdn_sent_theme_notifications' );

		// Clear any remaining transients (if any exist)
		if ( isset( $wpdb ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					'_transient_sprucely_mwpdn_%',
					'_transient_timeout_sprucely_mwpdn_%'
				)
			);
		}
	}

	/**
	 * Get the webhook URL for the specified type.
	 *
	 * @param string $type The type of update (plugin_updates or theme_updates).
	 * @return string The webhook URL for the specified type.
	 */
	public function get_webhook_url( $type ) {
		return isset( $this->webhook_urls[ $type ] ) ? $this->webhook_urls[ $type ] : '';
	}
}
