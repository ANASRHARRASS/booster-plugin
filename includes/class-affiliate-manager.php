<?php
class Booster_Affiliate_Manager {

    public static function process_content($content) {
        $keywords = explode(',', get_option('booster_affiliate_keywords', ''));
        $base_url = get_option('booster_affiliate_base_url', '');
        foreach ($keywords as $keyword) {
            if (trim($keyword)) {
                $content = preg_replace(
                    '/\b' . preg_quote(trim($keyword), '/') . '\b/i',
                    '<a href="' . esc_url($base_url) . '" rel="nofollow">$0</a>',
                    $content,
                    1
                );
            }
        }
        return $content;
    }
}