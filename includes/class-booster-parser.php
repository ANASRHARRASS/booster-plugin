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

declare(strict_types=1);

class Booster_Parser {

    /**
     * Normalize the raw API response to a standard Booster-friendly structure.
     *
     * @param array<string, mixed> $response The raw API response.
     * @param string $type The type of content (e.g., 'news', 'product').
     * @return array<int, array<string, mixed>> Normalized items.
     */
    public static function normalize(array $response, string $type): array {
        Booster_Logger::log("[Booster_Parser] Raw response received for type: " . $type);

        switch ($type) {
            case 'news':
                return self::parse_news($response);
            case 'product':
                return self::parse_products($response);
            case 'crypto':
                return self::parse_crypto($response);
            default:
                return self::dynamic_parser($response);
        }
    }

    /**
     * Safely get a string value from an array, providing a default if not found or not a string/scalar.
     *
     * @param array<mixed> $array The array to search in.
     * @param string|int $key The key to look for.
     * @param string $default The default value to return.
     * @return string
     */
    private static function get_string_value(array $array, $key, string $default = ''): string {
        if (isset($array[$key]) && (is_string($array[$key]) || is_numeric($array[$key]) || is_bool($array[$key]))) {
            return (string) $array[$key];
        }
        return $default;
    }

    /**
     * Safely get a string value from a nested array structure.
     *
     * @param array<mixed> $array The array to search in.
     * @param array<string|int> $keys Path of keys to the desired value.
     * @param string $default The default value to return.
     * @return string
     */
    private static function get_nested_string_value(array $array, array $keys, string $default = ''): string {
        $current = $array;
        foreach ($keys as $key) {
            if (is_array($current) && isset($current[$key])) {
                $current = $current[$key];
            } else {
                return $default;
            }
        }
        if (is_string($current) || is_numeric($current) || is_bool($current)) {
            return (string) $current;
        }
        return $default;
    }


    /**
     * Normalize a response from a News API.
     *
     * @param array<string, mixed> $response The API response.
     * @return array<int, array<string, mixed>> Normalized news items.
     */
    public static function parse_news(array $response): array {
        $results = [];
        /** @var array<int, mixed> $articles Initially mixed, elements will be checked to be arrays. */
        $articles = [];

        if (isset($response['articles']) && is_array($response['articles'])) {
            $articles = $response['articles'];
        } elseif (isset($response['news']) && is_array($response['news'])) {
            $articles = $response['news'];
        // FIXED: Removed redundant is_array($response) as $response is already type-hinted as array
        } elseif (!empty($response) && array_keys($response) === range(0, count($response) - 1)) {
            // Handle cases where the response itself is a list of articles
            $articles = $response;
        }


        foreach ($articles as $item) {
            if (!is_array($item)) { // This check remains crucial
                Booster_Logger::log("[Booster_Parser] Skipped non-array news item: " . gettype($item));
                continue;
            }

            $title = self::get_string_value($item, 'title');
            $content = self::get_string_value($item, 'content', self::get_string_value($item, 'description'));
            $url = self::get_string_value($item, 'url');
            $image = self::get_string_value($item, 'urlToImage', self::get_string_value($item, 'image'));
            $source = self::get_nested_string_value($item, ['source', 'name'], 'Unknown Source');
            $publishedAt = self::get_string_value($item, 'publishedAt', self::get_string_value($item, 'published'));

            if (empty($title) || empty($url)) {
                Booster_Logger::log("[Booster_Parser] Skipped news item: Missing title or URL. Item: " . wp_json_encode($item));
                continue;
            }

            $category = 'News'; // Default category
            if (isset($item['category'])) {
                if (is_string($item['category']) && !empty($item['category'])) {
                    $category = $item['category'];
                } elseif (is_array($item['category']) && !empty($item['category']) && isset($item['category'][0]) && is_string($item['category'][0])) {
                    $category = $item['category'][0];
                }
            }

            $results[] = [
                'title'     => $title,
                'content'   => self::expand_content($title, self::clean_content($content)),
                'url'       => $url,
                'image'     => $image,
                'category'  => $category,
                'source'    => $source,
                'published' => $publishedAt,
                'post_type' => 'post',
            ];
        }

        Booster_Logger::log("[Booster_Parser] Parsed " . count($results) . " valid news items.");
        return $results;
    }

    /**
     * Clean up content string.
     *
     * @param string|null $content The content to clean.
     * @return string Cleaned content.
     */
    private static function clean_content(?string $content): string {
        if ($content === null || $content === '') {
            return '';
        }
        $cleaned_content = preg_replace('/\[\+\d+\s*chars\]/i', '', $content);
        if ($cleaned_content === null) {
            $cleaned_content = $content;
            Booster_Logger::log("[Booster_Parser] preg_replace error in clean_content for pattern chars.");
        }

        $cleaned_content = preg_replace('/\s+/', ' ', $cleaned_content);
        if ($cleaned_content === null) {
             Booster_Logger::log("[Booster_Parser] preg_replace error in clean_content for whitespace.");
             return trim($content);
        }

        return trim($cleaned_content);
    }

    /**
     * Expand content if too short.
     *
     * @param string $title The title of the item.
     * @param string $content The main content.
     * @param string $description Optional description to append.
     * @return string Expanded content.
     */
    private static function expand_content(string $title, string $content, string $description = ''): string {
        $stripped_content = strip_tags($content);
        if (str_word_count($stripped_content) < 100) { // Target word count
            Booster_Logger::log("[Booster_Parser] Expanding short content for '{$title}'. Current word count: " . str_word_count($stripped_content));

            $original_content_for_expansion = $content;

            if (!empty($description) && stripos($original_content_for_expansion, $description) === false) {
                $original_content_for_expansion .= "\n\n" . trim($description);
            }

            $stripped_content_after_desc = strip_tags($original_content_for_expansion);
            if (str_word_count($stripped_content_after_desc) < 100 && !empty($title)) {
                $original_content_for_expansion = '<strong>' . esc_html($title) . '</strong>' . "\n\n" . $original_content_for_expansion;
            }
            // Check length again before adding generic closing
            $stripped_content_final_check = strip_tags($original_content_for_expansion);
            if(str_word_count($stripped_content_final_check) < 150 ) { // Slightly higher threshold if we add generic closing
                $original_content_for_expansion .= "\n\n" . "Stay tuned for more updates on this topic.";
            }
            return $original_content_for_expansion;
        }
        return $content;
    }

    /**
     * Normalize a response from a Product API.
     *
     * @param array<string, mixed> $response The API response.
     * @return array<int, array<string, mixed>> Normalized product items.
     */
    private static function parse_products(array $response): array {
        $results = [];
        /** @var array<int, mixed> $items */
        $items = [];

        if (isset($response['items']) && is_array($response['items'])) {
            $items = $response['items'];
        } elseif (isset($response['products']) && is_array($response['products'])) {
            $items = $response['products'];
        // FIXED: Removed redundant is_array($response)
        } elseif (!empty($response) && array_keys($response) === range(0, count($response) - 1)) {
            $items = $response;
        }

        foreach ($items as $item) {
            if (!is_array($item)) { // This check remains crucial
                Booster_Logger::log("[Booster_Parser] Skipped non-array product item: " . gettype($item));
                continue;
            }

            $name = self::get_string_value($item, 'name');
            if (empty($name)) {
                Booster_Logger::log("[Booster_Parser] Skipped product item: Missing name. Item: " . wp_json_encode($item));
                continue;
            }

            $results[] = [
                'title'     => $name,
                'content'   => self::clean_content(self::get_string_value($item, 'description', self::get_string_value($item, 'content'))),
                'url'       => self::get_string_value($item, 'affiliate_link', self::get_string_value($item, 'url')),
                'image'     => self::get_string_value($item, 'image', self::get_string_value($item, 'urlToImage', self::get_string_value($item, 'thumbnail'))),
                'category'  => self::get_string_value($item, 'category', 'Products'),
                'provider'  => self::get_string_value($item, 'provider', 'productapi'),
                'post_type' => 'product',
            ];
        }
        Booster_Logger::log("[Booster_Parser] Parsed " . count($results) . " product items.");
        return $results;
    }

    /**
     * Normalize a response from a Crypto API.
     *
     * @param array<string, mixed> $response The API response.
     * @return array<int, array<string, mixed>> Normalized crypto items.
     */
    private static function parse_crypto(array $response): array {
        $results = [];
        /** @var array<int, mixed> $coins */
        $coins = [];

        if (isset($response['data']) && is_array($response['data'])) {
            $coins = $response['data'];
        // FIXED: Removed redundant is_array($response)
        } elseif (!empty($response) && array_keys($response) === range(0, count($response) - 1)) {
            $coins = $response;
        }

        foreach ($coins as $coin) {
            if (!is_array($coin)) { // This check remains crucial
                Booster_Logger::log("[Booster_Parser] Skipped non-array crypto item: " . gettype($coin));
                continue;
            }

            $name = self::get_string_value($coin, 'name');
            $symbol = strtoupper(self::get_string_value($coin, 'symbol'));

            if (empty($name) || empty($symbol)) {
                Booster_Logger::log("[Booster_Parser] Skipped crypto item: Missing name or symbol. Item: " . wp_json_encode($coin));
                continue;
            }

            $price_usd = self::get_string_value($coin, 'price_usd', self::get_nested_string_value($coin, ['quote', 'USD', 'price'], 'N/A'));

            $results[] = [
                'title'     => $name . ' (' . $symbol . ')',
                'content'   => 'Current price: $' . $price_usd,
                'url'       => self::get_string_value($coin, 'website_url', self::get_string_value($coin, 'website')),
                'image'     => self::get_string_value($coin, 'logo_url', self::get_string_value($coin, 'icon_url', self::get_string_value($coin, 'icon'))),
                'category'  => 'Cryptocurrency',
                'provider'  => self::get_string_value($coin, 'platform', 'coinapi'),
                'post_type' => 'post',
            ];
        }
        Booster_Logger::log("[Booster_Parser] Parsed " . count($results) . " crypto items.");
        return $results;
    }

    /**
     * Fallback: Try to find a list and parse it dynamically.
     *
     * @param array<string, mixed> $response The API response.
     * @return array<int, array<string, mixed>> Normalized items.
     */
    private static function dynamic_parser(array $response): array {
        $results = [];
        /** @var array<int, mixed> $items The list of items found. Its elements will be checked to be arrays. */
        $items = self::find_list($response);

        if (empty($items)) {
            Booster_Logger::log("[Booster_Parser] Dynamic parser: No list found in response.");
            return [];
        }

        foreach ($items as $item) {
            if (!is_array($item)) { // This check remains crucial
                Booster_Logger::log("[Booster_Parser] Dynamic parser: Skipped non-array item: " . gettype($item));
                continue;
            }

            $title = self::get_string_value($item, 'title', self::get_string_value($item, 'name'));
            if (empty($title)) {
                $title = 'Untitled Item (' . uniqid() . ')'; // Add unique ID to untitled to avoid hash collisions
                Booster_Logger::log("[Booster_Parser] Dynamic parser: Item has no title or name, defaulting to '{$title}'. Item: " . wp_json_encode($item));
            }

            $results[] = [
                'title'     => $title,
                'content'   => self::clean_content(self::get_string_value($item, 'description', self::get_string_value($item, 'summary', self::get_string_value($item, 'text')))),
                'url'       => self::get_string_value($item, 'url', self::get_string_value($item, 'link')),
                'image'     => self::get_string_value($item, 'image', self::get_string_value($item, 'icon', self::get_string_value($item, 'thumbnail', self::get_string_value($item, 'urlToImage')))),
                'category'  => self::get_string_value($item, 'category', 'Uncategorized'),
                'provider'  => self::get_nested_string_value($item, ['source', 'name'], self::get_string_value($item, 'provider', 'Dynamic Source')),
                'published' => self::get_string_value($item, 'publishedAt', self::get_string_value($item, 'date', self::get_string_value($item, 'created_at'))),
                'post_type' => 'post',
            ];
        }
        Booster_Logger::log("[Booster_Parser] Parsed " . count($results) . " dynamic items.");
        return $results;
    }

    /**
     * Find the first numerically indexed list in a nested array.
     * This is a heuristic and might need adjustment based on API structures.
     *
     * @param array<mixed> $input_array The array to search.
     * @return array<int, mixed> The found list, or an empty array.
     */
    private static function find_list(array $input_array): array {
        // Check if the top-level array is already a list of potential items (arrays)
        if (!empty($input_array) && array_keys($input_array) === range(0, count($input_array) - 1)) {
            // Further check if its elements are likely items (e.g., arrays themselves)
            if (isset($input_array[0]) && is_array($input_array[0])) {
                /** @var array<int, mixed> $input_array_as_list - Hint for PHPStan */
                $input_array_as_list = $input_array;
                return $input_array_as_list;
            }
        }

        foreach ($input_array as $value) {
            if (is_array($value)) {
                // Check if this sub-array is numerically indexed (a list)
                if (!empty($value) && array_keys($value) === range(0, count($value) - 1)) {
                    // Heuristic: if the list items are themselves arrays, it's likely our target.
                    if (isset($value[0]) && is_array($value[0])) {
                        /** @var array<int, mixed> $value_as_list - Hint for PHPStan */
                        $value_as_list = $value;
                        return $value_as_list;
                    }
                }
                // Optional recursion: if lists can be nested deeper.
                // For now, keeping it simple. If you enable recursion, ensure $value is type-hinted
                // as array<mixed> for the recursive call, or handle its potential non-array elements.
                // $found = self::find_list($value); // $value here is array<mixed>
                // if (!empty($found)) {
                //     return $found; // $found would be array<int, mixed>
                // }
            }
        }
        return []; // Returns array<empty,empty> which is compatible with array<int,mixed>
    }
}