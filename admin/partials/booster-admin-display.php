<?php
/**
 * Admin page template for Booster plugin
 *
 * @package    Booster
 * @subpackage Booster/admin/partials
 */

// Helper function to safely get string options
if (!function_exists('booster_get_string_option')) {
    /**
     * Safely retrieves a string option.
     *
     * @param string $option_name The name of the option.
     * @param string $default_value The default value if the option is not found or not a string.
     * @return string The option value.
     */
    function booster_get_string_option(string $option_name, string $default_value = ''): string {
        $value = get_option($option_name, $default_value);
        return is_string($value) ? $value : $default_value;
    }
}

// Helper function to safely get an array option
if (!function_exists('booster_get_array_option')) {
    /**
     * Safely retrieves an array option.
     *
     * @param string $option_name The name of the option.
     * @param array<array-key, mixed> $default_value The default value if the option is not found or not an array.
     * @return array<array-key, mixed> The option value.
     */
    function booster_get_array_option(string $option_name, array $default_value = []): array {
        $value = get_option($option_name, $default_value);
        return is_array($value) ? $value : $default_value;
    }
}

$booster_ai_provider_option = booster_get_string_option('booster_ai_provider', 'huggingface');

// Decrypt API keys safely
$decrypted_openai_key = '';
$openai_key_option = get_option('booster_openai_api_key', '');
if (is_string($openai_key_option) && !empty($openai_key_option) && is_callable(['Booster_Admin', 'decrypt_api_key'])) {
    $decrypted_openai_key = Booster_Admin::decrypt_api_key($openai_key_option);
}

$decrypted_huggingface_key = '';
$huggingface_key_option = get_option('booster_huggingface_api_key', '');
if (is_string($huggingface_key_option) && !empty($huggingface_key_option) && is_callable(['Booster_Admin', 'decrypt_api_key'])) {
    $decrypted_huggingface_key = Booster_Admin::decrypt_api_key($huggingface_key_option);
}

$decrypted_newsapi_key = '';
$newsapi_key_option = get_option('booster_api_key_newsapi', '');
if (is_string($newsapi_key_option) && !empty($newsapi_key_option) && is_callable(['Booster_Admin', 'decrypt_api_key'])) {
    $decrypted_newsapi_key = Booster_Admin::decrypt_api_key($newsapi_key_option);
}

$decrypted_currentsapi_key = '';
$currentsapi_key_option = get_option('booster_api_key_currentsapi', '');
if (is_string($currentsapi_key_option) && !empty($currentsapi_key_option) && is_callable(['Booster_Admin', 'decrypt_api_key'])) {
    $decrypted_currentsapi_key = Booster_Admin::decrypt_api_key($currentsapi_key_option);
}

?>

<div class="wrap booster-settings-page">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php
    // Display admin notices
    if (isset($_GET['fetch'])) {
        $fetch_status = sanitize_text_field(wp_unslash($_GET['fetch']));
        $fetch_count = isset($_GET['count']) ? absint($_GET['count']) : 0;

        if ($fetch_status === 'success' && $fetch_count > 0) {
            echo '<div id="booster-fetch-success" class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('Successfully fetched %d item(s). They are now in drafts.', 'booster'), $fetch_count) . '</p></div>';
        } elseif ($fetch_status === 'zero') {
            echo '<div id="booster-fetch-zero" class="notice notice-warning is-dismissible"><p>' . esc_html__('Fetched 0 new items. No new content found or all items were duplicates.', 'booster') . '</p></div>';
        } elseif ($fetch_status === 'failed') {
            echo '<div id="booster-fetch-failed" class="notice notice-error is-dismissible"><p>' . esc_html__('Manual import failed to fetch new items or an error occurred.', 'booster') . '</p></div>';
        } elseif ($fetch_status === 'error' && isset($_GET['message'])) {
            $error_message = sanitize_text_field(urldecode(wp_unslash($_GET['message'])));
            echo '<div id="booster-fetch-error" class="notice notice-error is-dismissible"><p>' . esc_html__('An error occurred during manual import: ', 'booster') . esc_html($error_message) . '</p></div>';
        }
    }
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
         echo '<div id="booster-settings-saved" class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'booster') . '</p></div>';
    }
    if (isset($_GET['message'])) { // General message handling from redirects (e.g., log clear)
        $message_type = 'info'; // Default
        $message_text = '';
        if ($_GET['message'] === 'logs_cleared') {
            $message_text = esc_html__('Logs cleared successfully.', 'booster');
            $message_type = 'success';
        } elseif ($_GET['message'] === 'log_clear_failed') {
            $message_text = esc_html__('Failed to clear logs. Logger might not be available.', 'booster');
            $message_type = 'error';
        }
        if (!empty($message_text)) {
            echo '<div class="notice notice-' . esc_attr($message_type) . ' is-dismissible"><p>' . $message_text . '</p></div>';
        }
    }
    ?>

    <!-- ✅ FORM 1: Save Booster Settings -->
    <form method="post" action="options.php" id="booster-settings-form">
        <?php
        settings_fields(Booster_Admin::SETTINGS_GROUP); // Use class constant
        // If you have registered sections, uncomment and use the correct slug:
        // do_settings_sections(Booster_Admin::SETTINGS_PAGE_SLUG); 
        ?>

        <!-- 🔧 AI Provider Section -->
        <h2><?php esc_html_e('AI Settings', 'booster'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="booster_ai_provider"><?php esc_html_e('AI Provider', 'booster'); ?></label></th>
                <td>
                    <select name="booster_ai_provider" id="booster_ai_provider">
                        <option value="huggingface" <?php selected($booster_ai_provider_option, 'huggingface'); ?>>Hugging Face</option>
                        <option value="openai" <?php selected($booster_ai_provider_option, 'openai'); ?>>OpenAI</option>
                    </select>
                    <p class="description"><?php esc_html_e('Select your preferred AI provider.', 'booster'); ?></p>
                </td>
            </tr>
        </table>

        <!-- 🔑 API Keys Section -->
        <h2><?php esc_html_e('API Keys', 'booster'); ?></h2>
        <table class="form-table">
             <tr>
                <th scope="row"><label for="booster_openai_api_key"><?php esc_html_e('OpenAI Key', 'booster'); ?></label></th>
                <td>
                    <input type="password" name="booster_openai_api_key" id="booster_openai_api_key" value="<?php echo esc_attr($decrypted_openai_key); ?>" placeholder="<?php echo !empty($decrypted_openai_key) ? esc_attr__('******** (saved)', 'booster') : ''; ?>" class="regular-text" autocomplete="new-password" />
                    <p class="description"><?php esc_html_e('Enter your OpenAI API key. Leave blank if unchanged.', 'booster'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="booster_huggingface_api_key"><?php esc_html_e('Hugging Face Key', 'booster'); ?></label></th>
                <td>
                    <input type="password" name="booster_huggingface_api_key" id="booster_huggingface_api_key" value="<?php echo esc_attr($decrypted_huggingface_key); ?>" placeholder="<?php echo !empty($decrypted_huggingface_key) ? esc_attr__('******** (saved)', 'booster') : ''; ?>" class="regular-text" autocomplete="new-password" />
                    <p class="description"><?php esc_html_e('Enter your Hugging Face API key. Leave blank if unchanged.', 'booster'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="booster_api_key_newsapi"><?php esc_html_e('NewsAPI Key', 'booster'); ?></label></th>
                <td>
                    <input type="password" name="booster_api_key_newsapi" id="booster_api_key_newsapi" value="<?php echo esc_attr($decrypted_newsapi_key); ?>" placeholder="<?php echo !empty($decrypted_newsapi_key) ? esc_attr__('******** (saved)', 'booster') : ''; ?>" class="regular-text" autocomplete="new-password" />
                    <p class="description"><?php esc_html_e('Enter your NewsAPI.org key. Leave blank if unchanged.', 'booster'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="booster_api_key_currentsapi"><?php esc_html_e('CurrentsAPI Key', 'booster'); ?></label></th>
                <td>
                    <input type="password" name="booster_api_key_currentsapi" id="booster_api_key_currentsapi" value="<?php echo esc_attr($decrypted_currentsapi_key); ?>" placeholder="<?php echo !empty($decrypted_currentsapi_key) ? esc_attr__('******** (saved)', 'booster') : ''; ?>" class="regular-text" autocomplete="new-password" />
                    <p class="description"><?php esc_html_e('Enter your CurrentsAPI.services key. Leave blank if unchanged.', 'booster'); ?></p>
                </td>
            </tr>
        </table>

        <!-- 📡 API Providers Section -->
        <h2><?php esc_html_e('Content Providers (Sources)', 'booster'); ?></h2>
        <p class="description"><?php esc_html_e('Configure API endpoints from WPGetAPI to fetch content from. Ensure WPGetAPI is installed and endpoints are set up there first.', 'booster'); ?></p>
        <table class="widefat fixed striped" id="booster-provider-table" style="margin-bottom: 20px;">
            <thead>
                <tr>
                    <th style="width: 25%;"><label><?php esc_html_e('API Group ID (from WPGetAPI)', 'booster'); ?></label></th>
                    <th style="width: 25%;"><label><?php esc_html_e('Endpoint ID (from WPGetAPI)', 'booster'); ?></label></th>
                    <th style="width: 20%;"><label><?php esc_html_e('Content Type (for parsing)', 'booster'); ?></label></th>
                    <th style="width: 15%;"><label><?php esc_html_e('Rewrite with AI?', 'booster'); ?></label></th>
                    <th style="width: 15%;"><?php esc_html_e('Actions', 'booster'); ?></th>
                </tr>
            </thead>
            <tbody id="booster-provider-rows">
                <?php
                /** @var array<int, array<string, mixed>> $providers */
                $providers = booster_get_array_option('booster_provider_list', []);
                // Ensure $providers is an array of arrays for the loop.


                if (empty($providers)) {
                    // Start with one empty row for the JS template if no providers are configured.
                    // This row won't be saved if left empty due to sanitize_provider_list logic.
                    $providers = [['api' => '', 'endpoint' => '', 'type' => 'news', 'rewrite' => true]];
                }

                foreach ($providers as $i => $row_data) :
                    //$row_data is guaranteed to be an array <string, mixed> due to the default value above.
                    $api      = isset($row_data['api']) && is_string($row_data['api']) ? $row_data['api'] : '';
                    $endpoint = isset($row_data['endpoint']) && is_string($row_data['endpoint']) ? $row_data['endpoint'] : '';
                    $type     = isset($row_data['type']) && is_string($row_data['type']) ? $row_data['type'] : 'news';
                    $rewrite_val = $row_data['rewrite'] ?? true; // Default to true if not set
                    $rewrite  = filter_var($rewrite_val, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]) ?? true;
                ?>
                    <tr class="booster-provider-row">
                        <td><input type="text" name="booster_provider_list[<?php echo esc_attr((string)$i); ?>][api]" value="<?php echo esc_attr($api); ?>" placeholder="<?php esc_attr_e('e.g., newsapi', 'booster'); ?>" class="widefat" aria-label="<?php esc_attr_e('API Group ID', 'booster'); ?>"/></td>
                        <td><input type="text" name="booster_provider_list[<?php echo esc_attr((string)$i); ?>][endpoint]" value="<?php echo esc_attr($endpoint); ?>" placeholder="<?php esc_attr_e('e.g., top-headlines', 'booster'); ?>" class="widefat" aria-label="<?php esc_attr_e('Endpoint ID', 'booster'); ?>"/></td>
                        <td>
                            <select name="booster_provider_list[<?php echo esc_attr((string)$i); ?>][type]" class="widefat" aria-label="<?php esc_attr_e('Content Type', 'booster'); ?>">
                                <option value="news" <?php selected($type, 'news'); ?>><?php esc_html_e('News', 'booster'); ?></option>
                                <option value="product" <?php selected($type, 'product'); ?>><?php esc_html_e('Product', 'booster'); ?></option>
                                <option value="crypto" <?php selected($type, 'crypto'); ?>><?php esc_html_e('Crypto', 'booster'); ?></option>
                                <!-- Add other types as needed -->
                            </select>
                        </td>
                        <td>
                            <label class="booster-rewrite-label">
                               <input type="checkbox" name="booster_provider_list[<?php echo esc_attr((string)$i); ?>][rewrite]" value="1" <?php checked($rewrite); ?> aria-label="<?php esc_attr_e('Rewrite with AI', 'booster'); ?>" />
                                <span class="screen-reader-text"><?php esc_html_e('Rewrite with AI', 'booster'); ?></span>
                            </label>
                        </td>
                        <td><button type="button" class="button booster-remove-row" aria-label="<?php esc_attr_e('Remove this provider row', 'booster'); ?>">×</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="button" class="button button-secondary" id="booster-add-provider"><?php esc_html_e('Add Provider Row', 'booster'); ?></button>
        <?php submit_button(esc_html__('Save All Settings', 'booster')); ?>
    </form>
    <hr/>

    <!-- 📥 Manual Import Section -->
    <div id="booster-manual-import-section">
        <h2><?php esc_html_e('Manual Content Fetch', 'booster'); ?></h2>
        <p><?php esc_html_e('Click the button below to trigger a one-time content fetch based on your saved provider settings. This runs the same process as the scheduled cron job.', 'booster'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="booster_manual_import">
            <?php wp_nonce_field('booster_manual_import_action', '_wpnonce_manual_import'); ?>
            <?php submit_button(esc_html__('Fetch Content Now', 'booster'), 'primary', 'booster_fetch_now_button'); ?>
        </form>
    </div>
    <hr/>

    <!-- 🔍 Import Logs Section -->
    <div id="booster-logs-section">
        <h2><?php esc_html_e('Recent Import Logs', 'booster'); ?></h2>
        <?php
        $logs = [];
        if (is_callable(['Booster_Logger', 'get_recent_logs'])) {
            $logs = Booster_Logger::get_recent_logs(30);
        }


        if (!empty($logs)) : ?>
            <div class="booster-logs-container" style="background: #fff; border: 1px solid #ccd0d4; padding: 10px; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 0.9em;">
                <ul style="list-style: none; margin: 0; padding: 0;">
                    <?php foreach ($logs as $log_entry) :
                        $log_data = is_scalar($log_entry) ? (string) $log_entry : wp_json_encode($log_entry);
                        if (false === $log_data) {
                            $log_string = '[Error encoding log entry to JSON]';
                        } else {
                            $log_string = $log_data;
                        }

                        $color = '#333'; // Default color
                        if (stripos($log_string, '[error]') !== false || stripos($log_string, 'failed') !== false) {
                            $color = '#dc3232'; // Red for errors
                        } elseif (stripos($log_string, '[warning]') !== false) {
                            $color = '#ffb900'; // Yellow for warnings
                        } elseif (stripos($log_string, '[success]') !== false || stripos($log_string, 'successfully') !== false) {
                            $color = '#46b450'; // Green for success
                        } elseif (stripos($log_string, '[debug]') !== false || stripos($log_string, '[info]') !== false) {
                            $color = '#0073aa'; // Blue for info/debug
                        }
                    ?>
                        <li style="padding: 3px 0; border-bottom: 1px dotted #eee; color: <?php echo esc_attr($color); ?>;">
                            <?php echo esc_html($log_string); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else : ?>
            <p><?php esc_html_e('No recent logs available.', 'booster'); ?></p>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:15px;">
            <input type="hidden" name="action" value="booster_clear_logs" />
            <?php wp_nonce_field('booster_clear_logs_action', '_wpnonce_clear_logs'); ?>
            <?php submit_button(esc_html__('Clear All Logs', 'booster'), 'delete', 'booster_clear_logs_button'); ?>
        </form>
    </div>
</div>