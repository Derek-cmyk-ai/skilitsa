<?php

if (!defined('ABSPATH')) {
    exit;
}

skilitsa_dogmatcher_safe_require('includes/admin/class-skilitsa-dogmatcher-admin.php');
skilitsa_dogmatcher_safe_require('includes/frontend/class-skilitsa-dogmatcher-shortcode.php');
skilitsa_dogmatcher_safe_require('includes/class-skilitsa-dogmatcher-config.php');

class Skilitsa_DogMatcher
{
    public function init(): void
    {
        if (is_admin() && class_exists('Skilitsa_DogMatcher_Admin')) {
            $admin = new Skilitsa_DogMatcher_Admin();
            $admin->init();
        }

        if (class_exists('Skilitsa_DogMatcher_Shortcode')) {
            $shortcode = new Skilitsa_DogMatcher_Shortcode();
            $shortcode->init();
        }
    }

    public static function get_config(): array
    {
        if (!class_exists('Skilitsa_DogMatcher_Config')) {
            return [
                'settings' => [],
                'ui_texts' => [],
                'sections' => [],
                'traits' => [],
                'questions' => [],
                'mapping' => [],
                'result_templates' => [],
                'breeds' => [],
                'warnings' => [],
                'traits_by_key' => [],
                'questions_by_key' => [],
                'breeds_by_key' => [],
            ];
        }

        return Skilitsa_DogMatcher_Config::get_config();
    }
}
