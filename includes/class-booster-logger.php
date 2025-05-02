<?php

class Booster_Logger {

    const OPTION_KEY = 'booster_logs';
    const MAX_LOGS = 100;

    /**
     * Save a log message to the WP options table (keeps the last MAX_LOGS entries).
     *
     * @param string $message The log message.
     * @param string $level   The log level (info, warning, error, success, debug).
     */
    public static function log(string $message, string $level = 'info'): void {
        $logs = get_option(self::OPTION_KEY, []);
        if (!is_array($logs)) $logs = [];

        $emoji = self::get_level_icon($level);
        $formatted = sprintf(
            '%s [%s] %s %s',
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $emoji,
            $message
        );

        $logs[] = $formatted;
        $logs = array_slice($logs, -self::MAX_LOGS); // keep only last MAX_LOGS entries

        update_option(self::OPTION_KEY, $logs);
    }

    /**
     * Get the most recent log messages.
     *
     * @param int $count
     * @return array
     */
    public static function get_recent_logs(int $count = 20): array {
        $logs = get_option(self::OPTION_KEY, []);
        if (!is_array($logs)) return [];
        return array_slice(array_reverse($logs), 0, $count);
    }

    /**
     * Clear all logs.
     */
    public static function clear_logs(): void {
        delete_option(self::OPTION_KEY);
    }

    /**
     * Return a nice emoji based on log level.
     *
     * @param string $level
     * @return string
     */
    private static function get_level_icon(string $level): string {
        switch (strtolower($level)) {
            case 'error':
                return '❌';
            case 'warning':
                return '⚠️';
            case 'success':
                return '✅';
            case 'debug':
                return '🔍';
            default:
                return 'ℹ️'; // info
        }
    }
}
