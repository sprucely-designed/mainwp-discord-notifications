<?php
/**
 * Plugin Name: Discord Webhook Notifications for MainWP
 * Description: Sends a message via webhook to a Discord server channel when a plugin or theme update is available.
 * Version: 1.3.0-beta.1
 * Author: Isaac @ Sprucely Designed
 * Author URI: https://www.sprucely.net
 * Plugin URI: https://github.com/sprucely-designed/mainwp-discord-notifications
 *
 * RequiresPlugins: mainwp
 *
 * License: GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * GitHub Plugin URI: https://github.com/sprucely-designed/mainwp-discord-notifications
 * Primary Branch: main
 *
 * @package Sprucely_MainWP_Discord
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Define plugin version.
 */
define( 'MAINWP_DISCORD_VERSION', 'dev-1.3.0-beta.1' );

/**
 * Define plugin directory path.
 */
define( 'MAINWP_DISCORD_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Include the core class responsible for setting up the plugin.
 */
require_once MAINWP_DISCORD_DIR . 'includes/class-mwpdn-main.php';

// Run the plugin.
Sprucely\MainWP_Discord\Main::get_instance();
