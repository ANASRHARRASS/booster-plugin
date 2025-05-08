<?php

declare(strict_types=1); // Good practice

class Booster_News_Fetcher {

    // Removed unused $plugin_name and $version properties and constructor

    /**
     * Fetches news from the configured NewsAPI endpoint via WPGetAPI.
     *
     * @return array<mixed> The API response data as an array, or an empty array on failure.
     *                      The actual structure of the returned array depends on the NewsAPI response.
     */
    public function fetch_news(): array {
        if (!function_exists('wpgetapi_endpoint')) {
            $log_msg = '[Booster News Fetcher] WPGetAPI plugin function wpgetapi_endpoint() is not available.';
            if (is_callable(['Booster_Logger', 'log'])) { // MODIFIED
                Booster_Logger::log($log_msg);
            } else {
                error_log($log_msg);
            }
            return [];
        }

        // Retrieve options and ensure they are strings
        $endpoint_id_option = get_option('booster_wpgetapi_id');
        $endpoint_id = is_string($endpoint_id_option) ? $endpoint_id_option : '';

        $api_key_option = get_option('booster_news_api_key');
        $api_key = is_string($api_key_option) ? $api_key_option : '';

        // Validate essential configuration
        if (empty($endpoint_id)) {
            $log_msg = '[Booster News Fetcher] WPGetAPI Endpoint ID for NewsAPI is not configured (option: booster_wpgetapi_id).';
            if (is_callable(['Booster_Logger', 'log'])) Booster_Logger::log($log_msg); else error_log($log_msg); // MODIFIED
            return [];
        }
        if (empty($api_key)) {
            $log_msg = '[Booster News Fetcher] NewsAPI Key is not configured (option: booster_news_api_key).';
            if (is_callable(['Booster_Logger', 'log'])) Booster_Logger::log($log_msg); else error_log($log_msg); // MODIFIED
            return [];
        }

        $wpgetapi_api_id = 'newsapi'; // This should match the API ID you set up in WPGetAPI for NewsAPI

        $request_args = [
            'debug' => false,
            'args'  => [
                'cache'     => 'no',
                'timestamp' => time(),
                'country'   => 'us',
                'category'  => 'business',
                'apiKey'    => $api_key,
            ]
        ];

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_msg_config = sprintf(
                '[Booster News Fetcher DEBUG] WPGetAPI Config: API ID=\'%s\', Endpoint ID=\'%s\', Args: %s',
                $wpgetapi_api_id,
                $endpoint_id,
                wp_json_encode($request_args['args'])
            );
            if (is_callable(['Booster_Logger', 'log'])) Booster_Logger::log($log_msg_config); else error_log($log_msg_config); // MODIFIED
        }

        /** @var mixed $data Raw response from wpgetapi_endpoint */
        $data = wpgetapi_endpoint(
            $wpgetapi_api_id,
            $endpoint_id,
            $request_args
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
             $log_msg_response = '[Booster News Fetcher DEBUG] WPGetAPI Response: ' . substr(print_r($data, true), 0, 1000);
             if (is_callable(['Booster_Logger', 'log'])) Booster_Logger::log($log_msg_response); else error_log($log_msg_response); // MODIFIED
        }

        if (is_wp_error($data)) {
            $error_message = $data->get_error_message();
            $log_msg = sprintf("[Booster News Fetcher] WPGetAPI Error: %s (API: %s, Endpoint: %s)", $error_message, $wpgetapi_api_id, $endpoint_id);
            if (is_callable(['Booster_Logger', 'log'])) Booster_Logger::log($log_msg); else error_log($log_msg); // MODIFIED
            return [];
        }

        if (!is_array($data)) {
            $response_type = gettype($data);
            $log_msg = sprintf("[Booster News Fetcher] WPGetAPI returned non-array data. Type: %s. (API: %s, Endpoint: %s)", $response_type, $wpgetapi_api_id, $endpoint_id);
            if (is_callable(['Booster_Logger', 'log'])) Booster_Logger::log($log_msg); else error_log($log_msg); // MODIFIED
            return [];
        }

        return $data;
    }
}