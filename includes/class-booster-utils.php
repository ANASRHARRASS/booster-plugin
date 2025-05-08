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

declare(strict_types=1); // Enforce strict types for better type safety

class Booster_Utils {

    /**
     * Clean up temporary files.
     *
     * @param string $path The path to the temporary file.
     * @return void
     */
    public static function cleanup_temp_file(string $path): void {
        // Check if path is not empty before file operations
        if (!empty($path) && file_exists($path) && is_writable($path)) {
            @unlink($path); // Suppress errors as it's a cleanup
        }
    }

    /**
     * Downloads an image from a URL and sets it as the featured image for a post.
     *
     * @param int    $post_id   The post to attach the image to.
     * @param string $image_url The URL of the image to download.
     * @return bool True on success, false on failure.
     */
    public static function set_featured_image(int $post_id, string $image_url): bool {
        if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
            Booster_Logger::log(sprintf("[Booster Utils] Invalid image URL for post %d: %s", $post_id, $image_url));
            return false;
        }

        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $tmp_file = download_url($image_url, 15);

        if (is_wp_error($tmp_file)) {
            Booster_Logger::log(sprintf("[Booster Utils] Initial download failed for post %d from %s. Retrying... Error: %s", $post_id, $image_url, $tmp_file->get_error_message()));
            sleep(2);
            $tmp_file = download_url($image_url, 30);
            if (is_wp_error($tmp_file)) {
                Booster_Logger::log(sprintf('[Booster Utils] Retry download failed for post %d from %s: %s', $post_id, $image_url, $tmp_file->get_error_message()));
                return false;
            }
        }

        // $tmp_file is now guaranteed to be a string path
        // Check if $tmp_file is empty or not a string (though download_url should ensure it's a string path on success)
        if (empty($tmp_file) || !is_string($tmp_file) || !file_exists($tmp_file)) {
            Booster_Logger::log(sprintf("[Booster Utils] Temporary file path is invalid or file does not exist after download for post %d. Path: %s", $post_id, var_export($tmp_file, true)));
            // No file to cleanup if $tmp_file is invalid path
            return false;
        }

        // Validate mime type
        // mime_content_type returns string on success, false on failure.
        $mime = mime_content_type($tmp_file);

        // FIX for Line 64: Simplified condition. If $mime is not false, it's a string.
        if ($mime === false || strpos($mime, 'image/') !== 0) {
            Booster_Logger::log(sprintf("[Booster Utils] Invalid image type for post %d. URL: %s, Temp File: %s, Mime: %s", $post_id, $image_url, $tmp_file, var_export($mime, true)));
            self::cleanup_temp_file($tmp_file);
            return false;
        }

        $parsed_url_path = parse_url($image_url, PHP_URL_PATH);
        $filename_from_url = $parsed_url_path ? basename($parsed_url_path) : '';
        $image_extension_parts = explode('/', $mime);
        $image_extension = $image_extension_parts[1] ?? 'jpg'; // Fallback extension

        $filename = !empty($filename_from_url) ? $filename_from_url : uniqid('image_') . '.' . $image_extension;
        if (empty(pathinfo($filename, PATHINFO_EXTENSION))) {
            $filename .= '.' . $image_extension;
        }

        $file_size = filesize($tmp_file);
        if (false === $file_size) {
            Booster_Logger::log(sprintf("[Booster Utils] Could not get filesize for temp file: %s (Post ID: %d)", $tmp_file, $post_id));
            self::cleanup_temp_file($tmp_file);
            return false;
        }

        $file_array = [
            'name'     => $filename, // Already a string
            'type'     => $mime,     // Already a string
            'tmp_name' => $tmp_file, // Already a string path
            'error'    => '0',      // WordPress expects string '0' for no error
            'size'     => (string) $file_size, // Ensure size is string for media_handle_sideload
        ];

        $attachment_id = media_handle_sideload($file_array, $post_id, null);
        self::cleanup_temp_file($tmp_file); // Cleanup happens regardless of sideload success

        if (is_wp_error($attachment_id)) {
            Booster_Logger::log(sprintf('[Booster Utils] Failed to sideload image for post %d: %s', $post_id, $attachment_id->get_error_message()));
            return false;
        }

        // $attachment_id is now an int
        $file_path = get_attached_file($attachment_id);
        if (is_string($file_path) && file_exists($file_path)) {
            $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
            if (is_array($attach_data)) { // Ensure metadata is an array
                wp_update_attachment_metadata($attachment_id, $attach_data);
            } else {
                 Booster_Logger::log(sprintf('[Booster Utils] Failed to generate attachment metadata for attachment %d (Post ID: %d). wp_generate_attachment_metadata returned: %s', $attachment_id, $post_id, var_export($attach_data, true)));
            }
        } else {
            Booster_Logger::log(sprintf('[Booster Utils] Could not get attached file path or file does not exist for attachment %d (Post ID: %d). Path: %s', $attachment_id, $post_id, var_export($file_path, true)));
        }

        if (!set_post_thumbnail($post_id, $attachment_id)) {
            Booster_Logger::log(sprintf("[Booster Utils] Failed to set featured image (Attachment ID: %d) for post %d", $attachment_id, $post_id));
            return false;
        }

        Booster_Logger::log(sprintf("[Booster Utils] Featured image set successfully for post %d (Attachment ID: %d)", $post_id, $attachment_id));
        return true;
    }

    /**
     * Extract keywords from content and assign as post tags.
     *
     * @param int    $post_id  The post to assign tags to.
     * @param string $content  The content to analyze.
     * @param string $taxonomy The taxonomy to use (default: post_tag).
     * @return void
     */
    public static function extract_and_assign_tags(int $post_id, string $content, string $taxonomy = 'post_tag'): void {
        try {
            if (empty(trim($content))) {
                Booster_Logger::log(sprintf("[Booster Utils] Content is empty for post %d. Skipping tag extraction.", $post_id));
                return;
            }

            $text = strtolower(wp_strip_all_tags(html_entity_decode($content)));
            $stopwords = [ /* ... your stopwords ... */ 'a', 'an', 'and', 'the']; // Keep it short for example

            preg_match_all('/\b\p{L}{4,}\b/u', $text, $matches);

            // FIX for Line 166: $matches[0] will exist and be an array (possibly empty)
            $words = $matches[0];

            if (empty($words)) {
                Booster_Logger::log(sprintf("[Booster Utils] No suitable words found for keyword extraction in post %d.", $post_id));
                return;
            }

            $counts = array_count_values($words);
            $filtered_keywords = array_filter($counts, function ($word_key) use ($stopwords) { // iterate over keys
                return !in_array($word_key, $stopwords, true);
            }, ARRAY_FILTER_USE_KEY);


            if (empty($filtered_keywords)) {
                Booster_Logger::log(sprintf("[Booster Utils] No keywords left after stopword filtering for post %d.", $post_id));
                return;
            }

            arsort($filtered_keywords);
            $top_keywords = array_slice(array_keys($filtered_keywords), 0, 5);

            if (empty($top_keywords)) {
                Booster_Logger::log(sprintf("[Booster Utils] No top keywords derived for post %d.", $post_id));
                return;
            }

            update_post_meta($post_id, '_booster_keywords', implode(', ', $top_keywords));
            $term_result = wp_set_post_terms($post_id, $top_keywords, $taxonomy, true);

            if (is_wp_error($term_result)) {
                Booster_Logger::log(sprintf("[Booster Utils] Error setting terms for post %d: %s", $post_id, $term_result->get_error_message()));
            } else {
                Booster_Logger::log(sprintf("[Booster Utils] Successfully set terms for post %d: %s", $post_id, implode(', ', $top_keywords)));
            }

        } catch (\Throwable $e) { // Catch Throwable for PHP 7+ broader errors
            Booster_Logger::log(sprintf("[Booster Utils] Error extracting keywords for post %d: %s", $post_id, $e->getMessage()));
        }
    }

    public static function process_post(int $post_id, string $image_url, string $content): bool {
        $image_result = self::set_featured_image($post_id, $image_url);
        self::extract_and_assign_tags($post_id, $content);
        return $image_result;
    }
    /**
     * Clean up failed imports by removing posts that didn't complete processing.
     * 
     * @param array<int> $post_ids Optional. Array of post IDs to clean up. If empty, will query for failed imports.
     * @return void
     */

    public static function clean_failed_imports(array $post_ids = []): void {
        if (empty($post_ids)) {
            $query_args = [ /* ... */ ];
            /** @var list<int>|false $post_ids_to_clean */ // get_posts can return false
            $post_ids_to_clean = get_posts($query_args);

            if (empty($post_ids_to_clean) || !is_array($post_ids_to_clean)) { // also check if it's an array
                Booster_Logger::log("[Booster Utils] No failed imports found to clean up based on query or query failed.");
                return;
            }
            $post_ids = $post_ids_to_clean;
        }

        try {
            $deleted_count = 0;
            foreach ($post_ids as $post_id_item) { // Use a different variable name to avoid confusion
                if (!is_int($post_id_item) || $post_id_item <= 0) {
                    Booster_Logger::log(sprintf("[Booster Utils] Invalid post ID skipped during cleanup: %s", var_export($post_id_item, true)));
                    continue;
                }
                if (wp_delete_post($post_id_item, true)) {
                    $deleted_count++;
                    Booster_Logger::log(sprintf("[Booster Utils] Cleaned up failed import: Post ID %d", $post_id_item));
                } else {
                    Booster_Logger::log(sprintf("[Booster Utils] Failed to delete post during cleanup: Post ID %d", $post_id_item));
                }
            }
            if ($deleted_count > 0) {
                Booster_Logger::log(sprintf("[Booster Utils] Cleanup complete. Deleted %d failed imports.", $deleted_count));
            } else {
                 Booster_Logger::log("[Booster Utils] Cleanup ran, but no posts were deleted from the provided/queried list.");
            }
        } catch (\Throwable $e) {
            Booster_Logger::log("[Booster Utils] Error during cleanup: " . $e->getMessage());
        }
    }

    /**
     * Attempt to fetch an Open Graph (OG) image or the first <img> src from a URL.
     *
     * @param string $url The URL to fetch the image from.
     * @return string|null The found image URL (raw) or null if not found/error.
     */
    public static function fetch_open_graph_image(string $url): ?string {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            Booster_Logger::log(sprintf("[Booster Utils] Invalid URL provided for OpenGraph fetch: %s", $url));
            return null;
        }

        try {
            // FIX for Line 297: Ensure sslverify is a boolean
            $sslverify_value = apply_filters('booster_fetch_og_image_sslverify', true);
            $args = [
                'timeout' => 15,
                'headers' => [
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'User-Agent'      => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
                ],
                'sslverify' => is_bool($sslverify_value) ? $sslverify_value : true, // Ensure boolean
            ];
            $response = wp_remote_get($url, $args);


            if (is_wp_error($response)) {
                Booster_Logger::log(sprintf('[Booster Utils] Failed to fetch page %s for OG image: %s', $url, $response->get_error_message()));
                return null;
            }

            $html = wp_remote_retrieve_body($response);
            $http_code = wp_remote_retrieve_response_code($response);

            if ($http_code !== 200 || empty($html)) {
                Booster_Logger::log(sprintf('[Booster Utils] Empty or non-200 response from %s for OG image. HTTP Code: %s', $url, is_int($http_code) ? (string) $http_code : 'N/A'));
                return null;
            }

            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html); // Prepending XML encoding hint
            libxml_clear_errors();

            $xpath = new \DOMXPath($dom);

            // Look for OpenGraph image
            $meta_og_image_nodes = $xpath->query("//meta[@property='og:image']/@content");
            if ($meta_og_image_nodes instanceof \DOMNodeList && $meta_og_image_nodes->length > 0) {
                $first_node = $meta_og_image_nodes->item(0);
                // FIX for Line 331: Check if node and nodeValue are not null
                if ($first_node !== null && $first_node->nodeValue !== null) {
                    $og_image_url = trim($first_node->nodeValue);
                    if (!empty($og_image_url) && filter_var($og_image_url, FILTER_VALIDATE_URL)) {
                        Booster_Logger::log(sprintf("[Booster Utils] Found OpenGraph image for %s: %s", $url, $og_image_url));
                        return esc_url_raw($og_image_url);
                    }
                }
            }
            
            // Look for Twitter Card image
            $meta_twitter_image_nodes = $xpath->query("//meta[@name='twitter:image']/@content");
            if ($meta_twitter_image_nodes instanceof \DOMNodeList && $meta_twitter_image_nodes->length > 0) {
                $first_node_twitter = $meta_twitter_image_nodes->item(0);
                 // FIX for Line 341: Check if node and nodeValue are not null
                if ($first_node_twitter !== null && $first_node_twitter->nodeValue !== null) {
                    $twitter_image_url = trim($first_node_twitter->nodeValue);
                    if (!empty($twitter_image_url) && filter_var($twitter_image_url, FILTER_VALIDATE_URL)) {
                        Booster_Logger::log(sprintf("[Booster Utils] Found Twitter Card image for %s: %s", $url, $twitter_image_url));
                        return esc_url_raw($twitter_image_url);
                    }
                }
            }

            $img_tags = $dom->getElementsByTagName('img');
            if ($img_tags instanceof \DOMNodeList && $img_tags->length > 0) { // Check instance type
                foreach ($img_tags as $img_node) {
                    if (!($img_node instanceof \DOMElement)) continue; // Ensure it's an Element
                    
                    if ($img_node->hasAttribute('src')) {
                        $fallback_image_src_raw = $img_node->getAttribute('src');
                        $fallback_image_src = trim($fallback_image_src_raw);

                        if (!empty($fallback_image_src) && !preg_match('#^https?://#i', $fallback_image_src) && !preg_match('#^//#i', $fallback_image_src)) { // Also check for protocol-relative
                             $parsed_page_url = parse_url($url);
                             if (isset($parsed_page_url['scheme'], $parsed_page_url['host'])) {
                                 $base = $parsed_page_url['scheme'] . '://' . $parsed_page_url['host'];
                                 if (strpos($fallback_image_src, '/') === 0) {
                                     $fallback_image_src = $base . $fallback_image_src;
                                 } else {
                                     $path = dirname($parsed_page_url['path'] ?? '/');
                                     $fallback_image_src = $base . rtrim($path, '/') . '/' . ltrim($fallback_image_src, '/');
                                 }
                             }
                        } elseif (preg_match('#^//#i', $fallback_image_src)) { // Handle protocol-relative URLs
                            $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'http';
                            $fallback_image_src = $scheme . ':' . $fallback_image_src;
                        }


                        if (!empty($fallback_image_src) && filter_var($fallback_image_src, FILTER_VALIDATE_URL)) {
                            Booster_Logger::log(sprintf("[Booster Utils] Fallback first IMG found for %s: %s", $url, $fallback_image_src));
                            return esc_url_raw($fallback_image_src);
                        }
                    }
                }
            }

            Booster_Logger::log(sprintf('[Booster Utils] No suitable image found on the page %s.', $url));
            return null;

        } catch (\Throwable $e) {
            Booster_Logger::log(sprintf("[Booster Utils] Error parsing OpenGraph/HTML for %s: %s", $url, $e->getMessage()));
            return null;
        }
    }
}