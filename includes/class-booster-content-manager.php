<?php

/**
 * Booster_Content_Manager Class
 *
 * This class is responsible for managing the content import process.
 * It loads configured providers, fetches data via Booster_API_Runner,
 * normalizes it with Booster_Parser, and creates WordPress posts.
 *
 * @since      1.0.0
 * @package    Booster
 * @subpackage Booster/includes
 * @author     anas <anas@shippingsmile.com>
 */

declare(strict_types=1);

class Booster_Content_Manager {

    /**
     * Constructor.
     */
    public function __construct() {
        // Constructor parameters removed as they were unused.
    }

    /**
     * Safely get a string value from an array, providing a default if not found or not a string/scalar.
     *
     * @param array<mixed> $array The array to search in.
     * @param string|int   $key   The key to look for.
     * @param string       $default The default value to return.
     * @return string
     */
    private static function get_string_value(array $array, $key, string $default = ''): string {
        if (isset($array[$key]) && (is_string($array[$key]) || is_numeric($array[$key]) || is_bool($array[$key]))) {
            return (string) $array[$key];
        }
        return $default;
    }

    /**
     * Safely get a boolean value from an array.
     *
     * @param array<mixed> $array
     * @param string|int   $key
     * @param bool         $default
     * @return bool
     */
    private static function get_bool_value(array $array, $key, bool $default = true): bool {
        if (isset($array[$key])) {
            if (is_bool($array[$key])) {
                return $array[$key];
            }
            if (is_string($array[$key])) {
                $val = strtolower($array[$key]);
                if ($val === 'true' || $val === '1' || $val === 'on' || $val === 'yes') return true;
                if ($val === 'false' || $val === '0' || $val === 'off' || $val === 'no') return false;
            }
            if (is_numeric($array[$key])) {
                return (bool) $array[$key];
            }
        }
        return $default;
    }


    /**
     * Main importer logic: loops through all provider APIs, fetches and posts.
     *
     * @return int The total number of posts created.
     */
    public function run_content_import(): int {
        $providers_option = get_option('booster_provider_list', []);
        $providers = is_array($providers_option) ? $providers_option : [];

        if (empty($providers)) {
            Booster_Logger::log("[Booster CM] No providers configured.");
            return 0;
        }

        $total_created = 0;

        foreach ($providers as $provider_config) {
           

            $api_id      = self::get_string_value($provider_config, 'api');
            $endpoint_id = self::get_string_value($provider_config, 'endpoint');
            $type        = self::get_string_value($provider_config, 'type', 'news');

            if (empty($api_id) || empty($endpoint_id)) {
                Booster_Logger::log("[Booster CM] Skipped provider due to missing API ID or Endpoint ID. API: '{$api_id}', Endpoint: '{$endpoint_id}'");
                continue;
            }

            if (!class_exists('Booster_API_Runner')) {
                 Booster_Logger::log("[Booster CM] Booster_API_Runner class not found. Skipping fetch for API: {$api_id} / {$endpoint_id}");
                 continue;
            }
            $raw_data = Booster_API_Runner::fetch_from_wpgetapi($api_id, $endpoint_id);

            

            if (!class_exists('Booster_Parser')) {
                 Booster_Logger::log("[Booster CM] Booster_Parser class not found. Skipping normalization for API: {$api_id} / {$endpoint_id}");
                 continue;
            }
            /** @var array<string, mixed> $raw_data_typed Ensure this matches expected input for normalize. Given the check above, $raw_data is an array here. */
            $raw_data_typed = $raw_data; // This cast is fine as long as $raw_data is known to be array from the check above
            $normalized_data = Booster_Parser::normalize($raw_data_typed, $type); // Returns array<int, array<string, mixed>>

            if (empty($normalized_data)) {
                Booster_Logger::log("[Booster CM] Normalization returned no data or failed for API: {$api_id} / {$endpoint_id}");
                continue;
            }



            $total_created += $this->create_posts($normalized_data, $api_id);
        }

        return $total_created;
    }

    /**
     * Creates WordPress posts from normalized items.
     *
     * @param array<int, array<string, mixed>> $items       Normalized items to create posts from.
     * @param string                           $provider_id The ID of the provider.
     * @return int The number of posts successfully created.
     */
    private function create_posts(array $items, string $provider_id = ''): int {
        $created_count = 0;

        if (empty($items)) {
            Booster_Logger::log("[Booster CM] No items to process for provider: {$provider_id}");
            return 0;
        }

        // Each $item_data here is expected to be array<string, mixed> due to $items type hint.
        foreach ($items as $item_data) {
            // Defensive check: if Booster_Parser somehow violates its contract and returns
            // a non-array element in $items, this would catch it.
            // However, with strict typing and correct PHPDocs, this should ideally not be necessary.

            try {
                $title = self::get_string_value($item_data, 'title', 'Untitled Post');
                $url   = self::get_string_value($item_data, 'url');
                $hash  = md5(strtolower(trim($title . $url)));

                if ($this->post_exists_by_hash($hash)) {
                    Booster_Logger::log("[Booster CM] Skipped duplicate post: '{$title}' (Hash: {$hash})");
                    continue;
                }

                $content = self::get_string_value($item_data, 'content');
                // $rewrite_enabled is now set in run_content_import directly into $item_data.
                $rewrite_enabled = self::get_bool_value($item_data, 'rewrite', true); 
                $rewritten_content_final = '';

                if ($rewrite_enabled && class_exists('Booster_AI')) {
                    Booster_Logger::log("[Booster CM] Original content length for '{$title}': " . strlen($content));
                    $retries = 0;
                    $backoff_seconds = 1;
                    $error_message = '';

                    while ($retries < 3) {
                        try {
                            Booster_Logger::log("[Booster CM] [ATTEMPT #" . ($retries + 1) . "] AI rewrite for '{$title}'. Content length: " . strlen($content));
                            $current_rewritten_content = Booster_AI::rewrite_content($content);

                            if (!empty($current_rewritten_content) && strlen($current_rewritten_content) > (strlen($content) * 0.5)) {
                                Booster_Logger::log("[Booster CM] [SUCCESS] Content for '{$title}' rewritten on attempt " . ($retries + 1) . ". New length: " . strlen($current_rewritten_content));
                                $rewritten_content_final = $current_rewritten_content;
                                break;
                            } else {
                                $error_message = !empty($current_rewritten_content) ? 'Rewritten content too short or empty.' : 'AI returned empty content.';
                                Booster_Logger::log("[Booster CM] [WARNING] AI rewrite for '{$title}' attempt " . ($retries + 1) . " - {$error_message}");
                            }
                        } catch (Exception $e) {
                            $error_message = $e->getMessage();
                            Booster_Logger::log("[Booster CM] [ERROR] AI rewrite for '{$title}' attempt " . ($retries + 1) . " failed: " . $error_message);
                            if (strpos($error_message, 'rate limit') !== false) {
                                $wait_time_rate_limit = $backoff_seconds * pow(2, $retries);
                                Booster_Logger::log("[Booster CM] [RATE LIMIT] Backing off for {$wait_time_rate_limit}s for '{$title}'");
                                sleep($wait_time_rate_limit);
                            }
                        }
                        $retries++;
                        if ($retries < 3) {
                            $wait_time = $backoff_seconds * pow(2, $retries -1); // Corrected for 0-indexed retry
                            Booster_Logger::log("[Booster CM] [RETRY] Waiting {$wait_time}s before attempt " . ($retries + 1) . " for '{$title}'");
                            sleep($wait_time);
                        }
                    }

                    if (!empty($rewritten_content_final)) {
                        $content = $rewritten_content_final;
                    } else {
                        Booster_Logger::log("[Booster CM] [WARNING] All AI rewrite attempts failed for '{$title}'; using original content. Last error: " . $error_message);
                    }
                } else {
                    $reason = !$rewrite_enabled ? 'disabled in provider config' : 'Booster_AI class not found';
                    Booster_Logger::log("[Booster CM] AI rewrite skipped for post: '{$title}' ({$reason}).");
                }


                $post_type     = self::get_string_value($item_data, 'post_type', 'post');
                $category_name = self::get_string_value($item_data, 'category');
                $image_url     = self::get_string_value($item_data, 'image');

                /**
                 * Prepare post data for wp_insert_post.
                 * @var array{
                 *   post_title: string,
                 *   post_content: string,
                 *   post_status: string,
                 *   post_type: string,
                 *   post_category?: array<int, int>,
                 *   meta_input: array<string, string>
                 * } $post_data_for_wp
                 */
                $post_data_for_wp = [
                    'post_title'    => sanitize_text_field($title),
                    'post_content'  => class_exists('Booster_Affiliate_Manager') ? Booster_Affiliate_Manager::process_content($content) : $content,
                    'post_status'   => 'draft',
                    'post_type'     => $post_type,
                    'meta_input'    => [
                        '_booster_source_url'   => esc_url_raw($url),
                        '_booster_content_hash' => $hash,
                        '_booster_api'          => sanitize_text_field($provider_id),
                        '_booster_image_url'    => esc_url_raw($image_url),
                        // '_booster_rewrite_status' => $rewrite_enabled ? (!empty($rewritten_content_final) ? 'success' : 'failed') : 'skipped',
                    ],
                ];
                 if ($rewrite_enabled) {
                    $post_data_for_wp['meta_input']['_booster_rewrite_status'] = !empty($rewritten_content_final) ? 'success' : 'failed';
                 } else {
                    $post_data_for_wp['meta_input']['_booster_rewrite_status'] = 'skipped';
                 }


                /** @var array<int, int> $category_ids */
                $category_ids = $this->map_category_to_ids($category_name);
                if (!empty($category_ids)) {
                    $post_data_for_wp['post_category'] = $category_ids;
                }

                $post_id_or_error = wp_insert_post($post_data_for_wp, true);

                if (is_wp_error($post_id_or_error)) {
                    throw new Exception("Failed to insert post '{$title}': " . $post_id_or_error->get_error_message());
                }
                $post_id = (int) $post_id_or_error; // Cast to int for safety, though wp_insert_post returns int on success

                if (class_exists('Booster_Utils')) {
                    Booster_Utils::process_post($post_id, $image_url, $content); // $content here is potentially rewritten
                } else {
                    Booster_Logger::log("[Booster CM] Booster_Utils class not found. Skipping post processing for post ID {$post_id}.");
                }

                if (class_exists('Booster_Trend_Matcher')) {
                    $keywords_meta_value = get_post_meta($post_id, '_booster_keywords', true);
                    $keywords_str = is_string($keywords_meta_value) ? $keywords_meta_value : '';
                    $keywords = !empty($keywords_str) ? array_map('trim', explode(',', $keywords_str)) : [];

                    if (!empty($keywords)) {
                        $trending_keywords = Booster_Trend_Matcher::get_trending_keywords();
                        $match_score = Booster_Trend_Matcher::calculate_match_score($keywords, $trending_keywords);
                        update_post_meta($post_id, '_booster_trend_score', (string) $match_score); // Store score as string for meta
                        Booster_Logger::log("[Booster CM] Imported '{$title}' (ID: {$post_id}) with trend match score: {$match_score}%");

                        if ($match_score >= 60) { // Make 60 a constant or configurable if needed
                            wp_set_post_tags($post_id, ['🔥 Trending'], true);
                        }
                    }
                } else {
                     Booster_Logger::log("[Booster CM] Booster_Trend_Matcher class not found. Skipping trend score for post ID {$post_id}.");
                }

                $created_count++;
                Booster_Logger::log("[Booster CM] Created post ID {$post_id} - '{$title}'");

            } catch (Exception $e) {
                $item_data_log = substr(print_r($item_data, true), 0, 1000);
                Booster_Logger::log("[Booster CM] Error creating post: " . $e->getMessage());
                Booster_Logger::log("[Booster CM] Item data: " . $item_data_log);
                Booster_Logger::log("[Booster CM] Provider ID: " . $provider_id);
            }
        }
        return $created_count;
    }

    /**
     * Check if a post with the given content hash already exists.
     *
     * @param string $hash The MD5 hash of the content.
     * @return bool True if post exists, false otherwise.
     */
    private function post_exists_by_hash(string $hash): bool {
        $query_args = [
            'post_type'      => 'any', // Consider specific post types if 'any' is too broad
            'post_status'    => 'any', // Consider specific statuses
            'fields'         => 'ids',
            'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                [
                    'key'   => '_booster_content_hash',
                    'value' => $hash,
                ]
            ],
            'posts_per_page' => 1,
            'no_found_rows'  => true, // Optimization for query
            'suppress_filters' => true, // Optimization
        ];
        $query = new WP_Query($query_args);
        return $query->have_posts();
    }

    /**
     * Creates a category if it doesn't exist and returns an array of term IDs.
     *
     * @param string $category_name The name of the category.
     * @return array<int, int> An array containing the term ID(s), or empty if error/no category.
     */
    private function map_category_to_ids(string $category_name): array {
        $trimmed_category_name = trim($category_name);
        if (empty($trimmed_category_name)) {
            return [];
        }

        // Check if term exists
        $term_object = term_exists($trimmed_category_name, 'category');

        if (is_array($term_object) && isset($term_object['term_id'])) {
            return [(int) $term_object['term_id']];
        }
        if (is_int($term_object) && $term_object > 0) { // term_exists can return int term_id for existing term
            return [$term_object];
        }

        // Term does not exist, try to create it
        $new_term_result = wp_insert_term($trimmed_category_name, 'category');
        if (is_array($new_term_result) && isset($new_term_result['term_id'])) {
            return [(int) $new_term_result['term_id']];
        }

        // Log error if creation failed
        if (is_wp_error($new_term_result)) {
            Booster_Logger::log("[Booster CM] Failed to create category '{$trimmed_category_name}': " . $new_term_result->get_error_message());
        } else {
            Booster_Logger::log("[Booster CM] Failed to create category '{$trimmed_category_name}': Unknown error during wp_insert_term.");
        }
        return [];
    }
}