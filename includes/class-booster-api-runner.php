<?php

declare(strict_types=1); // Good practice

class Booster_API_Runner {

    /**
     * Features:
     * - Works with any API configured through WPGetAPI
     * - Safe handling of non-function availability
     * - Logs errors (visible in error_log() or via a future admin log panel)
     *
     * Fetch data from a configured WPGetAPI endpoint.
     *
     * @param string $api_id       WPGetAPI API group ID (e.g., 'newsapi').
     * @param string $endpoint_id  WPGetAPI endpoint ID (e.g., 'top-headlines').
     * @param array<string, mixed> $args Optional. Additional arguments to pass to the WPGetAPI endpoint.
     *                                   These might include query parameters, headers, etc.
     *                                   Example: ['query_parameters' => ['country' => 'us'], 'page' => 2].
     * @return array<mixed>|null   Raw API response (usually decoded JSON as an associative array or a list of items),
     *                             or null on failure. The exact structure depends on the API.
     */
    public static function fetch_from_wpgetapi(string $api_id, string $endpoint_id, array $args = []): ?array {
        if (!function_exists('wpgetapi_endpoint')) {
            Booster_Logger::log('[Booster API Runner] WPGetAPI plugin function wpgetapi_endpoint() is not available.');
            return null;
        }

        // Prepare arguments for wpgetapi_endpoint
        // The 'args' key within $wpgetapi_args is specifically for query parameters by WPGetAPI convention.
        // Other top-level keys in $wpgetapi_args might be for headers, body, etc., depending on WPGetAPI.
        $wpgetapi_args = [
            'args'  => array_merge(['timestamp' => time()], $args['query_parameters'] ?? $args), // Prioritize 'query_parameters' if provided, else use $args directly for backwards compatibility or simpler use cases.
            'debug' => false, // Set to true for debugging WPGetAPI calls
        ];

        // If $args contains other WPGetAPI specific keys like 'headers', 'body_json', merge them too.
        // Example: if $args = ['query_parameters' => [...], 'headers' => [...]]
        foreach ($args as $key => $value) {
            if ($key !== 'query_parameters' && !isset($wpgetapi_args[$key])) {
                $wpgetapi_args[$key] = $value;
            }
        }


        Booster_Logger::log(sprintf("[Booster API Runner] Fetching from WPGetAPI: API ID='%s', Endpoint ID='%s', Args: %s", $api_id, $endpoint_id, wp_json_encode($wpgetapi_args)));

        try {
            /**
             * The structure of $response from wpgetapi_endpoint can be highly variable.
             * It's typically an associative array if the JSON response is an object,
             * or a numerically indexed array if the JSON response is an array.
             * @var mixed $response_raw
             */
            $response_raw = wpgetapi_endpoint($api_id, $endpoint_id, $wpgetapi_args);

            if (empty($response_raw) || !is_array($response_raw)) {
                $response_type = gettype($response_raw);
                Booster_Logger::log("[Booster API Runner] Empty or invalid response from {$api_id}/{$endpoint_id}. Expected array, got {$response_type}. Response: " . substr(print_r($response_raw, true), 0, 500));
                // Consider if an empty array is a valid successful response for some APIs.
                // If so, you might only throw an exception if !is_array.
                // For now, assuming empty array is also problematic for subsequent processing.
                return null; // Return null instead of throwing, to match method signature's ?array
            }

            // The response is confirmed to be a non-empty array here.
            /** @var array<mixed> $response */
            $response = $response_raw;


            // --- Basic Pagination Handling Example (adjust to your API's specific pagination method) ---
            // This is a very generic example. Real pagination depends heavily on:
            // 1. How the API indicates there's a next page (e.g., 'nextPage', 'next_page_token', 'meta.pagination.next_url', Link header).
            // 2. How you pass the next page identifier (e.g., 'page' parameter, a specific token).
            // 3. How you merge results (append to a list, merge objects, etc.).

            // Example: Simple "page" number based pagination
            // And assuming the main content is under a key like 'items' or 'data'
            $pagination_key_indicator = 'nextPage'; // e.g., API returns 'nextPage': 2
            $pagination_param_name = 'page';        // e.g., you send 'page=2' in next request
            $items_key = null;                      // Key holding the actual list of items, e.g., 'articles', 'data'. Determine this if merging lists.

            // Determine $items_key heuristically if not known (this is fragile)
            if (isset($response['articles']) && is_array($response['articles'])) $items_key = 'articles';
            elseif (isset($response['items']) && is_array($response['items'])) $items_key = 'items';
            elseif (isset($response['data']) && is_array($response['data'])) $items_key = 'data';
            // If $items_key remains null, we might be dealing with a response that is directly a list,
            // or pagination logic needs to be more specific.

            if (isset($response[$pagination_key_indicator])) {
                $next_page_value = $response[$pagination_key_indicator];
                Booster_Logger::log(sprintf("[Booster API Runner] Pagination: Next page indicator '%s' found with value '%s' for %s/%s.", $pagination_key_indicator, $next_page_value, $api_id, $endpoint_id));

                $next_page_args = $args; // Start with original args
                if (isset($next_page_args['query_parameters']) && is_array($next_page_args['query_parameters'])) {
                    $next_page_args['query_parameters'][$pagination_param_name] = $next_page_value;
                } else {
                    $next_page_args[$pagination_param_name] = $next_page_value;
                }


                $next_page_data_response = self::fetch_from_wpgetapi($api_id, $endpoint_id, $next_page_args);

                if (is_array($next_page_data_response) && !empty($next_page_data_response)) {
                    Booster_Logger::log(sprintf("[Booster API Runner] Pagination: Successfully fetched next page data for %s/%s.", $api_id, $endpoint_id));
                    // Intelligent merging:
                    if ($items_key && isset($response[$items_key]) && is_array($response[$items_key]) && isset($next_page_data_response[$items_key]) && is_array($next_page_data_response[$items_key])) {
                        // Merge the lists of items
                        $response[$items_key] = array_merge($response[$items_key], $next_page_data_response[$items_key]);
                        // Update other meta-data if necessary (e.g., total_results, current_page)
                        // For instance, remove the 'nextPage' indicator from the final merged response if it's no longer valid
                        if (isset($next_page_data_response[$pagination_key_indicator])) {
                             $response[$pagination_key_indicator] = $next_page_data_response[$pagination_key_indicator];
                        } else {
                             unset($response[$pagination_key_indicator]);
                        }
                        Booster_Logger::log(sprintf("[Booster API Runner] Pagination: Merged items under key '%s'. Total items now: %d", $items_key, count($response[$items_key])));
                    } elseif (array_keys($response) === range(0, count($response) - 1) && array_keys($next_page_data_response) === range(0, count($next_page_data_response) - 1)) {
                        // If both current and next page responses are simple lists (numerically indexed arrays)
                        $response = array_merge($response, $next_page_data_response);
                         Booster_Logger::log(sprintf("[Booster API Runner] Pagination: Merged direct list responses. Total items now: %d", count($response)));
                    } else {
                        // Fallback: a more generic merge (might overwrite keys, be careful)
                        // This is often NOT what you want for list data.
                        // $response = array_merge($response, $next_page_data_response);
                        Booster_Logger::log(sprintf("[Booster API Runner] Pagination: Could not determine specific item list for merging. Next page data might not be fully integrated."));
                    }
                } else {
                     Booster_Logger::log(sprintf("[Booster API Runner] Pagination: No data or invalid data from next page fetch for %s/%s.", $api_id, $endpoint_id));
                }
            }
            // --- End Basic Pagination Handling Example ---

            return $response;

        } catch (\Throwable $e) { // Catch any throwable (Error or Exception)
            Booster_Logger::log('[Booster API Runner] Exception during WPGetAPI fetch: ' . $e->getMessage() . ' for API: ' . $api_id . ', Endpoint: ' . $endpoint_id . ' Trace: ' . $e->getTraceAsString());
            return null;
        }
    }
}