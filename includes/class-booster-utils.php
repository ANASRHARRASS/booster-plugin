<?php
/**
 * Booster_Utils Class
 *
 * Provides helper functions like setting featured images,
 * logging, and keyword processing for the Booster plugin.
 *
 * @since      1.0.0
 * @package    Booster
 * @subpackage Booster/includes
 */

class Booster_Utils {

    /**
     * clean up temporary files
     */
    public static function cleanup_temp_file(string $path): void {
        if (file_exists($path)) {
            @unlink($path);
        }
    }
    /**
     * Downloads an image from a URL and sets it as the featured image for a post.
     *
     * @param int    $post_id  The post to attach the image to.
     * @param string $image_url The URL of the image to download.
     * @return bool True on success, false on failure.
     */
    public static function set_featured_image($post_id, $image_url): bool {
        if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
            Booster_Logger::log("[Booster] Invalid image URL for post {$post_id}: $image_url");
            return false;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // Download the file to the temp dir
        $tmp_file = download_url($image_url);

        if (is_wp_error($tmp_file)) {
            Booster_Logger::log("[Booster] Initial download failed . Retrying...");
            sleep(1);
            $tmp_file = download_url($image_url);
            if (is_wp_error($tmp_file)) {
                Booster_Logger::log('[Booster] Retry download failed: ' . $tmp_file->get_error_message());
                return false;
            }
        }
        // validate mime type
        $mime = mime_content_type($tmp_file);
        if (strpos($mime, 'image/') !== 0) {
            Booster_Logger::log("[Booster] Invalid image type for post {$post_id}: $mime");
            self::cleanup_temp_file($tmp_file);
            return false;
        }

        // Get the image file name
        $filename = basename(parse_url($image_url, PHP_URL_PATH));

        // Upload the file to the media library
        $file_array = [
            'name'     => $filename,
            'type'     => $mime,
            'tmp_name' => $tmp_file,
            'error'    => 0,
            'size'     => filesize($tmp_file),
        ];

        // Move the file into the uploads directory
        $attachment_id = media_handle_sideload( $file_array, $post_id);

        if (is_wp_error($attachment_id)) {
            Booster_Logger::log('[Booster] failed to sideload image: ' . $attachment_id->get_error_message());
            self::cleanup_temp_file($file_array['tmp_name']); // Clean up temp file
            return false;
        }
        $attach_data = wp_generate_attachment_metadata($attachment_id, get_attached_file($attachment_id));
        wp_update_attachment_metadata($attachment_id, $attach_data);
        
        //attach and set as featured image
        $set = set_post_thumbnail($post_id, $attachment_id);
        if (!$set){
            Booster_Logger::log("[Booster] Failed to set featured image  (attachment ID: {$attachment_id}) for post {$post_id}");
            return false;
        }
        Booster_Logger::log("[Booster] Featured image set successfully for post {$post_id} ");
        return true;
    }
        /**
     * Extract keywords from content and assign as post tags.
     *
     * @param int $post_id   The post to assign tags to.
     * @param string $content The content to analyze.
     * @return void
     */
    public static function extract_and_assign_tags($post_id, $content,$taxonomy = 'post_tag'): void {
        try{
            if (empty($content)) {
                return;
            }
            // Clean text (strip HTML and lowercase)
            $text = strtolower(strip_tags($content));
            // Remove common stopwords (basic set for now)
            $stopwords = ['the', 'and', 'with', 'this', 'that', 'for', 'from', 'https', 'about', 'your', 'you', 'are', 'was', 'will', 'have', 'has', 'just', 'been'];
            
            // Break into words
            preg_match_all('/\b[a-z]{4,}\b/', $text, $matches); // only words with 4+ letters
            $words = $matches[0] ?? [];
               // Count frequency
            $counts = array_count_values($words);
        
            // Filter stopwords + get top 5 keywords
            $keywords = array_keys(array_filter($counts, function ($word) use ($stopwords) {
                return !in_array($word, $stopwords);
            }, ARRAY_FILTER_USE_KEY));
        
            // Sort by frequency
            usort($keywords, function($a, $b) use ($counts) {
                return $counts[$b] <=> $counts[$a];
            });
        
            $top_keywords = array_slice($keywords, 0, 5);
            
            
            
            // Optional: store in post meta for reference
            update_post_meta($post_id, '_booster_keywords', implode(', ', $top_keywords));
            // Assign as tags
            wp_set_post_terms($post_id, $top_keywords, $taxonomy, true); // true = append, not replace
            } catch (Exception $e) {
                Booster_Logger::log("[Booster] Error extracting keywords: {$post_id} : " .$e->getMessage());
            }
        }
    /**
     * process both image and tags for post
     */
    public static function process_post(int $post_id, string $image_url, string $content): bool {
        $image_result = self::set_featured_image($post_id, $image_url);
        self::extract_and_assign_tags($post_id, $content);
        return $image_result;
    }
    
    /**
     * clean failed imports by removing pots that didn't complete processing
     * @param array $post_ids The post IDs to clean.
     * @return void
     */
    public static function clean_failed_imports(array $post_ids = []): void {
        if (empty($post_ids)) {
            $post_ids = get_posts([
                'post_type'      => 'post',
                'post_status'    => 'draft',
                'meta_key'       => '_booster_content_hash',
                'meta_query'     => [
                    [
                        'key'     => '_booster_keywords',
                        'compare' => 'NOT EXISTS'
                    ]
                ],
                'posts_per_page' => -1,
                'fields'         => 'ids'
            ]);
        try {
            $failed_posts = get_posts([
                'post_type'      => 'post',
                'post_status'    => 'draft',
                'meta_key'       => '_booster_content_hash',
                'meta_query'     => [
                    [
                        'key'     => '_booster_keywords',
                        'compare' => 'NOT EXISTS'
                    ]
                ],
                'posts_per_page' => -1,
                'fields'         => 'ids'
            ]);

            if (empty($failed_posts)) {
                Booster_Logger::log("[Booster] No failed imports found to clean up");
                return;
            }

            $deleted = 0;
            foreach ($failed_posts as $post_id) {
                if (wp_delete_post($post_id, true)) {
                    $deleted++;
                    Booster_Logger::log("[Booster] Cleaned up failed import: Post ID {$post_id}");
                } else {
                    Booster_Logger::log("[Booster] Failed to delete post: {$post_id}");
                }
            }

            Booster_Logger::log("[Booster] Cleanup complete. Deleted {$deleted} failed imports");

        } catch (Exception $e) {
            Booster_Logger::log("[Booster] Error during cleanup: " . $e->getMessage());
        }
    }
}
    /**
     * Attempt to fetch an Open Graph (OG) image from a URL if no image is provided.
     *
     * @param string $url
     * @return string|null The found image URL or null if not found
     */
    public static function fetch_open_graph_image(string $url): ?string {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            Booster_Logger::log("[Booster_Utils] Invalid URL provided for OpenGraph fetch: $url");
            return null;
        }
    
        try {
            $response = wp_remote_get($url, [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml',
                ],
            ]);
    
            if (is_wp_error($response)) {
                Booster_Logger::log('[Booster_Utils] Failed to fetch page: ' . $response->get_error_message());
                return null;
            }
    
            $html = wp_remote_retrieve_body($response);
    
            if (empty($html)) {
                Booster_Logger::log('[Booster_Utils] Empty page content.');
                return null;
            }
    
            // Load HTML and suppress warnings
            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            @$dom->loadHTML($html);
            libxml_clear_errors();
    
            $xpath = new \DOMXPath($dom);
    
            // Look for OpenGraph image
            $meta_tags = $xpath->query("//meta[@property='og:image']");
    
            if ($meta_tags->length > 0) {
                $meta_tag = $meta_tags->item(0);
                if ($meta_tag instanceof \DOMElement) {
                    $image = $meta_tag->getAttribute('content');
                } else {
                    $image = null;
                }
                Booster_Logger::log("[Booster_Utils] Found OpenGraph image: $image");
                return esc_url_raw($image);
            }
    
            // If no OpenGraph image found, fallback: first <img> tag
            $img_tags = $dom->getElementsByTagName('img');
            if ($img_tags->length > 0) {
                $fallback_image = $img_tags->item(0)->getAttribute('src');
                Booster_Logger::log("[Booster_Utils] Fallback first IMG found: $fallback_image");
                return esc_url_raw($fallback_image);
            }
    
            Booster_Logger::log('[Booster_Utils] No image found on the page.');
            return null;
    
        } catch (\Throwable $e) {
            Booster_Logger::log("[Booster_Utils] Error parsing OpenGraph: " . $e->getMessage());
            return null;
        }
    }
    
    
}
