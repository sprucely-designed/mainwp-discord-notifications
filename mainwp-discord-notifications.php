<?php
/**
 * Plugin Name: Discord Webhook Notifications for MainWP
 * Description: Sends a message via webhook to a Discord server channel when a plugin or theme update is available.
 * Version: 1.2.0
 * Author: Isaac @ Sprucely Designed
 * Author URI: https://www.sprucely.net
 * Plugin URI: https://github.com/sprucely-designed/mainwp-discord-notifications
 * License: GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * GitHub Plugin URI: https://github.com/sprucely-designed/mainwp-discord-notifications
 * Primary Branch: main
 *
 * @package Sprucely_MWP_Discord
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Define plugin version.
 */
define( 'SPRUCELY_MWPDN_VERSION', '1.2.0' );

/**
 * Define plugin directory path.
 */
define( 'SPRUCELY_MWPDN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Include the core class responsible for setting up the plugin.
 */
require_once SPRUCELY_MWPDN_DIR . 'includes/class-main.php';

// Run the plugin.
Sprucely_MWPDN::get_instance();
