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
	 * Get the webhook URL for the specified type.
	 *
	 * @param string $type The type of update (plugin_updates or theme_updates).
	 * @return string The webhook URL for the specified type.
	 */
	public function get_webhook_url( $type ) {
		return isset( $this->webhook_urls[ $type ] ) ? $this->webhook_urls[ $type ] : '';
	}
}
