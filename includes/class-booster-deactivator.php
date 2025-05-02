<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://shippingsmile.com/anasrharrass
 * @since      1.0.0
 *
 * @package    Booster
 * @subpackage Booster/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Booster
 * @subpackage Booster/includes
 * @author     anas <anas@shippingsmile.com>
 */
class Booster_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		// Clear cron events
		wp_clear_scheduled_hook('booster_content_cron');

	}

}
