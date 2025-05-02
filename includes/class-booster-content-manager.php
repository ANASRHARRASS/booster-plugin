<?php

/**
 * Fired during plugin activation
 *
 * @link       https://shippingsmile.com/anasrharrass
 * @since      1.0.0
 *
 * @package    Booster
 * @subpackage Booster/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Booster
 * @subpackage Booster/includes
 * @author     anas <anas@shippingsmile.com>
 */

/**
 * This class:
 *
 *
 *  Loads the configured providers (from the plugin settings)    
 * Loops through each API/endpoint combo
 *Calls Booster_API_Runner to fetch data
 *Calls Booster_API_Runner to fetch data 
 *Sends the data to Booster_Parser
 *
 *Creates new posts (deduplicated by hash)
 *
 */

 class Booster_Content_Manager {

	private $plugin_name;
	private $version;

	public function __construct($plugin_name, $version) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Main importer logic: loops through all provider APIs, fetches and posts.
	 */
	public function run_content_import(): int {
		$providers = get_option('booster_provider_list', []);
		if (empty($providers)) return 0;

		$total_created = 0;

		foreach ($providers as $provider) {
			$api_id      = $provider['api'] ?? '';
			$endpoint_id = $provider['endpoint'] ?? '';
			$type        = $provider['type'] ?? 'news';

			if (empty($api_id) || empty($endpoint_id)) {
				Booster_Logger::log("Skipped provider due to missing API or endpoint.");
				continue;
			}

			$raw_data = Booster_API_Runner::fetch_from_wpgetapi($api_id, $endpoint_id);

			if (!$raw_data) {
				Booster_Logger::log("No response from API: $api_id / $endpoint_id");
				continue;
			}

			$normalized_data = Booster_Parser::normalize($raw_data, $type);
			//inject the rewrite flag from the provider row
			foreach ($normalized_data as &$item) {
				$item['rewrite'] = $provider['rewrite'] ?? true;
			}
			unset($item); // Unset reference to avoid issues

			$total_created += $this->create_posts($normalized_data, $api_id);
		}
		Booster_Logger::log("Raw API Response from {$api_id} / {$endpoint_id}: " . print_r($raw_data, true));
		Booster_Logger::log("Normalized data from  {$api_id} : " . print_r($normalized_data, true));

		return $total_created;
	}

	/**
	 * Creates WordPress posts from normalized items.
	 *
	 * @param array $items
	 * @param string $provider_id
	 * @return int
	 */
	private function create_posts(array $items, string $provider_id = ''): int {
		$created = 0;
	
		if (empty($items)) {
			Booster_Logger::log("No items to process from provider: $provider_id");
			return 0;
		}
	
		foreach ($items as $item) {
			try {
				$hash = md5(strtolower(trim($item['title'] . $item['url'])));
	
				if ($this->post_exists_by_hash($hash)) {
					Booster_Logger::log("Skipped duplicate: " . ($item['title'] ?? 'Unknown'));
					continue;
				}
	
				// ✨ AI Rewrite
				$content = $item['content'] ?? '';
				
	
                // ✨ AI Rewrite section
				$rewrite_enabled = $item['rewrite'] ?? true;
				if ($rewrite_enabled && class_exists('Booster_AI')) {
				    // debug before the rewrite
				    Booster_Logger::log("Original content length: " . strlen($content));
                    $retries = 0;
                    $rewritten = '';
                    $backoff_seconds = 1;
                    $error_message = '';
                    
                    while ($retries < 3 && empty($rewritten)) {
                        try {
                            Booster_Logger::log("[ATTEMPT #" . ($retries + 1) . "] Attempting AI rewrite... Content length: " . strlen($content));
                            $rewritten = Booster_AI::rewrite_content($content);
                            
                            // Validate rewritten content
                            if (!empty($rewritten) && strlen($rewritten) > (strlen($content) * 0.5)) {
                                Booster_Logger::log("[SUCCESS] Content rewritten successfully on attempt " . ($retries + 1) . 
                                    ". New length: " . strlen($rewritten));
                                $content = $rewritten;
                                break;
                            } else {
                                $rewritten = ''; // Reset if validation fails
                                $error_message = 'Content too short';
                                Booster_Logger::log("[WARNING] Rewritten content too short, retrying...");
                            }
                            
                            $retries++;
                            if (empty($rewritten) && $retries < 3) {
                                $wait_time = $backoff_seconds * pow(2, $retries - 1);
                                Booster_Logger::log("[RETRY] Waiting {$wait_time}s before attempt " . ($retries + 1));
                                sleep($wait_time);
                            }
                        } catch (Exception $e) {
                            $error_message = $e->getMessage();
                            Booster_Logger::log("[ERROR] AI rewrite attempt " . ($retries + 1) . " failed: " . $error_message);
                            
                            // Handle specific error types
                            if (strpos($error_message, 'rate limit') !== false) {
                                $wait_time = $backoff_seconds * pow(2, $retries);
                                Booster_Logger::log("[RATE LIMIT] Backing off for {$wait_time}s");
                                sleep($wait_time);
                            }
                            
                            $retries++;
                            if ($retries < 3) {
                                $backoff_seconds *= 2;
                            }
                        }
                    }
                    
                    if (empty($rewritten)) {
                        Booster_Logger::log("[WARNING] All rewrite attempts failed; using original content. Last error: " . $error_message);
                    }
                } else {
                    Booster_Logger::log("Skipping AI rewrite for post: " . ($item['title'] ?? 'Untitled'));
                }
	
				// 📝 Insert Post
				$post_id = wp_insert_post([
					'post_title'    => sanitize_text_field($item['title']),
					'post_content'  => Booster_Affiliate_Manager::process_content($content),
					'post_status'   => 'draft',
					'post_type'     => $item['post_type'] ?? 'post',
					'post_category' => $this->map_category($item['category']),
					'meta_input'    => [
						'_booster_source_url'   => esc_url_raw($item['url']),
						'_booster_content_hash' => $hash,
						'_booster_api'          => sanitize_text_field($provider_id),
						'_booster_image_url'    => esc_url_raw($item['image'] ?? ''),
					],
				]);
	
				if (is_wp_error($post_id)) {
					throw new Exception($post_id->get_error_message());
				}
	
				// 🖼️ Featured image + tags
				Booster_Utils::process_post($post_id, $item['image'] ?? '', $content);
	
				// 🔥 Trend Score
				$keywords_str = get_post_meta($post_id, '_booster_keywords', true);
				$keywords = $keywords_str ? explode(',', $keywords_str) : [];
	
				if (!empty($keywords)) {
					$trends = Booster_Trend_Matcher::get_trending_keywords();
					$score = Booster_Trend_Matcher::calculate_match_score($keywords, $trends);
					update_post_meta($post_id, '_booster_trend_score', $score);
					Booster_Logger::log("Imported '{$item['title']}' with trend match score: {$score}%");
	
					if ($score >= 60) {
						wp_set_post_tags($post_id, ['🔥 Trending'], true);
					}
				}
	
				$created++;
				Booster_Logger::log("Created post ID {$post_id} - " . get_the_title($post_id));
	
			} catch (Exception $e) {
				Booster_Logger::log("Error creating post: " . $e->getMessage());
				Booster_Logger::log("Item data: " . print_r($item, true));
				Booster_Logger::log("Provider ID: " . $provider_id);
				continue;
			}
		}
	
		return $created;
	}
	

	/**
	 * Prevent duplicate posts via content hash.
	 */
	private function post_exists_by_hash($hash): bool {
		$query = new WP_Query([
			'post_type'  => 'post',
			'fields'     => 'ids',
			'meta_query' => [[
				'key'   => '_booster_content_hash',
				'value' => $hash,
			]],
			'posts_per_page' => 1,
		]);

		return $query->have_posts();
	}

	/**
	 * Creates category if needed, and returns term ID.
	 */
	private function map_category($category): array {
		if (empty($category)) return [];

		$term = term_exists($category, 'category');
		if (!$term) {
			$term = wp_insert_term($category, 'category');
		}

		return is_wp_error($term) ? [] : [$term['term_id']];
	}
}
