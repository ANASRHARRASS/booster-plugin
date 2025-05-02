<?php


class Booster_API_Runner {

    /**
     * Features:
     *Works with any API configured through WPGetAPI
     *
     *Safe handling of non-function availability
     *
     *Logs errors (visible in error_log() or via a future admin log panel)
     * Fetch data from a configured WPGetAPI endpoint.
     *
     * @param string $api_id       WPGetAPI API group ID (e.g., 'newsapi')
     * @param string $endpoint_id  WPGetAPI endpoint ID (e.g., 'top-headlines')
     * @return array|null          Raw API response (usually decoded JSON)
     */
    
    public static function fetch_from_wpgetapi($api_id, $endpoint_id, $args = []): ?array {
        $api_key = get_option('booster_api_key_newsapi');
        if (empty($api_key)) {
            Booster_Logger::log("[Booster] Missing API key for NewsAPI.");
            return null; // Fixed
        }
    
        if (!function_exists('wpgetapi_endpoint')) {
            Booster_Logger::log('[Booster] WPGetAPI not available.');
            return null;
        }
    
        $default_args = [
            'args'  => array_merge(['timestamp' => time()], $args),
            'debug' => false,
        ];
    
        try {
            $response = wpgetapi_endpoint($api_id, $endpoint_id, $default_args);
    
            if (empty($response) || !is_array($response)) {
                throw new \RuntimeException("Empty or invalid response from $api_id/$endpoint_id.");
            }
    
            // Handle pagination if applicable
            if (isset($response['nextPage'])) {
                $nextPageArgs = array_merge($args, ['page' => $response['nextPage']]);
                $nextPageData = self::fetch_from_wpgetapi($api_id, $endpoint_id, $nextPageArgs);
                $response = array_merge($response, $nextPageData);
            }
    
            return $response;
        } catch (\Throwable $e) {
            Booster_Logger::log('[Booster] Error fetching from WPGetAPI: ' . $e->getMessage());
            return null;
        }
    }
    
}
