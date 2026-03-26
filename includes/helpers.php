<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('skilitsa_dogmatcher_register_missing_file_notice')) {
    function skilitsa_dogmatcher_register_missing_file_notice(string $file_path): void
    {
        add_action('admin_notices', static function () use ($file_path): void {
            if (!current_user_can('manage_options')) {
                return;
            }
            echo '<div class="notice notice-error"><p>' . esc_html(sprintf(
                __('Skilitsa DogMatcher: plugin files are incomplete/mismatched. Please re-upload the full plugin folder (zip → extract). Missing file: %s', 'skilitsa_dogmatcher'),
                $file_path
            )) . '</p></div>';
        });
    }
}

if (!function_exists('skilitsa_dogmatcher_safe_require')) {
    function skilitsa_dogmatcher_safe_require(string $relative_path): bool
    {
        $file_path = SKILITSA_DOGMATCHER_PATH . ltrim($relative_path, '/');
        if (!file_exists($file_path)) {
            skilitsa_dogmatcher_register_missing_file_notice($relative_path);
            return false;
        }

        require_once $file_path;
        return true;
    }
}
