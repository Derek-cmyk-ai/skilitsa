<?php
/**
 * Plugin Name: Skilitsa DogMatcher
 * Description: Match users with their ideal dog breed using a configurable quiz.
 * Version: 1.0.0
 * Author: Skilitsa
 * Text Domain: skilitsa_dogmatcher
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SKILITSA_DOGMATCHER_VERSION', '1.0.0');
define('SKILITSA_DOGMATCHER_PATH', plugin_dir_path(__FILE__));
define('SKILITSA_DOGMATCHER_URL', plugin_dir_url(__FILE__));
define('SKILITSA_DOGMATCHER_MASTER_SHEET_URL', 'https://docs.google.com/spreadsheets/d/1Du1Lod48C0naHt6NWUc1DP_5BAf9st90cKDDsUdwpY0/edit?usp=drive_link');

$helpers_path = SKILITSA_DOGMATCHER_PATH . 'includes/helpers.php';
if (file_exists($helpers_path)) {
    require_once $helpers_path;
} else {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>' . esc_html__('Skilitsa DogMatcher: Plugin files incomplete, please re-upload plugin folder.', 'skilitsa_dogmatcher') . '</p></div>';
    });
    return;
}

if (!skilitsa_dogmatcher_safe_require('includes/class-skilitsa-dogmatcher.php')) {
    return;
}

function skilitsa_dogmatcher_init(): void
{
    $plugin = new Skilitsa_DogMatcher();
    $plugin->init();
}

add_action('plugins_loaded', 'skilitsa_dogmatcher_init');
