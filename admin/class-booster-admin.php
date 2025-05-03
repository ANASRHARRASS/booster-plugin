<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://shippingsmile.com/anasrharrass
 * @since      1.0.0
 *
 * @package    Booster
 * @subpackage Booster/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Booster
 * @subpackage Booster/admin
 * @author     anas <anas@shippingsmile.com>
 */
class Booster_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;
	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Booster_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	private $loader;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version, $loader ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->loader = $loader;
		$this->register_hooks();

	}
	/**
	 * Register all of the hooks related to the admin area functionality
	 *
	 * @since    1.0.0
	 * @access   private
	 */
    public function register_hooks() {
        // Existing hooks...
		//settings
        $this->loader->add_action('admin_menu', $this, 'add_settings_page');
        $this->loader->add_action('admin_init', $this, 'register_settings');
		//assets
		$this->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_styles');
		$this->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_scripts');
		//Notices
		$this->loader->add_action('admin_notices', $this, 'show_admin_notices');
		// add clear logs action
		$this->loader->add_action('admin_post_booster_clear_logs', $this, 'handle_clear_logs');
		// add widget
		
		if (method_exists($this, 'register_dashboard_widget')) {
			$this->loader->add_action('wp_dashboard_setup', $this, 'register_dashboard_widget');
		}
		$this->loader->add_action('wp_ajax_booster_get_logs', $this, 'handle_get_logs');
		

	}
   	/**
    * Handles the clear log action from admin-post.php.
    *
    * @since    1.0.0
    * @return   void
    */
   public function handle_clear_logs() {
       if (!current_user_can('manage_options')) {
           wp_die(__('Access denied', 'booster'));
       }
   
       check_admin_referer('booster_clear_logs');
   
       if (class_exists('Booster_Logger')) {
           Booster_Logger::clear_logs();
   
           wp_redirect(add_query_arg([
               'page' => 'booster-settings',
               'logs' => 'cleared',
           ], admin_url('admin.php')));
           exit;
       }
   }
   public function handle_get_logs() {
    check_ajax_referer('booster_logs_nonce');

    $logs = Booster_Logger::get_recent_logs(30);

    wp_send_json_success([
        'logs' => $logs,
    ]);
}

    
    public function add_settings_page() {
        add_submenu_page(
            'options-general.php',
            __('Booster Settings', 'booster'),
			__('Booster', 'booster'),
            'manage_options',
            'booster-settings',
            [$this, 'render_settings_page']
        );
    }
    
    public function register_settings() {
		register_setting('booster_settings', 'booster_provider_list',[
			'sanitize_callback' => [self::class,'sanitize_provider_list'],
			'default' => [],
		]);
		// Register settings for AI provider
		register_setting('booster_settings', 'booster_ai_provider', [
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'huggingface',
		]);
		register_setting('booster_settings', 'booster_openai_api_key', [
			'sanitize_callback' => 'sanitize_text_field',
			'default' => '',
		]);
		register_setting('booster_settings', 'booster_huggingface_api_key', [
			'sanitize_callback' => 'sanitize_text_field',
			'default' => '',
		]);
		
		// Register settings for content rewrite
		register_setting('booster_settings', 'booster_rewrite_content', [
			'sanitize_callback' => 'sanitize_text_field',
			'default' => '1',
		]);
		// Add settings for API keys in Booster_Admin
        register_setting('booster_settings', 'booster_api_key_newsapi', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        
        register_setting('booster_settings', 'booster_api_key_currentsapi', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
		
        register_setting('booster_settings', 'booster_affiliate_keywords',[
			'sanitize_callback' => 'sanitize_text_field',
			'default' => '',
		]);
		
        register_setting('booster_settings', 'booster_affiliate_base_url',[
			'sanitize_callback' => 'esc_url_raw',
			'default' => '',
		]);


        // Register settings for shipping rates or future sections
        register_setting('booster_settings', 'booster_api_key_shipping', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);



    }
	public static function sanitize_provider_list($input) {
		if (!is_array($input)) return [];
	
		return array_map(function ($row) {
			return [
				'api'      => sanitize_text_field($row['api'] ?? ''),
				'endpoint' => sanitize_text_field($row['endpoint'] ?? ''),
				'type'     => sanitize_text_field($row['type'] ?? 'news'),
				'rewrite'  => isset($row['rewrite']) && $row['rewrite'] ? true : false,
			];
		}, $input);
	}
	
	
    
    public function render_settings_page() {
		// Check if the user has the required capability
		if (!current_user_can('manage_options')) {
			return;
		}
        include plugin_dir_path(__FILE__) . 'partials/booster-admin-display.php';
    }

	public function register_dashboard_widget() {
		wp_add_dashboard_widget(
			'booster_live_log_widget',
			__('Booster Live Logs', 'booster'),
			[$this, 'render_live_log_widget']
		);
	}
	public function render_live_log_widget() {
		?>
		<div id="booster-live-logs" style="background: #fff; border: 1px solid #ccd0d4; padding: 10px; height: 300px; overflow-y: scroll; font-size: 13px;">
			<em>Loading logs...</em>
		</div>
	
		<script type="text/javascript">
			(function($) {
				function fetchBoosterLogs() {
					$.ajax({
						url: ajaxurl,
						method: 'POST',
						data: {
							action: 'booster_get_logs',
							_ajax_nonce: '<?php echo wp_create_nonce('booster_logs_nonce'); ?>'
						},
						success: function(response) {
							if (response.success && response.data.logs.length) {
								var html = '<ul style="list-style:none;padding:0;margin:0;">';
								response.data.logs.forEach(function(log) {
									let color = '#333';
									if (log.includes('[ERROR]')) color = '#dc3232';
									else if (log.includes('[WARNING]')) color = '#ffb900';
									else if (log.includes('[SUCCESS]')) color = '#46b450';
									else if (log.includes('[DEBUG]')) color = '#0073aa';
									html += '<li style="padding:2px 0; color:' + color + ';">' + log + '</li>';
								});
								html += '</ul>';
								$('#booster-live-logs').html(html);
								//auto scroll to bottom
								var logContainer = document.getElementById('booster-live-logs');
								logContainer.scrollTop = logContainer.scrollHeight;
							} else {
								$('#booster-live-logs').html('<em>No logs available.</em>');
							}
						}
					});
				}
	
				$(document).ready(function() {
					fetchBoosterLogs();
					setInterval(fetchBoosterLogs, 10000); // Refresh every 10 seconds
				});
			})(jQuery);
		</script>
		<?php
	}
		
	

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles($hook) {
		if ('settings_page_booster-settings' !== $hook) {
			return;
		}

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Booster_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Booster_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/booster-admin.css',array(), $this->version );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts($hook) {
		if ('settings_page_booster-settings' !== $hook) {
			return;
		}

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Booster_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Booster_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/booster-admin.js',array('jquery'), $this->version, true );
		// add localization if needed
		wp_localize_script( $this->plugin_name, 'boosterAdmin', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'booster_admin_nonce' ),
		) );

	}
	// add to your admin class
	private function register_ajax_handlers() {
		add_action('wp_ajax_booster_clear_logs', array($this, 'handle_clear_logs'));
		add_action('wp_ajax_booster_manual_import', array($this, 'handle_manual_import'));
	}
	/**
	 * Show admin notices.
	 *
	 * @since    1.0.0
	 */
	public function show_admin_notices() {
		if(!is_plugin_active('booster/booster.php')){
			?>
			<div class="notice notice-error">
				<p><?php
				printf(__('Booster requires WPGetAPI plugin. %sInstall it now%s', 'booster'),
					'<a href="' . esc_url(admin_url('plugin-install.php?s=wpgetapi&tab=search&type=term')) . '" target="_blank">',
					'</a>'
				);
				?></p>
			</div>
			<?php
		}
	}

}
