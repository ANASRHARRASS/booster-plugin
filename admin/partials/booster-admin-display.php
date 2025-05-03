<?php
/**
 * Admin page template for Booster plugin
 *
 * @package    Booster
 * @subpackage Booster/admin/partials
 */
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- ✅ FORM 1: Save Booster Settings -->
    <form method="post" action="options.php">
        <?php 
        settings_fields('booster_settings'); 
        do_settings_sections('booster-settings');
        ?>

        <h2><?php _e('AI Settings', 'booster'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="booster_ai_provider"><?php _e('AI Provider', 'booster'); ?></label></th>
                <td>
                    <select name="booster_ai_provider" id="booster_ai_provider">
                        <option value="huggingface" <?php selected(get_option('booster_ai_provider', 'huggingface'), 'huggingface'); ?>>Hugging Face</option>
                        <option value="openai" <?php selected(get_option('booster_ai_provider', 'huggingface'), 'openai'); ?>>OpenAI</option>
                    </select>
                    <p class="description"><?php _e('Select your preferred AI provider.', 'booster'); ?></p>
                </td>
            </tr> 
        </table>
        <h2><?php _e('API Keys', 'booster'); ?></h2>    
        <table class="form-table">
            <tr>
                <th scope="row"><label for="booster_api_key_newsapi"><?php _e('NewsAPI Key', 'booster'); ?></label></th>
                <td>
                    <input type="text" name="booster_api_key_newsapi" id="booster_api_key_newsapi" value="<?php echo esc_attr(get_option('booster_api_key_newsapi', '')); ?>" class="regular-text" />
                    <p class="description"><?php _e('Enter your NewsAPI key.', 'booster'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="booster_api_key_currentsapi"><?php _e('CurrentsAPI Key', 'booster'); ?></label></th>
                <td>
                    <input type="text" name="booster_api_key_currentsapi" id="booster_api_key_currentsapi" value="<?php echo esc_attr(get_option('booster_api_key_currentsapi', '')); ?>" class="regular-text" />
                    <p class="description"><?php _e('Enter your CurrentsAPI key.', 'booster'); ?></p>
                </td>
            </tr>

        </table>

        <h2><?php _e('API Providers', 'booster'); ?></h2>
        <table class="widefat" id="booster-provider-table" style="margin-bottom: 20px;">
            <thead>
                <tr>
                    <th><?php _e('API Group ID', 'booster'); ?></th>
                    <th><?php _e('Endpoint ID', 'booster'); ?></th>
                    <th><?php _e('Content Type', 'booster'); ?></th>
                    <th><?php _e('Rewrite?', 'booster'); ?></th>

                    <th><?php _e('Actions', 'booster'); ?></th>
                </tr>
            </thead>
            <tbody id="booster-provider-rows">
                <?php
                $providers = get_option('booster_provider_list', []);
                if (empty($providers)) {
                    $providers[] = ['api' => '', 'endpoint' => '', 'type' => 'news'];
                }

                foreach ($providers as $i => $row) : ?>
                    <tr>
                        <td><input type="text" name="booster_provider_list[<?php echo $i; ?>][api]" value="<?php echo esc_attr($row['api']); ?>" /></td>
                        <td><input type="text" name="booster_provider_list[<?php echo $i; ?>][endpoint]" value="<?php echo esc_attr($row['endpoint']); ?>" /></td>
                        <td>
                            <select name="booster_provider_list[<?php echo $i; ?>][type]">
                                <option value="news" <?php selected($row['type'], 'news'); ?>>News</option>
                                <option value="product" <?php selected($row['type'], 'product'); ?>>Product</option>
                                <option value="crypto" <?php selected($row['type'], 'crypto'); ?>>Crypto</option>
                            </select>
                        </td>
                        <td>
                            <label>
                               <input type="checkbox" name="booster_provider_list[<?php echo $i; ?>][rewrite]" value="1"
				                   <?php checked(!isset($row['rewrite']) || $row['rewrite']); ?> />
			                   <?php _e('Rewrite', 'booster'); ?>
                            </label>

                        </td>
                        <td><button type="button" class="button booster-remove-row">×</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <button type="button" class="button button-secondary" id="booster-add-provider"><?php _e('Add Provider', 'booster'); ?></button>

        <?php submit_button(__('Save All Settings', 'booster')); ?>
    </form>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <h2><?php _e('Manual Import', 'booster'); ?></h2>
        <input type="hidden" name="action" value="booster_manual_import">
        <?php wp_nonce_field('booster_manual_import'); ?>
        <?php submit_button(__('Fetch Content Now', 'booster'), 'primary'); ?>
    </form>

    <?php if (isset($_GET['fetch'])) : ?>
        <div class="notice notice-<?php echo $_GET['fetch'] === 'success' ? 'success' : 'error'; ?>">
            <p>
                <?php if ($_GET['fetch'] === 'success') : ?>
                    ✅ Fetched <?php echo intval($_GET['count']); ?> items.
                <?php else : ?>
                    ❌ Import failed. Check logs.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <h2><?php _e('Import Logs', 'booster'); ?></h2>

<?php
$logs = class_exists('Booster_Logger') ? Booster_Logger::get_recent_logs(30) : [];

if (!empty($logs)) : ?>
    <div style="background: #fff; border: 1px solid #ccd0d4; padding: 10px; max-height: 400px; overflow-y: auto;">
        <ul style="list-style: none; margin: 0; padding: 0;">
            <?php foreach ($logs as $log) : ?>
                <?php
                // Decide the log type based on keywords
                $color = '#333'; // Default text color
                if (stripos($log, '[ERROR]') !== false) {
                    $color = '#dc3232'; // red
                } elseif (stripos($log, '[WARNING]') !== false) {
                    $color = '#ffb900'; // yellow
                } elseif (stripos($log, '[SUCCESS]') !== false) {
                    $color = '#46b450'; // green
                } elseif (stripos($log, '[DEBUG]') !== false) {
                    $color = '#0073aa'; // blue
                }
                ?>
                <li style="padding: 4px 0; color: <?php echo esc_attr($color); ?>;">
                    <?php echo esc_html($log); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php else : ?>
    <p><?php _e('No logs available.', 'booster'); ?></p>
<?php endif; ?>


    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="booster_clear_logs" />
        <?php wp_nonce_field('booster_clear_logs'); ?>
        <?php submit_button(__('Clear Logs', 'booster'), 'delete'); ?>
    </form>
</div>
