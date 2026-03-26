<?php

if (!defined('ABSPATH')) {
    exit;
}

class Skilitsa_DogMatcher_Config
{
    public static function get_config(): array
    {
        $config = get_option('skilitsa_dogmatcher_config', []);
        $config = is_array($config) ? $config : [];

        $normalized = [
            'settings' => isset($config['settings']) && is_array($config['settings']) ? $config['settings'] : [],
            'ui_texts' => isset($config['ui_texts']) && is_array($config['ui_texts']) ? $config['ui_texts'] : [],
            'sections' => isset($config['sections']) && is_array($config['sections']) ? $config['sections'] : [],
            'traits' => isset($config['traits']) && is_array($config['traits']) ? $config['traits'] : [],
            'questions' => isset($config['questions']) && is_array($config['questions']) ? $config['questions'] : [],
            'mapping' => isset($config['mapping']) && is_array($config['mapping']) ? $config['mapping'] : [],
            'result_templates' => isset($config['result_templates']) && is_array($config['result_templates']) ? $config['result_templates'] : [],
            'breeds' => isset($config['breeds']) && is_array($config['breeds']) ? $config['breeds'] : [],
            'warnings' => [],
        ];

        $normalized['traits_by_key'] = self::index_by_key($normalized['traits'], 'trait_key');
        $normalized['questions_by_key'] = self::index_by_key($normalized['questions'], 'question_key');
        $normalized['breeds_by_key'] = self::index_by_key($normalized['breeds'], 'breed_key');

        $normalized['questions'] = self::normalize_questions($normalized['questions'], $normalized['settings']);
        $normalized['breeds'] = self::normalize_breeds($normalized['breeds'], $normalized['settings'], $normalized['warnings']);
        $normalized['breeds_by_key'] = self::index_by_key($normalized['breeds'], 'breed_key');

        return $normalized;
    }

    private static function normalize_breeds(array $breeds, array $settings, array &$warnings): array
    {
        $base_url = isset($settings['breed_base_url']) ? (string) $settings['breed_base_url'] : '';
        $utm = isset($settings['breed_url_utm']) ? (string) $settings['breed_url_utm'] : '';

        foreach ($breeds as $index => $breed) {
            $external_url = isset($breed['external_url']) ? (string) $breed['external_url'] : '';
            $external_slug = isset($breed['external_slug']) ? (string) $breed['external_slug'] : '';

            if ($external_url === '' && $external_slug !== '' && $base_url !== '') {
                $external_url = $base_url . $external_slug . $utm;
            }

            if ($external_url === '' && $external_slug === '') {
                $warnings[] = sprintf(__('Breed %s is missing external_slug and external_url.', 'skilitsa_dogmatcher'), (string) ($breed['breed_key'] ?? ''));
            }

            if ($external_url !== '' && !self::is_absolute_url($external_url)) {
                $external_url = self::ensure_absolute_url($external_url);
            }

            $external_image_url = isset($breed['external_image_url']) ? (string) $breed['external_image_url'] : '';

            $breeds[$index]['external_url'] = $external_url;
            $breeds[$index]['external_image_url'] = $external_image_url;
        }

        return $breeds;
    }

    private static function normalize_questions(array $questions, array $settings): array
    {
        $image_mode = isset($settings['question_image_mode']) ? (string) $settings['question_image_mode'] : 'slider';
        foreach ($questions as $index => $question) {
            $images = [];
            $img_1 = isset($question['img_1_key']) ? (string) $question['img_1_key'] : '';
            $img_5 = isset($question['img_5_key']) ? (string) $question['img_5_key'] : '';

            foreach ([$img_1, $img_5] as $image_value) {
                $resolved = self::resolve_question_image($image_value);
                if ($resolved !== '') {
                    $images[] = $resolved;
                }
            }

            $questions[$index]['image_urls'] = $images;
            $questions[$index]['image_mode'] = $image_mode;
        }

        return $questions;
    }

    private static function resolve_question_image(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (self::is_absolute_url($value)) {
            return $value;
        }

        if (ctype_digit($value)) {
            $attachment_url = wp_get_attachment_image_url((int) $value, 'large');
            if ($attachment_url) {
                return $attachment_url;
            }
        }

        return SKILITSA_DOGMATCHER_URL . 'assets/img/questions/' . rawurlencode($value);
    }

    private static function index_by_key(array $items, string $key): array
    {
        $indexed = [];
        foreach ($items as $item) {
            if (isset($item[$key])) {
                $indexed[(string) $item[$key]] = $item;
            }
        }
        return $indexed;
    }

    private static function is_absolute_url(string $url): bool
    {
        return (bool) filter_var($url, FILTER_VALIDATE_URL);
    }

    private static function ensure_absolute_url(string $url): string
    {
        if (self::is_absolute_url($url)) {
            return $url;
        }
        return home_url($url);
    }
}
