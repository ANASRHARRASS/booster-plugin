<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://shippingsmile.com/anasrharrass
 * @since      1.0.0
 *
 * @package    Booster
 * @subpackage Booster/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Booster
 * @subpackage Booster/includes
 * @author     anas <anas@shippingsmile.com>
 */
class Booster {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Booster_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'BOOSTER_VERSION' ) ) {
			$this->version = BOOSTER_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'booster';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Booster_Loader. Orchestrates the hooks of the plugin.
	 * - Booster_i18n. Defines internationalization functionality.
	 * - Booster_Admin. Defines all hooks for the admin area.
	 * - Booster_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-booster-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-booster-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 * 
		 */
		
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-booster-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-booster-public.php';
		/**
		 * the class responsoble for news fetcher
		 */
		/**
		 * the class responsible for affiliate manager
		 *
		 */
		// ✅ Make sure these are here:
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-affiliate-manager.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-booster-cron.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-booster-utils.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-booster-ai.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-booster-parser.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-booster-content-manager.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-booster-logger.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-booster-trend-matcher.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-booster-api-runner.php';
		// Load CLI only in CLI context
        if (defined('WP_CLI') && WP_CLI) {
            require_once plugin_dir_path(__FILE__) . 'includes/class-booster-cli.php';
            WP_CLI::add_command('booster:repair-images', 'Booster_CLI');
        }

		/**
		 * the class responsible for cron jobs
		 */

		$this->loader = new Booster_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Booster_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Booster_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_hooks() {
		//admin hooks (pass loader to the admin class)

		new Booster_Admin( $this->plugin_name, $this->version, $this->loader );
		//public hooks 
		$plugin_public = new Booster_Public( $this->plugin_name, $this->version);
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );


		// cron jobs
        $plugin_cron = new Booster_Cron($this->plugin_name, $this->version);
        $this->loader->add_action('init', $plugin_cron, 'init');


	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *

	
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Booster_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
