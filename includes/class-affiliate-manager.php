<?php

declare(strict_types=1); // Good practice

class Booster_Affiliate_Manager {

    /**
     * Processes content to add affiliate links based on configured keywords.
     *
     * @param string $content The input content string.
     * @return string The processed content string with affiliate links.
     */
    public static function process_content(string $content): string {
        // Retrieve options and ensure they are strings
        $keywords_option = get_option('booster_affiliate_keywords', '');
        $keywords_string = is_string($keywords_option) ? $keywords_option : '';

        $base_url_option = get_option('booster_affiliate_base_url', '');
        $base_url = is_string($base_url_option) ? $base_url_option : '';

        if (empty(trim($keywords_string)) || empty(trim($base_url))) {
            return $content; // No keywords or base URL, return original content
        }

        $keywords = array_map('trim', explode(',', $keywords_string));
        $processed_content = $content; // Work on a copy

        foreach ($keywords as $keyword) {
            $trimmed_keyword = trim($keyword);
            if (!empty($trimmed_keyword)) {
                // Construct the replacement link
                // Ensure $base_url is a valid URL before using it
                $affiliate_link_html = '<a href="' . esc_url($base_url) . '?keyword=' . rawurlencode($trimmed_keyword) . '" rel="nofollow sponsored" target="_blank">$0</a>';

                // The pattern for word boundary, case-insensitive
                $pattern = '/\b' . preg_quote($trimmed_keyword, '/') . '\b/i';

                // Perform the replacement
                $result = preg_replace(
                    $pattern,
                    $affiliate_link_html,
                    $processed_content, // Use the potentially modified content from previous iteration
                    1 // Replace only the first occurrence per keyword (adjust if needed)
                );

                // Check if preg_replace returned null (error) or a string
                if (is_string($result)) {
                    $processed_content = $result;
                } else {
                    // Log error or handle it, but ensure $processed_content remains a string
                    // For now, we'll just keep the content as it was before this problematic replacement
                    if (is_callable(['Booster_Logger', 'log'])) { // MODIFIED_LINE
                         Booster_Logger::log(sprintf("[Booster Affiliate Manager] preg_replace error for keyword '%s'. Pattern: %s", $trimmed_keyword, $pattern));
                    }
                    // $processed_content remains unchanged from the previous iteration if $result is null
                }
            }
        }
        return $processed_content;
    }
}