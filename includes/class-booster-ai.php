<?php

class Booster_AI {
    /**
     * Main AI rewrite method to handle multiple providers.
     *
     * @param string $original The original content to be rewritten.
     * @return string|null The rewritten content or null if an error occurs.
     */
    public static function rewrite_content(string $original): ?string {
        $provider = get_option('booster_ai_provider', 'huggingface');
        if (!is_string($provider)) {
            $provider = 'huggingface';
        }


        if ($provider === 'openai') {
            return self::rewrite_content_with_openai($original);
        } elseif ($provider === 'huggingface') {
            return self::rewrite_content_with_huggingface($original);
        }

        Booster_Logger::log("[Booster_AI] Unsupported AI provider: {$provider}");
        return null;
    }

    /**
     * Rewrite content using Hugging Face.
     *
     * @param string $original
     * @return string|null
     */
    public static function rewrite_content_with_huggingface(string $original): ?string {
        $api_key = get_option('booster_huggingface_api_key');
        if (!is_string($api_key)) {
            $api_key = '';
        }

        if (empty($api_key)) {
            Booster_Logger::log("[Booster_AI] Missing Hugging Face API key. Skipping rewrite.");
            return null;
        }

        $endpoint = "https://api-inference.huggingface.co/models/t5-small";
        $body = json_encode(['inputs' => "Rewrite the following content: {$original}"]);
        if ($body === false) {
            Booster_Logger::log('[Booster_AI] Failed to encode JSON body.');
            return null;
        }

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => $body,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Booster_Logger::log('[Booster_AI] Hugging Face Request failed: ' . $response->get_error_message());
            return null;
        }

        $json = json_decode(wp_remote_retrieve_body($response), true);
        if (is_array($json) && isset($json[0]['generated_text']) && is_string($json[0]['generated_text'])) {
            return $json[0]['generated_text'];
        }
        Booster_Logger::log('[Booster_AI] Hugging Face response missing generated_text.');
        return null;
    }

    /**
     * Rewrite content using OpenAI.
     *
     * @param string $original
     * @return string|null
     */
    public static function rewrite_content_with_openai(string $original): ?string {
        $api_key = get_option('booster_openai_api_key');
        if (!is_string($api_key)) {
            $api_key = '';
        }

        if (empty($api_key)) {
            Booster_Logger::log("[Booster_AI] Missing OpenAI API key.");
            return null;
        }

        $endpoint = 'https://api.openai.com/v1/chat/completions';
        $body = json_encode([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful content editor.'],
                ['role' => 'user', 'content' => "Rewrite the following content: {$original}"],
            ],
            'temperature' => 0.7,
            'max_tokens' => 1000,
        ]);
        if ($body === false) {
            Booster_Logger::log('[Booster_AI] Failed to encode JSON body.');
            return null;
        }

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => $body,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Booster_Logger::log('[Booster_AI] OpenAI Request failed: ' . $response->get_error_message());
            return null;
        }

        $json = json_decode(wp_remote_retrieve_body($response), true);
        if (
            is_array($json)
            && isset($json['choices'][0]['message']['content'])
            && is_string($json['choices'][0]['message']['content'])
        ) {
            return $json['choices'][0]['message']['content'];
        }
        Booster_Logger::log('[Booster_AI] OpenAI response missing content.');
        return null;
    }

    /**
     * Get the appropriate prompt for the content.
     *
     * @param string $content
     * @return string
     */
    public static function get_prompt(string $content): string {
        if (str_word_count(strip_tags($content)) < 100) {
            Booster_Logger::log("[Booster_AI] Using expansion prompt for short content");
            return "Expand and rewrite the following short content into a full article while maintaining the core message and adding relevant details: \n\n" . $content;
        }
        Booster_Logger::log("[Booster_AI] Using standard rewrite prompt");
        return "Rewrite the following content to be unique, engaging, and SEO-friendly while maintaining accuracy and professionalism: \n\n" . $content;
    }

    /**
     * Fallback: Expand content locally without AI if needed.
     *
     * @param string $content
     * @return string
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