<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://shippingsmile.com/anasrharrass
 * @since             1.0.0
 * @package           Booster
 *
 * @wordpress-plugin
 * Plugin Name:       Booster
 * Plugin URI:        https://shippingsmile.com/plugin
 * Description:       Automatically fetch news, rewrite content, and insert affiliate links for traffic growth.
 * Version:           1.0.0
 * Author:            anas
 * Author URI:        https://shippingsmile.com/anasrharrass/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       booster
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'BOOSTER_VERSION', '1.0.0' );



/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-booster-activator.php
 */
function activate_booster() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-booster-activator.php';
	Booster_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-booster-deactivator.php
 */
function deactivate_booster() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-booster-deactivator.php';
	Booster_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_booster' );
register_deactivation_hook( __FILE__, 'deactivate_booster' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-booster.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
// update the activation check :
//add_action('admin_init', 'booster_check_dependencies');
// function booster_check_dependencies() {
//     if (!function_exists('is_plugin_active') ){
// 		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	
//         add_action('admin_notices', function() {
//             echo '<div class="notice notice-error"><p>Booster requires <strong>WPGetAPI</strong>. <a href="' . esc_url(admin_url('plugin-install.php?s=wpgetapi&tab=search&type=term')) . '">Install it now</a>.</p></div>';
//         });
// 		//Deactivate the plugin if dependency missing
// 		deactivate_plugins(plugin_basename(__FILE__));
//     }
// }
// check wpgetapi activation during plugin INIT INSTEAD of admin_init
add_action('init', function(){
	// make sure is_plugin_active is available
	if (!function_exists('is_plugin_active') ){
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if (!is_plugin_active('wpgetapi/wpgetapi.php')){
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		deactivate_plugins(plugin_basename(__FILE__));
		add_action('admin_notices', function() {
			sprintf(__('<div class="notice notice-error"><p>Booster requires <strong>WPGetAPI</strong>. <a href="%s">Install it now</a>.</p></div>', 'booster'), esc_url(admin_url('plugin-install.php?s=wpgetapi&tab=search&type=term')));
		});
	}
});

function run_booster() {

	$plugin = new Booster();
	$plugin->run();

}
run_booster();
