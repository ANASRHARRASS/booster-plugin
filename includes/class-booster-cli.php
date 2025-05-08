<?php
/**
 * WP-CLI commands for the Booster plugin.
 * @package    Booster
 * @subpackage Booster/includes
 */

declare(strict_types=1);

if (!defined('WP_CLI')) { // Simplified check, WP_CLI constant itself is true if defined.
    return;
}

class Booster_CLI {

    /**
     * Fix missing featured images for previously imported posts.
     *
     * ## OPTIONS
     *
     * [--batch-size=<number>]
     * : Process posts in batches. Default is 50.
     * ---
     * default: 50
     * ---
     *
     * [--dry-run]
     * : Perform a dry run without actually setting images.
     *
     * ## EXAMPLES
     *
     *     wp booster fix-images
     *     wp booster fix-images --batch-size=100
     *     wp booster fix-images --dry-run
     *
     * @when after_wp_load
     *
     * @param array<int, string> $args Positional arguments (not used in this command).
     * @param array<string, string|true> $assoc_args Associative arguments (flags).
     */
    public function fix_images(array $args, array $assoc_args): void {
        $batch_size = isset($assoc_args['batch-size']) ? absint($assoc_args['batch-size']) : 50;
        if ($batch_size <= 0) {
            $batch_size = 50;
        }
        $dry_run = isset($assoc_args['dry-run']);

        if ($dry_run) {
            WP_CLI::log(WP_CLI::colorize('%YDry run mode enabled. No changes will be made.%n'));
        }

        // Check for Booster_Utils class existence
        if (!class_exists('Booster_Utils')) {
            WP_CLI::error("Booster_Utils class is not available. Cannot proceed.");
            // WP_CLI::error() exits, so no return is needed here, fixing "Unreachable statement"
        }

        $paged = 1;
        $total_processed = 0;
        $count_fixed = 0;
        $count_skipped_has_thumb = 0;
        $count_skipped_no_url = 0;
        $count_failed_set = 0;

        do {
            $query_args = [
                'post_type'      => 'post',
                'post_status'    => 'draft',
                'meta_query'     => [
                    [
                        'key'     => '_booster_image_url',
                        'compare' => 'EXISTS',
                    ],
                    [
                        'key'     => '_booster_image_url',
                        'value'   => '',
                        'compare' => '!=',
                    ],
                ],
                'posts_per_page' => $batch_size,
                'paged'          => $paged,
                'fields'         => 'ids', // Ensures $post_ids are integers
                'orderby'        => 'ID',
                'order'          => 'ASC',
            ];

            $query = new WP_Query($query_args);

            if (!$query->have_posts()) {
                if ($paged === 1) {
                    WP_CLI::log("No posts found matching the criteria to fix images.");
                }
                break;
            }

            /** @var array<int, int> $post_ids Ensure PHPStan knows this is an array of integers */
            $post_ids = $query->posts;

            WP_CLI::log(sprintf("Processing batch %d: Found %d posts...", $paged, count($post_ids)));

            foreach ($post_ids as $post_id) {
                // $post_id is an integer here due to 'fields' => 'ids'.
                // PHPStan should not complain about type mismatches for $post_id from this point.
                $total_processed++;

                if (has_post_thumbnail($post_id)) {
                    $count_skipped_has_thumb++;
                    continue;
                }

                $image_url_meta = get_post_meta($post_id, '_booster_image_url', true);
                $image_url = is_string($image_url_meta) ? $image_url_meta : ''; // Ensure string

                if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
                    $count_skipped_no_url++;
                    continue;
                }

                // $post_id is int, safe for string concatenation.
                WP_CLI::log(WP_CLI::colorize("%B=> Fixing image for post {$post_id} with URL: {$image_url}%n"));

                if ($dry_run) {
                    WP_CLI::log(WP_CLI::colorize("%Y[Dry Run]%n Would attempt to set image for post {$post_id}."));
                    $count_fixed++;
                    continue;
                }

                // $post_id is int, Booster_Utils::set_featured_image expects int.
                $result = Booster_Utils::set_featured_image($post_id, $image_url);

                if ($result) {
                    $count_fixed++;
                    WP_CLI::success(WP_CLI::colorize("%g🖼️ Image set for post {$post_id}%n"));
                } else {
                    $count_failed_set++;
                    WP_CLI::error(WP_CLI::colorize("%r❌ Failed to set image for post {$post_id}%n"));
                }
                usleep(100000);
            }

            $paged++;
            wp_cache_flush();

        } while ($query->max_num_pages >= $paged);

        WP_CLI::log("------------------------------------------");
        WP_CLI::success(WP_CLI::colorize("%g🎉 Image fixing process complete!%n"));
        WP_CLI::log(sprintf("Total posts processed: %d", $total_processed));
        WP_CLI::log(sprintf("Images successfully set/fixed: %d", $count_fixed) . ($dry_run ? WP_CLI::colorize(" %Y(Dry Run)%n") : ""));
        WP_CLI::log(sprintf("Skipped (already had thumbnail): %d", $count_skipped_has_thumb));
        WP_CLI::log(sprintf("Skipped (no valid image URL): %d", $count_skipped_no_url));
        WP_CLI::log(sprintf("Failed to set image: %d", $count_failed_set));
        WP_CLI::log("------------------------------------------");
    }

    /**
     * Invoke alias for fix_images.
     * Allows `wp booster` to call `wp booster fix-images`
     *
     * @param array<int, string> $args Positional arguments.
     * @param array<string, string|true> $assoc_args Associative arguments (flags).
     */
    public function __invoke(array $args, array $assoc_args): void {
        WP_CLI::log("Running `wp booster fix-images` (invoked directly)...");
        $this->fix_images($args, $assoc_args);
    }
}

/**
 * Registers the WP-CLI command.
 */
// The check `defined('WP_CLI') && WP_CLI` where WP_CLI is a constant is fine.
// PHPStan's "Right side of && is always true" for `WP_CLI` constant might be overly strict
// or assumes WP_CLI is always defined as `true`. The simpler check above is common.
WP_CLI::add_command('booster', 'Booster_CLI');