<?php

class Booster_AI {

    public static function rewrite_content($original): ?string {
        $api_key = get_option('booster_openai_api_key');

        // Step 1: Validate API Key
        if (empty($api_key) || strlen($api_key) < 30 || stripos($api_key, 'sk-') !== 0) {
            Booster_Logger::log("[Booster_AI] Invalid or missing OpenAI API key. Falling back to expansion.");
            return self::expand_content_locally($original);
        }

        if (empty($original)) {
            Booster_Logger::log("[Booster_AI] Empty original content provided.");
            return null;
        }

        $endpoint = 'https://api.openai.com/v1/chat/completions';
        $prompt = self::get_prompt($original);

        $body = json_encode([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful content editor.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 1000,
        ]);

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => $body,
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            Booster_Logger::log('[Booster_AI] OpenAI Request failed: ' . $response->get_error_message());
            return self::expand_content_locally($original);
        }

        $json = json_decode(wp_remote_retrieve_body($response), true);
        Booster_Logger::log("[Booster_AI] Full API JSON: " . print_r($json, true));

        if (isset($json['error'])) {
            Booster_Logger::log("[Booster_AI] OpenAI Error: " . $json['error']['message']);
            return self::expand_content_locally($original);
        }

        $rewritten = $json['choices'][0]['message']['content'] ?? null;

        if (empty($rewritten)) {
            Booster_Logger::log("[Booster_AI] Empty rewrite result from OpenAI.");
            return self::expand_content_locally($original);
        }

        Booster_Logger::log("[Booster_AI] Rewritten content length: " . strlen($rewritten));
        return $rewritten;
    }

    public static function get_prompt(string $content): string {
        if (str_word_count(strip_tags($content)) < 100) {
            Booster_Logger::log("[Booster_AI] Using expansion prompt for short content");
            return "Expand and rewrite the following short content into a full article while maintaining the core message and adding relevant details: \n\n" . $content;
        }
        Booster_Logger::log("[Booster_AI] Using standard rewrite prompt");
        return "Rewrite the following content to be unique, engaging, and SEO-friendly while maintaining accuracy and professionalism: \n\n" . $content;
    }

    /**
     * Fallback: Expand content locally without AI if needed
     */
    public static function expand_content_locally(string $content): string {
        Booster_Logger::log("[Booster_AI] Expanding content locally (no OpenAI)");

        $content = trim(strip_tags($content));

        if (str_word_count($content) < 50) {
            $content .= "\n\nMore information and updates will follow as the story develops.";
        } elseif (str_word_count($content) < 100) {
            $content .= "\n\nStay tuned for more detailed coverage and analysis.";
        }

        return $content;
    }
}


