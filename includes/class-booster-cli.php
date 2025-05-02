<?php
/**
 * this class is used for wp-cli commands
 * @package    Booster
 * @subpackage Booster/includes
 */
if ( ! defined( 'WP_CLI' ) ) {
    return;
}
class Booster_CLI {

    /**
     * Fix missing featured images for previously imported posts.
     *
     * ## EXAMPLES
     *     wp booster:repair-images
     *
     */
    public function __invoke() {
        $query = new WP_Query([
            'post_type'      => 'post',
            'post_status'    => 'draft',
            'meta_query'     => [
                [
                    'key'     => '_booster_source_url',
                    'compare' => 'EXISTS'
                ]
            ],
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        if (!$query->have_posts()) {
            WP_CLI::success("No posts found.");
            return;
        }

        $count_fixed = 0;

        foreach ($query->posts as $post_id) {
            if (has_post_thumbnail($post_id)) {
                WP_CLI::log("✅ Post {$post_id} already has a thumbnail.");
                continue;
            }

            $image_url = get_post_meta($post_id, '_booster_image_url', true);

            if (!$image_url) {
                WP_CLI::warning("⚠️ No image URL for post {$post_id}");
                continue;
            }

            WP_CLI::log("🔄 Fixing image for post {$post_id}...");

            $result = Booster_Utils::set_featured_image($post_id, $image_url);

            if ($result) {
                $count_fixed++;
                WP_CLI::success("🖼️ Image set for post {$post_id}");
            } else {
                WP_CLI::error("❌ Failed to set image for post {$post_id}");
            }
        }

        WP_CLI::success("🎉 Fixed {$count_fixed} posts.");
    }
}
