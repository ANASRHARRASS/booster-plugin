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

declare(strict_types=1);

class Booster_Admin {

    /**
     * The ID of this plugin.
     */
    private string $plugin_name;

    /**
     * The version of this plugin.
     */
    private string $version;

    /**
     * The loader responsible for maintaining and registering all hooks.
     */
    private Booster_Loader $loader;

    /**
     * The slug for the settings page.
     */
    private const SETTINGS_PAGE_SLUG = 'booster-settings';

    /**
     * The option group name for settings.
     */
    public const SETTINGS_GROUP = 'booster_settings_group';


    public function __construct(string $plugin_name, string $version, Booster_Loader $loader) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->loader = $loader;
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     */
    public function register_hooks(): void {
        $this->loader->add_action('admin_menu', $this, 'add_settings_page');
        $this->loader->add_action('admin_init', $this, 'register_settings');
        $this->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_admin_assets');
        $this->loader->add_action('admin_post_booster_clear_logs', $this, 'handle_clear_logs');
        $this->loader->add_action('wp_ajax_booster_get_logs', $this, 'handle_get_logs_ajax');
        $this->loader->add_action('wp_dashboard_setup', $this, 'register_dashboard_widget');
    }

    public function add_settings_page(): void {
        add_submenu_page(
            'options-general.php',
            __('Booster Settings', 'booster'),
            __('Booster', 'booster'),
            'manage_options',
            self::SETTINGS_PAGE_SLUG,
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void {
        register_setting(self::SETTINGS_GROUP, 'booster_ai_provider', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'huggingface',
            'show_in_rest'      => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'booster_openai_api_key', [
            'type'              => 'string',
            'sanitize_callback' => [$this, 'sanitize_and_encrypt_api_key'],
            'default'           => '',
            'show_in_rest'      => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'booster_huggingface_api_key', [
            'type'              => 'string',
            'sanitize_callback' => [$this, 'sanitize_and_encrypt_api_key'],
            'default'           => '',
            'show_in_rest'      => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'booster_api_key_newsapi', [
            'type'              => 'string',
            'sanitize_callback' => [$this, 'sanitize_and_encrypt_api_key'],
            'default'           => '',
            'show_in_rest'      => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'booster_api_key_currentsapi', [
            'type'              => 'string',
            'sanitize_callback' => [$this, 'sanitize_and_encrypt_api_key'],
            'default'           => '',
            'show_in_rest'      => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'booster_provider_list', [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_provider_list'],
            'default'           => [],
            'show_in_rest'      => false,
        ]);
    }

    /**
     * Sanitize and encrypt an API key.
     *
     * @param string $input_key The raw API key from input.
     * @return string The sanitized and encrypted API key, or empty string.
     */
    public function sanitize_and_encrypt_api_key(string $input_key): string {
        $sanitized_key = sanitize_text_field(trim($input_key));
        if (empty($sanitized_key)) {
            return ''; // Allows clearing the key by submitting an empty value
        }
        // The form display logic (e.g., in booster-admin-display.php) should handle
        // showing placeholders like '********' for existing keys.
        // If '********' is submitted, it means the user literally typed that or the form
        // submitted the placeholder value. This function will encrypt that literal string.
        // To preserve an existing key without change, the form should ideally re-submit
        // the original encrypted value or use JavaScript to prevent submission of the field
        // if it's an unchanged placeholder.
        return self::encrypt_api_key($sanitized_key);
    }

    /**
     * Sanitize the provider list array.
     *
     * @param array|mixed $input The input array from the settings form.
     * @return array The sanitized provider list.
     */
    public function sanitize_provider_list($input): array {
        $sanitized_list = [];
        if (!is_array($input)) {
            return $sanitized_list;
        }

        foreach ($input as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sanitized_row = [
                'api'      => isset($row['api']) ? sanitize_text_field($row['api']) : '',
                'endpoint' => isset($row['endpoint']) ? sanitize_text_field($row['endpoint']) : '',
                'type'     => isset($row['type']) ? sanitize_key($row['type']) : 'news',
                'rewrite'  => isset($row['rewrite']) ? filter_var($row['rewrite'], FILTER_VALIDATE_BOOLEAN) : true,
            ];
            if (!empty($sanitized_row['api']) && !empty($sanitized_row['endpoint'])) {
                $sanitized_list[] = $sanitized_row;
            }
        }
        return $sanitized_list;
    }

    private const ENCRYPTION_METHOD = 'aes-256-cbc';

    /**
     * Get a deterministically derived IV for decrypting legacy data.
     * WARNING: This is only for backward compatibility with data encrypted
     * without a prepended random IV. New encryptions should use random IVs.
     *
     * @return string The derived IV.
     */
    private static function get_legacy_derived_iv(): string {
        $iv_string = wp_salt('nonce'); // Use a different salt than the encryption key's salt
        $iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);

        if (false === $iv_length) {
            // Fallback if cipher method is somehow unknown here, though ENCRYPTION_METHOD is const
            error_log('Booster_Admin: Unknown cipher method for legacy IV derivation: ' . self::ENCRYPTION_METHOD);
            return substr(hash('sha256', $iv_string), 0, 16); // Default to 16 bytes for AES-256-CBC
        }
        return substr(hash('sha256', $iv_string), 0, $iv_length);
    }

    public static function encrypt_api_key(string $key): string {
        if (empty($key)) {
            return '';
        }
        $encryption_key_string = wp_salt('auth'); // Consider a dedicated, strong key stored securely
        $secret_key = hash('sha256', $encryption_key_string);

        $iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
        if (false === $iv_length) {
            error_log('Booster_Admin: Unknown cipher method for encryption: ' . self::ENCRYPTION_METHOD);
            return ''; // Encryption cannot proceed
        }
        $iv = openssl_random_pseudo_bytes($iv_length); // Generate a cryptographically strong random IV

        $encrypted = openssl_encrypt($key, self::ENCRYPTION_METHOD, $secret_key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            error_log('Booster_Admin: Encryption failed. OpenSSL error: ' . openssl_error_string());
            return ''; // Encryption failed
        }
        return base64_encode($iv . $encrypted); // Prepend IV to ciphertext
    }

    public static function decrypt_api_key(string $encrypted_key_with_iv): string {
        if (empty($encrypted_key_with_iv)) {
            return '';
        }
        $decoded = base64_decode($encrypted_key_with_iv, true);
        if ($decoded === false) {
            return ''; // Not valid base64
        }

        $encryption_key_string = wp_salt('auth');
        $secret_key = hash('sha256', $encryption_key_string);

        $iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
        if (false === $iv_length) {
            error_log('Booster_Admin: Unknown cipher method for decryption: ' . self::ENCRYPTION_METHOD);
            return ''; // Decryption cannot proceed
        }

        // Check if the decoded string is long enough to contain an IV + at least 1 byte of data
        if (strlen($decoded) <= $iv_length) {
            // This might be an old key encrypted without a prepended IV, or corrupted data.
            // Attempt decryption with derived IV for backward compatibility (less secure)
            $derived_iv = self::get_legacy_derived_iv();
            // OPENSSL_RAW_DATA was not used for old method, assume 0
            $decrypted = openssl_decrypt($decoded, self::ENCRYPTION_METHOD, $secret_key, 0, $derived_iv);
            return $decrypted !== false ? $decrypted : '';
        }

        $iv = substr($decoded, 0, $iv_length);
        $ciphertext_raw = substr($decoded, $iv_length);

        $decrypted = openssl_decrypt($ciphertext_raw, self::ENCRYPTION_METHOD, $secret_key, OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : '';
    }


    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'booster'), '', ['response' => 403]);
            // wp_die() terminates execution, so no 'return' needed here.
        }
        include_once plugin_dir_path(__FILE__) . 'partials/booster-admin-display.php';
    }

    public function handle_clear_logs(): void {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('Access denied. You do not have permission to clear logs.', 'booster'),
                esc_html__('Permission Denied', 'booster'),
                ['response' => 403]
            );
        }
        check_admin_referer('booster_clear_logs_action', '_wpnonce_clear_logs');

        $message_slug = 'log_clear_failed'; // Default message
        if (is_callable(['Booster_Logger', 'clear_logs'])) {
            Booster_Logger::clear_logs();
            $message_slug = 'logs_cleared';
        } else {
            error_log('Booster_Admin: Booster_Logger::clear_logs is not callable.');
        }

        $redirect_url = add_query_arg(
            [
                'page'    => self::SETTINGS_PAGE_SLUG,
                'message' => $message_slug
            ],
            admin_url('options-general.php') // Base page for settings submenus
        );
        wp_safe_redirect($redirect_url);
        exit; // wp_safe_redirect calls exit, but explicit exit is a failsafe.
    }

    public function handle_get_logs_ajax(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'booster')], 403);
            // wp_send_json_error calls wp_die(), so no return/exit needed here.
        }
        check_ajax_referer('booster_ajax_logs_nonce', '_ajax_nonce');

        if (is_callable(['Booster_Logger', 'get_recent_logs'])) {
            $logs = Booster_Logger::get_recent_logs(30);
            wp_send_json_success(['logs' => $logs]);
        } else {
            error_log('Booster_Admin: Booster_Logger::get_recent_logs is not callable.');
            wp_send_json_error(['message' => __('Logger not available.', 'booster')]);
        }
        // wp_send_json_success/error call wp_die() which terminates execution.
    }

    public function enqueue_admin_assets(string $hook_suffix): void {
        $is_settings_page = ('settings_page_' . self::SETTINGS_PAGE_SLUG === $hook_suffix);
        $is_dashboard = ('dashboard' === $hook_suffix || 'index.php' === $hook_suffix); // index.php is main dashboard hook

        if (!$is_settings_page && !$is_dashboard) {
            return;
        }

        if ($is_settings_page) {
            wp_enqueue_style(
                $this->plugin_name . '-admin-style',
                plugin_dir_url(__FILE__) . 'css/booster-admin.css',
                [],
                $this->version
            );

            wp_enqueue_script(
                $this->plugin_name . '-admin-script',
                plugin_dir_url(__FILE__) . 'js/booster-admin.js',
                ['jquery'],
                $this->version,
                true
            );

            wp_localize_script(
                $this->plugin_name . '-admin-script',
                'boosterAdminData',
                [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'logs_nonce' => wp_create_nonce('booster_ajax_logs_nonce'),
                    'lang' => [
                        'apiIdPlaceholder'      => esc_attr__('e.g., newsapi', 'booster'),
                        'endpointIdPlaceholder' => esc_attr__('e.g., top-headlines', 'booster'),
                        'removeRowLabel'        => esc_attr__('Remove this provider row', 'booster'),
                        'rewriteLabel'          => esc_html__('Rewrite with AI', 'booster'),
                        'errorRequiredFields'   => esc_html__('Please fill out all required fields (API Group ID and Endpoint ID) for all provider rows.', 'booster'),
                        'confirmRemoveRow'      => esc_html__('Are you sure you want to remove this provider row?', 'booster'),
                    ],
                ]
            );
        }

        // For dashboard widget script (if any specific JS, otherwise inline is fine)
        // The inline script in render_live_log_widget uses jQuery.
        // WordPress usually loads jQuery on the dashboard.
        // If you had a separate JS file for the widget, you'd enqueue it here for $is_dashboard.
    }


    public function register_dashboard_widget(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'booster_live_log_widget',
            __('Booster Live Logs', 'booster'),
            [$this, 'render_live_log_widget']
        );
    }

    public function render_live_log_widget(): void {
        // Ensure jQuery is available (though usually it is on dashboard)
        wp_print_scripts(['jquery']);
        $ajax_nonce = wp_create_nonce('booster_ajax_logs_nonce');
        ?>
        <div id="booster-live-logs-widget" style="background: #fff; border: 1px solid #ccd0d4; padding: 10px; height: 300px; overflow-y: scroll; font-family: monospace; font-size: 0.9em;">
            <em><?php esc_html_e('Loading logs...', 'booster'); ?></em>
        </div>
        <script type="text/javascript">
            (function($) {
                var boosterWidgetData = {
                    ajax_url: '<?php echo esc_url_raw(admin_url('admin-ajax.php')); ?>',
                    nonce: '<?php echo esc_js($ajax_nonce); ?>',
                    noLogsText: '<?php echo esc_js(__('No logs available or error fetching logs.', 'booster')); ?>',
                    errorText: '<?php echo esc_js(__('Error fetching logs.', 'booster')); ?>'
                };

                function fetchBoosterLogsForWidget() {
                    $.ajax({
                        url: boosterWidgetData.ajax_url,
                        method: 'POST',
                        data: {
                            action: 'booster_get_logs',
                            _ajax_nonce: boosterWidgetData.nonce
                        },
                        dataType: 'json',
                        success: function(response) {
                            var logContainer = $('#booster-live-logs-widget');
                            if (response && response.success && response.data && Array.isArray(response.data.logs) && response.data.logs.length) {
                                var html = '<ul style="list-style:none;padding:0;margin:0;">';
                                response.data.logs.forEach(function(logEntry) {
                                    var logText = typeof logEntry === 'string' ? logEntry : JSON.stringify(logEntry);
                                    var color = '#333'; // Default
                                    if (logText.includes('[ERROR]') || logText.includes('Failed')) color = '#dc3232';
                                    else if (logText.includes('[WARNING]')) color = '#ffb900';
                                    else if (logText.includes('[SUCCESS]') || logText.includes('Successfully')) color = '#46b450';
                                    else if (logText.includes('[DEBUG]') || logText.includes('[INFO]')) color = '#0073aa';
                                    
                                    var sanitizedLogText = $('<div/>').text(logText).html(); // Basic XSS prevention
                                    html += '<li style="padding:2px 0;border-bottom:1px dotted #eee;color:' + color + ';">' + sanitizedLogText + '</li>';
                                });
                                html += '</ul>';
                                logContainer.html(html);
                                logContainer.scrollTop(logContainer[0].scrollHeight);
                            } else if (response && !response.success && response.data && response.data.message) {
                                 logContainer.html('<em>' + $('<div/>').text(response.data.message).html() + '</em>');
                            }
                            else {
                                logContainer.html('<em>' + boosterWidgetData.noLogsText + '</em>');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error("Booster Logs Widget AJAX Error:", textStatus, errorThrown);
                            $('#booster-live-logs-widget').html('<em>' + boosterWidgetData.errorText + '</em>');
                        }
                    });
                }

                $(document).ready(function() {
                    var widgetElement = $('#booster-live-logs-widget');
                    if (widgetElement.length) {
                        fetchBoosterLogsForWidget();
                        var boosterLogsInterval = setInterval(fetchBoosterLogsForWidget, 15000);

                        // Optional: Clear interval if widget is removed (more robust handling needed for dynamic dashboard)
                        // $(document).on('widget-removed', function(event, widget) {
                        //     if (widget && widget.id === 'booster_live_log_widget') {
                        //         clearInterval(boosterLogsInterval);
                        //     }
                        // });
                    }
                });
            })(jQuery);
        </script>
        <?php
    }
}