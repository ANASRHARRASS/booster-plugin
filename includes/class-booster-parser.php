<?php
/**
 * Booster_Parser Class
 *
 * This class is responsible for normalizing different API responses (news, products, crypto, etc.)
 * into a consistent array format that the Booster plugin can use to create WordPress posts.
 *
 * @since      1.0.0
 * @package    Booster
 * @subpackage Booster/includes
 * @author     anas
 */

 class Booster_Parser {

    /**
     * Normalize the raw API response to a standard Booster-friendly structure.
     */
    public static function normalize($response, $type): array {
        if (!is_array($response)) {
            return [];
        }

        Booster_Logger::log("[Booster_Parser] Raw response received for type: {$type}");

        switch ($type) {
            case 'news':
                return self::parse_news($response, $type);
            case 'product':
                return self::parse_products($response, $type);
            case 'crypto':
                return self::parse_crypto($response, $type);
            default:
                return self::dynamic_parser($response, $type);
        }
    }

    /**
     * Normalize a response from a News API.
     */
    public static function parse_news(array $response, string $type): array {
        $results = [];
        $articles = $response['articles'] ?? $response['news'] ?? [];

        foreach ($articles as $item) {
            $title = $item['title'] ?? '';
            $content = $item['content'] ?? $item['description'] ?? '';
            $url = $item['url'] ?? '';
            $image = $item['urlToImage'] ?? $item['image'] ?? '';
            $source = $item['source']['name'] ?? 'Unknown Source';
            $publishedAt = $item['publishedAt'] ?? $item['published'] ?? '';

            if (empty($title) || empty($url)) {
                Booster_Logger::log("[Booster_Parser] Skipped news item: Missing title or URL.");
                continue;
            }

            $results[] = [
                'title'     => $title,
                'content'   => self::expand_content($title, self::clean_content($content)),
                'url'       => $url,
                'image'     => $image,
                'category'  => $item['category'][0] ?? 'News',
                'source'    => $source,
                'published' => $publishedAt,
                'post_type' => 'post',
            ];
        }

        Booster_Logger::log("[Booster_Parser] Parsed " . count($results) . " valid news items.");
        return $results;
    }


    private static function clean_content(string $content): string {
        $content = preg_replace('/\[\+\d+\schars\]/', '', $content);
        $content = preg_replace('/\s+/', ' ', $content);
        return trim($content);
    }

    private static function expand_content(string $title, string $content, string $description = ''): string {
        if (str_word_count(strip_tags($content)) < 100) {
            Booster_Logger::log("[Booster_Parser] Expanding short content for '{$title}'.");

            if (!empty($description) && strpos($content, $description) === false) {
                $content .= "\n\n" . trim($description);
            }

            if (str_word_count(strip_tags($content)) < 100 && !empty($title)) {
                $content = '<strong>' . esc_html($title) . '</strong>' . "\n\n" . $content;
            }

            $content .= "\n\nStay tuned for more updates!";
        }

        return $content;
    }

    private static function parse_products(array $response, string $type): array {
        $results = [];
        $items = $response['items'] ?? $response;

        foreach ($items as $item) {
            if (empty($item['name'])) continue;

            $results[] = [
                'title'     => $item['name'],
                'content'   => self::clean_content($item['description'] ?? $item['content'] ?? ''),
                'url'       => $item['affiliate_link'] ?? $item['url'] ?? '',
                'image'     => $item['urlToImage'] ?? '',
                'category'  => $item['category'] ?? 'Products',
                'provider'  => 'productapi',
                'post_type' => 'product',
            ];
        }
        Booster_Logger::log("[Booster_Parser] Parsed " . count($results) . " product items.");

        return $results;
    }

    private static function parse_crypto(array $response, string $type): array {
        $results = [];
        $coins = $response['data'] ?? $response;

        foreach ($coins as $coin) {
            if (empty($coin['name']) || empty($coin['symbol'])) continue;

            $results[] = [
                'title'     => $coin['name'] . ' (' . strtoupper($coin['symbol']) . ')',
                'content'   => 'Current price: $' . ($coin['price_usd'] ?? 'N/A'),
                'url'       => $coin['website'] ?? '',
                'image'     => $coin['icon'] ?? '',
                'category'  => 'Crypto',
                'provider'  => 'coinapi',
                'post_type' => 'post',
            ];
        }
        Booster_Logger::log("[Booster_Parser] Parsed " . count($results) . " crypto items.");

        return $results;
    }

    private static function dynamic_parser(array $response, string $type): array {
        $results = [];
        $items = self::find_list($response);
        if (empty($items)) return [];

        foreach ($items as $item) {
            if (empty($item['title']) && empty($item['name'])) continue;

            $results[] = [
                'title'     => $item['title'] ?? $item['name'] ?? 'Untitled',
                'content'   => self::clean_content($item['description'] ?? $item['summary'] ?? ''),
                'url'       => $item['url'] ?? $item['link'] ?? '',
                'image'     => $item['image'] ?? $item['icon'] ?? $item['urlToImage'] ?? '',
                'category'  => $item['category'] ?? 'Uncategorized',
                'provider'  => $item['source']['name'] ?? $item['provider'] ?? '',
                'post_type' => 'post',
            ];
        }
        Booster_Logger::log("[Booster_Parser] Parsed " . count($results) . " dynamic items.");

        return $results;
    }

    private static function find_list(array $array): array {
        foreach ($array as $value) {
            if (is_array($value) && array_keys($value) === range(0, count($value) - 1)) {
                return $value;
            }
        }
        return [];
    }
}