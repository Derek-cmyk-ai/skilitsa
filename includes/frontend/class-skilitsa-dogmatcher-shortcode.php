<?php

if (!defined('ABSPATH')) {
    exit;
}

class Skilitsa_DogMatcher_Shortcode
{
    public function init(): void
    {
        add_shortcode('skilitsa_dogmatcher', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    public function register_assets(): void
    {
        wp_register_style(
            'skilitsa-dogmatcher',
            SKILITSA_DOGMATCHER_URL . 'assets/css/skilitsa-dogmatcher.css',
            [],
            SKILITSA_DOGMATCHER_VERSION
        );

        wp_register_script(
            'skilitsa-dogmatcher',
            SKILITSA_DOGMATCHER_URL . 'assets/js/skilitsa-dogmatcher.js',
            [],
            SKILITSA_DOGMATCHER_VERSION,
            true
        );
    }

    public function render_shortcode(): string
    {
        $config = Skilitsa_DogMatcher::get_config();
        if (empty($config['questions']) || empty($config['breeds'])) {
            return '<div class="skilitsa-dogmatcher">' . esc_html__('DogMatcher is not configured yet. Please sync the Google Sheet in the settings page.', 'skilitsa_dogmatcher') . '</div>';
        }

        $front_end_data = $this->build_front_end_data($config);
        if (empty($front_end_data['questions']) || empty($front_end_data['sections'])) {
            return '<div class="skilitsa-dogmatcher">' . esc_html__('DogMatcher configuration is incomplete. Please check the sheet data.', 'skilitsa_dogmatcher') . '</div>';
        }

        wp_enqueue_style('skilitsa-dogmatcher');
        wp_enqueue_script('skilitsa-dogmatcher');

        wp_localize_script('skilitsa-dogmatcher', 'SkilitsaDogMatcherData', [
            'questions' => $front_end_data['questions'],
            'sections' => $front_end_data['sections'],
            'uiTexts' => $front_end_data['ui_texts'],
            'settings' => $front_end_data['settings'],
            'traits' => $front_end_data['traits'],
            'mapping' => $front_end_data['mapping'],
            'breeds' => $front_end_data['breeds'],
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('skilitsa_dogmatcher_nonce'),
            'labels' => [
                'submit' => __('Show My Match', 'skilitsa_dogmatcher'),
                'reset' => __('Start Over', 'skilitsa_dogmatcher'),
                'resultTitle' => __('Your Best Match', 'skilitsa_dogmatcher'),
            ],
        ]);

        return '<div class="skilitsa-dogmatcher" data-dogmatcher-root="true"></div>';
    }

    private function build_front_end_data(array $config): array
    {
        $questions = [];
        $sections = [];
        $ui_texts = isset($config['ui_texts']) && is_array($config['ui_texts']) ? $config['ui_texts'] : [];
        $settings = isset($config['settings']) && is_array($config['settings']) ? $config['settings'] : [];
        $traits = isset($config['traits']) && is_array($config['traits']) ? $config['traits'] : [];
        $mapping = isset($config['mapping']) && is_array($config['mapping']) ? $config['mapping'] : [];
        $breeds = isset($config['breeds']) && is_array($config['breeds']) ? $config['breeds'] : [];

        $question_map = [];

        foreach ($mapping as $row) {
            $question_key = $row['question_key'] ?? '';
            if ($question_key === '') {
                continue;
            }
            if (!isset($question_map[$question_key])) {
                $question_map[$question_key] = [];
            }
            $answer_value = $row['answer_value'] ?? '';
            if ($answer_value === '') {
                continue;
            }
            $question_map[$question_key][$answer_value] = $row['answer_label'] ?? $answer_value;
        }

        foreach ($config['sections'] as $section) {
            if (!isset($section['section_key'], $section['label'])) {
                continue;
            }
            $sections[] = [
                'section_key' => (string) $section['section_key'],
                'label' => (string) $section['label'],
            ];
        }

        foreach ($config['questions'] as $question) {
            $question_key = $question['question_key'] ?? '';
            if ($question_key === '' || !isset($question_map[$question_key])) {
                continue;
            }
            $options = [];
            foreach ($question_map[$question_key] as $value => $label) {
                $options[] = [
                    'label' => (string) $label,
                    'value' => (string) $value,
                ];
            }
            $questions[] = [
                'id' => (string) $question_key,
                'label' => (string) ($question['label'] ?? $question_key),
                'section_key' => (string) ($question['section_key'] ?? ''),
                'weight' => isset($question['weight']) ? (float) $question['weight'] : 1.0,
                'options' => $options,
                'image_1_url' => $this->get_first_image_value($question, ['image_1_url', 'img_1_url', 'img_1_key']),
                'image_5_url' => $this->get_first_image_value($question, ['image_5_url', 'img_5_url', 'img_5_key']),
                'helperText' => isset($question['helper_text']) ? (string) $question['helper_text'] : '',
                'scaleLabels' => [
                    '1' => $this->get_scale_label($question, ['q_1', 'q1', 'label_1', 'answer_1_label', 'qxx_1']),
                    '5' => $this->get_scale_label($question, ['q_5', 'q5', 'label_5', 'answer_5_label', 'qxx_5']),
                ],
            ];
        }

        return [
            'questions' => $questions,
            'sections' => $sections,
            'ui_texts' => $ui_texts,
            'settings' => [
                'image_mode_default' => isset($settings['image_mode_default']) ? (string) $settings['image_mode_default'] : 'slider',
                'image_mode_safe' => isset($settings['image_mode_safe']) ? (string) $settings['image_mode_safe'] : 'pulse',
                'official_test_url' => isset($settings['official_test_url']) ? (string) $settings['official_test_url'] : '',
                'breed_base_url' => isset($settings['breed_base_url']) ? (string) $settings['breed_base_url'] : '',
                'breed_url_utm' => isset($settings['breed_url_utm']) ? (string) $settings['breed_url_utm'] : '',
                'results_top_cards' => isset($settings['results_top_cards']) ? (int) $settings['results_top_cards'] : 3,
                'results_top_show' => isset($settings['results_top_show']) ? (int) $settings['results_top_show'] : 5,
                'results_more_count' => isset($settings['results_more_count']) ? (int) $settings['results_more_count'] : 10,
                'dealbreaker_strength' => isset($settings['dealbreaker_strength']) ? (int) $settings['dealbreaker_strength'] : 4,
                'dealbreaker_diff_threshold' => isset($settings['dealbreaker_diff_threshold']) ? (int) $settings['dealbreaker_diff_threshold'] : 2,
                'scoring_strictness' => isset($settings['scoring_strictness']) ? (int) $settings['scoring_strictness'] : 3,
                'training_warning_strength' => isset($settings['training_warning_strength']) ? (int) $settings['training_warning_strength'] : 4,
                'feedback_verbosity' => isset($settings['feedback_verbosity']) ? (int) $settings['feedback_verbosity'] : 4,
            ],
            'traits' => $traits,
            'mapping' => $mapping,
            'breeds' => $breeds,
        ];
    }

    private function get_scale_label(array $question, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($question[$key]) && $question[$key] !== '') {
                return (string) $question[$key];
            }
        }
        return '';
    }

    private function get_first_image_value(array $question, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($question[$key]) && $question[$key] !== '') {
                return (string) $question[$key];
            }
        }
        return '';
    }
}
