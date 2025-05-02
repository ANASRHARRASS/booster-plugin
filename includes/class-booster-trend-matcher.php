<?php

class Booster_Trend_Matcher {

    /**
     * Return mock trending keywords from Google Trends (for now).
     * Later, replace this with a real external API call or a custom endpoint.
     *
     * @return array
     */
    public static function get_trending_keywords(): array {
        return [
            'ai',
            'bitcoin',
            'elon musk',
            'meta',
            'gpt',
            'openai',
            'climate change',
            'apple',
            'nvidia',
            'tesla',
        ];
    }

    /**
     * Match post keywords to trending keywords and return a match score (0-100).
     *
     * @param array $post_keywords
     * @param array $trending_keywords
     * @return int
     */
    public static function calculate_match_score(array $post_keywords, array $trending_keywords): int {
        if (empty($post_keywords) || empty($trending_keywords)) {
            return 0;
        }

        $matched = array_intersect(
            array_map('strtolower', $post_keywords),
            array_map('strtolower', $trending_keywords)
        );

        $score = (count($matched) / count($trending_keywords)) * 100;

        return (int) round(min($score, 100));
    }
}
