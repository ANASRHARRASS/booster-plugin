<?php

/**
 * Fired during plugin activation
 *
 * @link       https://shippingsmile.com/anasrharrass
 * @since      1.0.0
 *
 * @package    Booster
 * @subpackage Booster/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Booster
 * @subpackage Booster/includes
 * @author     anas <anas@shippingsmile.com>
 */
class Booster_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
        // Set default cron if not set
        if (!wp_next_scheduled('booster_content_cron')) {
            wp_schedule_event(time(), 'hourly', 'booster_content_cron');
        }

        // Optionally set default provider list (just in case)
        if (!get_option('booster_provider_list')) {
            update_option('booster_provider_list', []);
        }
    }

}
