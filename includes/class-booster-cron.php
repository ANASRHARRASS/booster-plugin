<?php

declare(strict_types=1);

class Booster_Cron {

    /**
     * Constructor.
     */
    public function __construct() {
        // No properties needed
    }

    /**
     * Initialize cron-related actions.
     */
    public function init(): void {
        add_action('booster_content_cron', [$this, 'run_content_import']);
        add_action('admin_post_booster_manual_import', [$this, 'run_manual_import']);
    }

    /**
     * Executes the content import process via cron.
     */
    public function run_content_import(): void {
        if (!class_exists('Booster_Content_Manager')) {
            error_log("[Booster] Cron: Booster_Content_Manager class not found. Cannot run import.");
            return;
        }

        $manager = new Booster_Content_Manager();
        $count = $manager->run_content_import();
        error_log(sprintf("[Booster] Cron import completed. Imported %d items.", $count));
    }

    /**
     * Executes the content import process manually via an admin action.
     * Redirects back to settings page with status.
     */
    public function run_manual_import(): void {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have sufficient permissions to perform this action.', 'booster'),
                esc_html__('Authorization Failed', 'booster'),
                ['response' => 403, 'back_link' => true]
            );
        }

        if (!isset($_POST['_wpnonce_manual_import']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['_wpnonce_manual_import'])), 'booster_manual_import_action')) {
            wp_die(
                esc_html__('Security check failed. Please try again.', 'booster'),
                esc_html__('Nonce Verification Failed', 'booster'),
                ['response' => 403, 'back_link' => true]
            );
        }

        if (!class_exists('Booster_Content_Manager')) {
            error_log("[Booster] Manual Import: Booster_Content_Manager class not found. Cannot run import.");
            $redirect_url = add_query_arg([
                'page'    => 'booster-settings',
                'fetch'   => 'error',
                'message' => urlencode(__('Content manager component is missing.', 'booster')),
            ], admin_url('admin.php'));
            wp_safe_redirect($redirect_url);
            exit;
        }

        $manager = new Booster_Content_Manager();
        $count = $manager->run_content_import();

        $redirect_url = add_query_arg([
            'page'    => 'booster-settings',
            'fetch'   => $count > 0 ? 'success' : ($count === 0 ? 'zero' : 'failed'),
            'count'   => $count,
        ], admin_url('admin.php'));

        wp_safe_redirect($redirect_url);
        exit;
    }
}