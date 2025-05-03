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
 * Defines the admin-specific functionality of the plugin.
 *
 * @package    Booster
 * @subpackage Booster/admin
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
     * The loader responsible for maintaining and registering all hooks.
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
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version           The version of this plugin.
     * @param    object    $loader            The loader for registering hooks.
     */
    public function __construct($plugin_name, $version, $loader) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->loader = $loader;
        $this->register_hooks();
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    public function register_hooks() {
        $this->loader->add_action('admin_menu', $this, 'add_settings_page');
        $this->loader->add_action('admin_init', $this, 'register_settings');
        $this->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_scripts');
        $this->loader->add_action('admin_post_booster_clear_logs', $this, 'handle_clear_logs');
        $this->loader->add_action('wp_ajax_booster_get_logs', $this, 'handle_get_logs');

        if (method_exists($this, 'register_dashboard_widget')) {
            $this->loader->add_action('wp_dashboard_setup', $this, 'register_dashboard_widget');
        }
    }

    /**
     * Add settings page to the WordPress admin menu.
     *
     * @since    1.0.0
     */
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

    /**
     * Register all plugin settings.
     *
     * @since    1.0.0
     */
    public function register_settings() {
        $this->register_ai_settings();
        $this->register_api_settings();
        $this->register_shipping_settings();
    }

    /**
     * Register settings related to AI providers.
     *
     * @since    1.0.0
     */
    private function register_ai_settings() {
        register_setting('booster_settings', 'booster_ai_provider', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'huggingface',
        ]);
        register_setting('booster_settings', 'booster_openai_api_key', [
            'sanitize_callback' => [self::class, 'encrypt_api_key'],
            'default' => '',
        ]);
        register_setting('booster_settings', 'booster_huggingface_api_key', [
            'sanitize_callback' => [self::class, 'encrypt_api_key'],
            'default' => '',
        ]);
    }

    /**
     * Register settings related to external APIs.
     *
     * @since    1.0.0
     */
    private function register_api_settings() {
        register_setting('booster_settings', 'booster_api_key_newsapi', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting('booster_settings', 'booster_api_key_currentsapi', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
    }

    /**
     * Register settings related to shipping rates.
     *
     * @since    1.0.0
     */
    private function register_shipping_settings() {
        register_setting('booster_settings', 'booster_api_key_shipping', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
    }

    /**
     * Encrypt API keys before storing them in the database.
     *
     * @since    1.0.0
     * @param    string $key The API key to encrypt.
     * @return   string Encrypted API key.
     */
    public static function encrypt_api_key($key) {
        if (empty($key)) return '';
        $encryption_key = wp_salt('auth');
        return base64_encode(openssl_encrypt($key, 'aes-256-cbc', $encryption_key, 0, substr($encryption_key, 0, 16)));
    }

    /**
     * Decrypt API keys when retrieving them from the database.
     *
     * @since    1.0.0
     * @param    string $encrypted_key The encrypted API key to decrypt.
     * @return   string Decrypted API key.
     */
    public static function decrypt_api_key($encrypted_key) {
        if (empty($encrypted_key)) return '';
        $encryption_key = wp_salt('auth');
        return openssl_decrypt(base64_decode($encrypted_key), 'aes-256-cbc', $encryption_key, 0, substr($encryption_key, 0, 16));
    }

    /**
     * Render the settings page.
     *
     * @since    1.0.0
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            echo '<div class="notice notice-error"><p>' . __('You do not have sufficient permissions to access this page.', 'booster') . '</p></div>';
            return;
        }
        include plugin_dir_path(__FILE__) . 'partials/booster-admin-display.php';
    }

    /**
     * Handle clearing logs via admin-post.php.
     *
     * @since    1.0.0
     */
    public function handle_clear_logs() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Access denied', 'booster'));
        }
        check_admin_referer('booster_clear_logs');
        if (class_exists('Booster_Logger')) {
            Booster_Logger::clear_logs();
            wp_redirect(add_query_arg(['page' => 'booster-settings', 'logs' => 'cleared'], admin_url('admin.php')));
            exit;
        }
    }

    /**
     * Handle AJAX request for fetching logs.
     *
     * @since    1.0.0
     */
    public function handle_get_logs() {
        check_ajax_referer('booster_logs_nonce');
        $logs = Booster_Logger::get_recent_logs(30);
        wp_send_json_success(['logs' => $logs]);
    }

    /**
     * Enqueue stylesheets for the admin area.
     *
     * @since    1.0.0
     * @param    string $hook The current admin page.
     */
    public function enqueue_styles($hook) {
        if ('settings_page_booster-settings' !== $hook) {
            return;
        }
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/booster-admin.css', [], $this->version);
    }

    /**
     * Enqueue JavaScript for the admin area.
     *
     * @since    1.0.0
     * @param    string $hook The current admin page.
     */
    public function enqueue_scripts($hook) {
        if ('settings_page_booster-settings' !== $hook) {
            return;
        }
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/booster-admin.js', ['jquery'], $this->version, true);
        wp_localize_script($this->plugin_name, 'boosterAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('booster_admin_nonce'),
        ]);
    }

    /**
     * Register the dashboard widget for live logs.
     *
     * @since    1.0.0
     */
    public function register_dashboard_widget() {
        wp_add_dashboard_widget(
            'booster_live_log_widget',
            __('Booster Live Logs', 'booster'),
            [$this, 'render_live_log_widget']
        );
    }

    /**
     * Render the live logs widget.
     *
     * @since    1.0.0
     */
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
}