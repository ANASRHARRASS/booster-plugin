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

declare(strict_types=1); // Added

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
	protected Booster_Loader $loader; // PHP 7.4+ typed property

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected string $plugin_name; // PHP 7.4+ typed property

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected string $version; // PHP 7.4+ typed property

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
		if ( defined( 'BOOSTER_VERSION' ) ) { // Check type of constant
			$this->version = BOOSTER_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'booster';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_hooks(); // define_hooks should be called after loader is initialized

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
	private function load_dependencies(): void { // Added void return type

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
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-booster-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-booster-public.php';

		// Other dependencies
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-booster-logger.php'; // Load Logger first if others depend on it
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-affiliate-manager.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-booster-cron.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-booster-utils.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-booster-ai.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-booster-parser.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-booster-api-runner.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-booster-content-manager.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-booster-trend-matcher.php';


		// Load CLI only in CLI context
        if (defined('WP_CLI') && WP_CLI) {
            // Ensure the path for CLI class is correct if it's directly in includes
            $cli_class_path = plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-booster-cli.php';
            if (file_exists($cli_class_path)) {
                require_once $cli_class_path;
                 // Check if class exists before adding command to prevent fatal errors if file doesn't define it
                if (class_exists('Booster_CLI')) {
                    WP_CLI::add_command('booster', 'Booster_CLI'); // Register base command
                }
            }
        }

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
	private function set_locale(): void { // Added void return type
		$plugin_i18n = new Booster_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area, public area, and cron functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_hooks(): void { // Added void return type
		// Admin hooks
        // The Booster_Admin constructor should use the loader to register its own hooks.
		$plugin_admin = new Booster_Admin( $this->plugin_name, $this->version, $this->loader );
        // If Booster_Admin has a register_hooks method that needs to be called explicitly:
        $plugin_admin->register_hooks(); // Or however it's named

		// Public hooks
		$plugin_public = new Booster_Public( $this->plugin_name, $this->version);
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
        // If Booster_Public has more hooks, its constructor or a method should handle them using $this->loader
        // Example if it needed the loader:
        // $plugin_public = new Booster_Public( $this->plugin_name, $this->version, $this->loader );


		// Cron jobs
        $plugin_cron = new Booster_Cron(); // No parameters needed for the constructor
        // The init method of Booster_Cron registers its specific cron action with add_action (WordPress function).
        // If Booster_Cron's init method was meant to register hooks via $this->loader, it would need $this->loader passed to its constructor.
        // Assuming Booster_Cron->init() uses WordPress's add_action directly for 'booster_content_cron'.
        $this->loader->add_action('init', $plugin_cron, 'init'); // Hook Booster_Cron's init method
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run(): void { // Added void return type
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name(): string { // Added string return type
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Booster_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader(): Booster_Loader { // Added Booster_Loader return type
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version(): string { // Added string return type
		return $this->version;
	}
}