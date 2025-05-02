<?php
class Booster_News_Fetcher {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function fetch_news() {
        if (!function_exists('wpgetapi_endpoint')) {
            error_log('Booster: WPGetAPI not active');
            return [];
        }
        //debug : print current configuration
        error_log('Booster api config: ' . print_r([
            'endpont_id' => get_option('booster_wpgetapi_id'),
            'api_id' => 'newsapi' //should match wpgetapi setup
        ], true));
        
        $data = wpgetapi_endpoint(
            'newsapi', //this should match the api id in wpgetapi
            get_option('booster_wpgetapi_id'), // endpoint id
            [
               'debug' => true,
               'args'  => [
                'cache' => 'no', // for wpgetapi, this is the cache option
                'timestamp' => time(), // force fresh fetch
                //newsapi.org retrieves the latest news articles
                'country' => 'us',
                'category' => 'business',
                'apiKey' => get_option('booster_news_api_key'), // api key

               ]            
            ]
        );
        //debug : print current configuration
        error_log('Booster api response: ' . print_r($data, true));

        return is_array($data) ? $data : [];
    }
}