<?php
class Booster_Cron {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function init() {
        add_action('booster_content_cron', [$this, 'run_content_import']);
        // Optional: Add manual trigger
        add_action('admin_post_booster_manual_import', [$this, 'run_manual_import']);
    }

    public function run_content_import() {
        $manager = new Booster_Content_Manager($this->plugin_name, $this->version);
        $count = $manager->run_content_import();
        error_log("[Booster] Cron import completed. Imported $count items imported.");
    }
    public function run_manual_import() {
        if (!current_user_can('manage_options') || !check_admin_referer('booster_manual_import')) {
            wp_die(__('Authorization failed', 'booster'));
        }
        $manager = new Booster_Content_Manager($this->plugin_name, $this->version);
        $count = $manager->run_content_import();
        // Redirect back to admin settings with result
        wp_redirect(add_query_arg([
            'page' => 'booster-settings',
            'fetch' => $count ? 'success' : 'failed',
            'count' => $count
        ], admin_url('admin.php')));
        exit;
    }
}