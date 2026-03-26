<?php

if (!defined('ABSPATH')) {
    exit;
}

class Skilitsa_DogMatcher_Admin
{
    private const CACHE_TTL = 600;
    private const REQUIRED_TABS = [
        'SETTINGS',
        'UI_TEXTS',
        'SECTIONS',
        'TRAITS',
        'QUESTIONS',
        'MAPPING',
        'RESULT_TEMPLATES',
        'BREEDS',
    ];

    private const REQUIRED_COLUMNS = [
        'SETTINGS' => ['key', 'value'],
        'UI_TEXTS' => ['key', 'value'],
        'SECTIONS' => ['section_key', 'label'],
        'TRAITS' => ['trait_key', 'label'],
        'QUESTIONS' => ['question_key', 'label', 'weight'],
        'MAPPING' => ['question_key', 'answer_value', 'answer_label', 'trait_key', 'weight_override'],
        'RESULT_TEMPLATES' => ['template_key', 'title', 'description'],
        'BREEDS' => ['breed_key', 'label', 'section_key'],
    ];

    public function init(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_skilitsa_dogmatcher_refresh', [$this, 'handle_refresh']);
    }

    public function register_menu(): void
    {
        add_options_page(
            __('Skilitsa DogMatcher', 'skilitsa_dogmatcher'),
            __('Skilitsa DogMatcher', 'skilitsa_dogmatcher'),
            'manage_options',
            'skilitsa-dogmatcher',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void
    {
        register_setting(
            'skilitsa_dogmatcher',
            'skilitsa_dogmatcher_settings',
            ['sanitize_callback' => [$this, 'sanitize_settings']]
        );

        add_settings_section(
            'skilitsa_dogmatcher_main',
            __('Google Sheet Configuration', 'skilitsa_dogmatcher'),
            [$this, 'render_section_intro'],
            'skilitsa-dogmatcher'
        );

        add_settings_field(
            'sheet_url',
            __('Google Sheet URL', 'skilitsa_dogmatcher'),
            [$this, 'render_sheet_url_field'],
            'skilitsa-dogmatcher',
            'skilitsa_dogmatcher_main'
        );
    }

    public function render_section_intro(): void
    {
        echo '<p>' . esc_html__('Provide a Google Sheet share link or direct link. Leave blank to use the default master sheet.', 'skilitsa_dogmatcher') . '</p>';
    }

    public function render_sheet_url_field(): void
    {
        $settings = $this->get_settings();
        $value = isset($settings['sheet_url']) ? $settings['sheet_url'] : '';
        echo '<input type="url" class="regular-text" name="skilitsa_dogmatcher_settings[sheet_url]" value="' . esc_attr($value) . '" placeholder="https://docs.google.com/spreadsheets/d/..." />';
    }

    public function render_settings_page(): void
    {
        $meta = $this->get_sync_meta();
        $last_sync = isset($meta['last_sync']) ? (int) $meta['last_sync'] : 0;
        $last_sync_display = $last_sync ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_sync) : __('Never', 'skilitsa_dogmatcher');
        $counts = isset($meta['counts']) && is_array($meta['counts']) ? $meta['counts'] : [];
        $errors = isset($meta['errors']) && is_array($meta['errors']) ? $meta['errors'] : [];
        $warnings = isset($meta['warnings']) && is_array($meta['warnings']) ? $meta['warnings'] : [];
        $config = Skilitsa_DogMatcher::get_config();
        $first_breed_url = '';
        $first_question_images = [];
        $settings = $this->get_settings();
        $has_sheet_url = !empty($settings['sheet_url']);
        $has_master_url = defined('SKILITSA_DOGMATCHER_MASTER_SHEET_URL') && SKILITSA_DOGMATCHER_MASTER_SHEET_URL !== '';
        $sheet_source_url = $settings['sheet_url'] ?? SKILITSA_DOGMATCHER_MASTER_SHEET_URL;
        $sheet_id = $sheet_source_url ? $this->extract_sheet_id($sheet_source_url) : null;

        if (!empty($config['breeds'])) {
            $first_breed = $config['breeds'][0];
            $first_breed_url = isset($first_breed['external_url']) ? (string) $first_breed['external_url'] : '';
        }

        if (!empty($config['questions'])) {
            $first_question = $config['questions'][0];
            if (isset($first_question['image_urls']) && is_array($first_question['image_urls'])) {
                $first_question_images = $first_question['image_urls'];
            }
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Skilitsa DogMatcher', 'skilitsa_dogmatcher'); ?></h1>
            <?php if (!$has_sheet_url && !$has_master_url) : ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html__('Google Sheet URL is missing and no master sheet URL is configured. Please add a sheet URL.', 'skilitsa_dogmatcher'); ?></p>
                </div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('skilitsa_dogmatcher'); ?>
                <?php do_settings_sections('skilitsa-dogmatcher'); ?>
                <?php submit_button(__('Save Settings', 'skilitsa_dogmatcher')); ?>
            </form>
            <hr />
            <h2><?php echo esc_html__('Sync from Google Sheet', 'skilitsa_dogmatcher'); ?></h2>
            <p><?php echo esc_html__('Last sync:', 'skilitsa_dogmatcher'); ?> <strong><?php echo esc_html($last_sync_display); ?></strong></p>
            <ul>
                <li><?php echo esc_html__('Breeds:', 'skilitsa_dogmatcher'); ?> <?php echo esc_html((string) ($counts['breeds'] ?? 0)); ?></li>
                <li><?php echo esc_html__('Questions:', 'skilitsa_dogmatcher'); ?> <?php echo esc_html((string) ($counts['questions'] ?? 0)); ?></li>
                <li><?php echo esc_html__('Traits:', 'skilitsa_dogmatcher'); ?> <?php echo esc_html((string) ($counts['traits'] ?? 0)); ?></li>
                <li><?php echo esc_html__('Templates:', 'skilitsa_dogmatcher'); ?> <?php echo esc_html((string) ($counts['templates'] ?? 0)); ?></li>
            </ul>
            <?php if (!empty($errors)) : ?>
                <div class="notice notice-error">
                    <p><strong><?php echo esc_html__('Errors', 'skilitsa_dogmatcher'); ?></strong></p>
                    <ul>
                        <?php foreach ($errors as $error) : ?>
                            <li><?php echo esc_html($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="notice notice-info">
                    <p><strong><?php echo esc_html__('How to fix CSV errors', 'skilitsa_dogmatcher'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Confirm the sheet is shared as “Anyone with the link: Viewer”.', 'skilitsa_dogmatcher'); ?></li>
                        <li><?php echo esc_html__('Confirm the tab name matches exactly (SETTINGS, UI_TEXTS, SECTIONS, TRAITS, QUESTIONS, MAPPING, RESULT_TEMPLATES, BREEDS).', 'skilitsa_dogmatcher'); ?></li>
                        <li><?php echo esc_html__('Open the CSV URL in a browser to verify it downloads a CSV file.', 'skilitsa_dogmatcher'); ?></li>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if (!empty($warnings)) : ?>
                <div class="notice notice-warning">
                    <p><strong><?php echo esc_html__('Warnings', 'skilitsa_dogmatcher'); ?></strong></p>
                    <ul>
                        <?php foreach ($warnings as $warning) : ?>
                            <li><?php echo esc_html($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('skilitsa_dogmatcher_refresh', 'skilitsa_dogmatcher_refresh_nonce'); ?>
                <input type="hidden" name="action" value="skilitsa_dogmatcher_refresh" />
                <?php submit_button(__('Refresh from Google Sheet', 'skilitsa_dogmatcher'), 'secondary'); ?>
            </form>
            <?php if ($sheet_id) : ?>
                <h3><?php echo esc_html__('Test CSV links', 'skilitsa_dogmatcher'); ?></h3>
                <ul>
                    <li><a href="<?php echo esc_url($this->build_csv_link($sheet_id, 'SETTINGS')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('SETTINGS CSV', 'skilitsa_dogmatcher'); ?></a></li>
                    <li><a href="<?php echo esc_url($this->build_csv_link($sheet_id, 'QUESTIONS')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('QUESTIONS CSV', 'skilitsa_dogmatcher'); ?></a></li>
                    <li><a href="<?php echo esc_url($this->build_csv_link($sheet_id, 'BREEDS')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('BREEDS CSV', 'skilitsa_dogmatcher'); ?></a></li>
                </ul>
            <?php endif; ?>
            <hr />
            <h2><?php echo esc_html__('Debug', 'skilitsa_dogmatcher'); ?></h2>
            <p><?php echo esc_html__('First breed external URL:', 'skilitsa_dogmatcher'); ?> <code><?php echo esc_html($first_breed_url ?: __('(empty)', 'skilitsa_dogmatcher')); ?></code></p>
            <p><?php echo esc_html__('First question image URLs:', 'skilitsa_dogmatcher'); ?></p>
            <?php if (!empty($first_question_images)) : ?>
                <ul>
                    <?php foreach ($first_question_images as $image_url) : ?>
                        <li><code><?php echo esc_html($image_url); ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><code><?php echo esc_html__('(none)', 'skilitsa_dogmatcher'); ?></code></p>
            <?php endif; ?>
            <details class="skilitsa-dogmatcher__troubleshooting">
                <summary><?php echo esc_html__('Troubleshooting (quick fixes)', 'skilitsa_dogmatcher'); ?></summary>
                <ul>
                    <li><?php echo esc_html__('Permissions: share the Google Sheet as “Anyone with the link: Viewer”.', 'skilitsa_dogmatcher'); ?></li>
                    <li><?php echo esc_html__('Exact tab names: SETTINGS, UI_TEXTS, SECTIONS, TRAITS, QUESTIONS, MAPPING, RESULT_TEMPLATES, BREEDS.', 'skilitsa_dogmatcher'); ?></li>
                    <li><?php echo esc_html__('CSV format: https://docs.google.com/spreadsheets/d/<ID>/gviz/tq?tqx=out:csv&sheet=<TAB>', 'skilitsa_dogmatcher'); ?></li>
                    <li><?php echo esc_html__('Refresh pulls fresh CSV data and updates stored config + counts.', 'skilitsa_dogmatcher'); ?></li>
                    <li><?php echo esc_html__('Warnings mean the MVP can still work; fix data when ready.', 'skilitsa_dogmatcher'); ?></li>
                    <li><?php echo esc_html__('Plugin updates: upload the full plugin folder (zip → extract).', 'skilitsa_dogmatcher'); ?></li>
                </ul>
            </details>
        </div>
        <?php
    }

    public function sanitize_settings(array $input): array
    {
        $settings = $this->get_settings();
        $sheet_url = isset($input['sheet_url']) ? esc_url_raw($input['sheet_url']) : '';

        if ($sheet_url !== '') {
            $sheet_id = $this->extract_sheet_id($sheet_url);
            if (!$sheet_id) {
                add_settings_error('skilitsa_dogmatcher', 'skilitsa_dogmatcher_sheet_url', __('The Google Sheet URL does not contain a valid spreadsheet ID.', 'skilitsa_dogmatcher'), 'error');
                $sheet_url = $settings['sheet_url'] ?? '';
            }
        }

        $settings['sheet_url'] = $sheet_url;

        return $settings;
    }

    public function handle_refresh(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to refresh the DogMatcher data.', 'skilitsa_dogmatcher'));
        }

        check_admin_referer('skilitsa_dogmatcher_refresh', 'skilitsa_dogmatcher_refresh_nonce');

        $settings = $this->get_settings();
        $sheet_url = $settings['sheet_url'] ?? '';

        $result = $this->sync_from_sheet($sheet_url, true);
        $meta = $result['meta'];

        update_option('skilitsa_dogmatcher_sync_meta', $meta);

        if (!empty($result['config'])) {
            update_option('skilitsa_dogmatcher_config', $result['config']);
        }

        wp_safe_redirect(add_query_arg('page', 'skilitsa-dogmatcher', admin_url('options-general.php')));
        exit;
    }

    private function sync_from_sheet(string $sheet_url, bool $force): array
    {
        $errors = [];
        $warnings = [];
        $counts = [
            'breeds' => 0,
            'questions' => 0,
            'traits' => 0,
            'templates' => 0,
        ];

        $source_url = $sheet_url ?: SKILITSA_DOGMATCHER_MASTER_SHEET_URL;
        $sheet_id = $this->extract_sheet_id($source_url);

        if (!$sheet_id) {
            $errors[] = __('Missing or invalid Google Sheet ID.', 'skilitsa_dogmatcher');
            return [
                'config' => [],
                'meta' => $this->build_meta($errors, $warnings, $counts),
            ];
        }

        $tabs = [];
        foreach (self::REQUIRED_TABS as $tab) {
            $tab_data = $this->fetch_csv_tab($sheet_id, $tab, $force);
            if (is_wp_error($tab_data)) {
                if (in_array($tab_data->get_error_code(), ['skilitsa_dogmatcher_not_csv', 'skilitsa_dogmatcher_not_found'], true)) {
                    $errors[] = sprintf(
                        __('Tab %s could not be fetched as CSV. Please ensure the sheet is shared as \"Anyone with the link: Viewer\" and that the tab name matches exactly.', 'skilitsa_dogmatcher'),
                        $tab
                    );
                } else {
                    $errors[] = sprintf(__('Missing tab: %s.', 'skilitsa_dogmatcher'), $tab);
                }
                continue;
            }
            $tabs[$tab] = $tab_data;
        }

        foreach (self::REQUIRED_TABS as $tab) {
            if (!isset($tabs[$tab])) {
                continue;
            }
            $missing_columns = $this->missing_required_columns($tabs[$tab]['headers'], self::REQUIRED_COLUMNS[$tab]);
            if (!empty($missing_columns)) {
                $errors[] = sprintf(__('Tab %s is missing required columns: %s.', 'skilitsa_dogmatcher'), $tab, implode(', ', $missing_columns));
            }
        }

        $config = $this->build_config($tabs, $errors, $warnings, $counts);

        return [
            'config' => $config,
            'meta' => $this->build_meta($errors, $warnings, $counts),
        ];
    }

    private function fetch_csv_tab(string $sheet_id, string $tab_name, bool $force)
    {
        $cache_key = 'skilitsa_dogmatcher_tab_' . md5($sheet_id . $tab_name);
        if (!$force) {
            $cached = get_transient($cache_key);
            if (false !== $cached && is_array($cached)) {
                return $cached;
            }
        }

        $url = sprintf(
            'https://docs.google.com/spreadsheets/d/%s/gviz/tq?tqx=out:csv&sheet=%s',
            rawurlencode($sheet_id),
            rawurlencode($tab_name)
        );

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'text/csv',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new WP_Error(
                'skilitsa_dogmatcher_bad_status',
                sprintf('Google Sheets returned HTTP %d.', $status_code)
            );
        }

        $content_type = wp_remote_retrieve_header($response, 'content-type');
        $body = wp_remote_retrieve_body($response);
        if ($body === '') {
            return new WP_Error('skilitsa_dogmatcher_empty_tab', 'Empty response');
        }

        if ($this->looks_like_html($body)) {
            return new WP_Error(
                'skilitsa_dogmatcher_not_csv',
                'Google Sheets did not return CSV. Check share permissions or sheet name.'
            );
        }

        if (str_starts_with($body, 'Λυπούμαστε') || mb_stripos($body, 'το αρχείο που ζητήσατε') !== false) {
            return new WP_Error(
                'skilitsa_dogmatcher_not_found',
                'Google Sheets did not return CSV. Check share permissions or sheet name.'
            );
        }

        if (!$this->looks_like_csv($content_type, $body)) {
            return new WP_Error(
                'skilitsa_dogmatcher_not_csv',
                'Google Sheets did not return CSV. Check share permissions or sheet name.'
            );
        }

        $parsed = $this->parse_csv($body);
        set_transient($cache_key, $parsed, self::CACHE_TTL);

        return $parsed;
    }

    private function parse_csv(string $csv): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $csv);
        $rows = [];
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $rows[] = str_getcsv($line);
        }

        if (empty($rows)) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = [];
        foreach ($rows[0] as $header) {
            if ($header === '' || $header === null) {
                break;
            }
            $normalized_header = $this->normalize_header($header);
            if ($normalized_header === '') {
                break;
            }
            $headers[] = $normalized_header;
        }

        $data_rows = [];
        foreach (array_slice($rows, 1) as $row) {
            $row = array_slice($row, 0, count($headers));
            if ($this->is_row_empty($row)) {
                continue;
            }
            $data_rows[] = $this->cast_row($headers, $row);
        }

        return ['headers' => $headers, 'rows' => $data_rows];
    }

    private function is_row_empty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== '' && $value !== null) {
                return false;
            }
        }
        return true;
    }

    private function cast_row(array $headers, array $row): array
    {
        $result = [];
        foreach ($headers as $index => $header) {
            $value = $row[$index] ?? '';
            if (is_string($value)) {
                if (strcasecmp($value, 'true') === 0) {
                    $value = true;
                } elseif (strcasecmp($value, 'false') === 0) {
                    $value = false;
                }
            }
            $result[$header] = $value;
        }
        return $result;
    }

    private function missing_required_columns(array $headers, array $required): array
    {
        $missing = [];
        $normalized_headers = array_map([$this, 'normalize_header'], $headers);
        foreach ($required as $column) {
            $normalized_column = $this->normalize_header($column);
            if (!in_array($normalized_column, $normalized_headers, true)) {
                $missing[] = $column;
            }
        }
        return $missing;
    }

    private function normalize_header(string $header): string
    {
        $header = trim($header);
        $header = preg_replace('/^\\xEF\\xBB\\xBF/', '', $header);
        $header = strtolower($header);
        $header = str_replace([' ', '-'], '_', $header);

        $aliases = [
            'key' => 'key',
            'value' => 'value',
            'notes' => 'notes',
            'note' => 'notes',
        ];

        return $aliases[$header] ?? $header;
    }

    private function build_config(array $tabs, array &$errors, array &$warnings, array &$counts): array
    {
        $settings = $this->rows_to_key_value($tabs['SETTINGS']['rows'] ?? [], 'key', 'value');
        foreach ($settings as $key => $value) {
            if (is_string($value) && is_numeric($value)) {
                $settings[$key] = (float) $value;
            }
        }

        $ui_texts = $this->rows_to_key_value($tabs['UI_TEXTS']['rows'] ?? [], 'key', 'value');

        $sections = $this->index_rows($tabs['SECTIONS']['rows'] ?? [], 'section_key');

        $traits = $this->index_rows($tabs['TRAITS']['rows'] ?? [], 'trait_key');
        $counts['traits'] = count($traits);

        $questions = $this->index_rows($tabs['QUESTIONS']['rows'] ?? [], 'question_key');
        foreach ($questions as $key => $question) {
            if (isset($question['weight']) && is_numeric($question['weight'])) {
                $questions[$key]['weight'] = (float) $question['weight'];
            }
        }
        $counts['questions'] = count($questions);

        $mapping_rows = $tabs['MAPPING']['rows'] ?? [];
        foreach ($mapping_rows as &$mapping_row) {
            if (isset($mapping_row['weight_override']) && is_numeric($mapping_row['weight_override'])) {
                $mapping_row['weight_override'] = (float) $mapping_row['weight_override'];
            }
        }
        unset($mapping_row);

        $templates = $this->index_rows($tabs['RESULT_TEMPLATES']['rows'] ?? [], 'template_key');
        $counts['templates'] = count($templates);

        $breeds = $this->index_rows($tabs['BREEDS']['rows'] ?? [], 'breed_key');
        $counts['breeds'] = count($breeds);

        $trait_keys = array_keys($traits);
        $valid_mappings = [];
        foreach ($mapping_rows as $mapping) {
            $trait_key = $mapping['trait_key'] ?? '';
            if ($trait_key === '' || !isset($traits[$trait_key])) {
        $warnings[] = sprintf(__('Mapping row references missing trait key: %s.', 'skilitsa_dogmatcher'), (string) $trait_key);
                continue;
            }
            $valid_mappings[] = $mapping;
        }

        $breed_missing_trait_values = [];
        $breed_missing_external_url = 0;
        $breed_missing_external_image = 0;

        foreach ($breeds as $breed_key => $breed) {
            foreach ($trait_keys as $trait_key) {
                if (!isset($breed[$trait_key]) || $breed[$trait_key] === '') {
                    $breed_missing_trait_values[] = $breed_key;
                    break;
                }
            }

            if (!isset($breed['external_url']) || $breed['external_url'] === '') {
                $breed_missing_external_url++;
            }
            if (!isset($breed['external_image_url']) || $breed['external_image_url'] === '') {
                $breed_missing_external_image++;
            }
        }

        if (!empty($breed_missing_trait_values)) {
            $warnings[] = sprintf(
                __('Breeds missing trait values: %1$d (examples: %2$s). Fix: fill all trait columns per breed (1–5).', 'skilitsa_dogmatcher'),
                count($breed_missing_trait_values),
                implode(', ', array_slice($breed_missing_trait_values, 0, 10))
            );
        }

        if ($breed_missing_external_url > 0) {
            $warnings[] = sprintf(__('Breeds missing external_url: %d.', 'skilitsa_dogmatcher'), $breed_missing_external_url);
        }

        if ($breed_missing_external_image > 0) {
            $warnings[] = __('Many breeds do not have external_image_url yet — results will show cards without images. This is OK for MVP.', 'skilitsa_dogmatcher');
        }

        return [
            'settings' => $settings,
            'ui_texts' => $ui_texts,
            'sections' => array_values($sections),
            'traits' => array_values($traits),
            'questions' => array_values($questions),
            'mapping' => $valid_mappings,
            'result_templates' => array_values($templates),
            'breeds' => array_values($breeds),
        ];
    }

    private function rows_to_key_value(array $rows, string $key_field, string $value_field): array
    {
        $data = [];
        foreach ($rows as $row) {
            if (!isset($row[$key_field])) {
                continue;
            }
            $data[(string) $row[$key_field]] = $row[$value_field] ?? '';
        }
        return $data;
    }

    private function index_rows(array $rows, string $key_field): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            if (!isset($row[$key_field])) {
                continue;
            }
            $indexed[(string) $row[$key_field]] = $row;
        }
        return $indexed;
    }

    private function build_meta(array $errors, array $warnings, array $counts): array
    {
        return [
            'last_sync' => time(),
            'counts' => $counts,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function extract_sheet_id(string $sheet_url): ?string
    {
        if (preg_match('#/spreadsheets/d/([a-zA-Z0-9-_]+)#', $sheet_url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function get_settings(): array
    {
        $settings = get_option('skilitsa_dogmatcher_settings', []);
        return is_array($settings) ? $settings : [];
    }

    private function get_sync_meta(): array
    {
        $meta = get_option('skilitsa_dogmatcher_sync_meta', []);
        return is_array($meta) ? $meta : [];
    }

    private function build_csv_link(string $sheet_id, string $tab_name): string
    {
        return sprintf(
            'https://docs.google.com/spreadsheets/d/%s/gviz/tq?tqx=out:csv&sheet=%s',
            rawurlencode($sheet_id),
            rawurlencode($tab_name)
        );
    }

    private function looks_like_html(string $body): bool
    {
        return (bool) preg_match('/<\\s*!doctype|<\\s*html/i', $body);
    }

    private function looks_like_csv(string $content_type, string $body): bool
    {
        if ($content_type && stripos($content_type, 'text/csv') !== false) {
            return true;
        }

        $first_line = strtok($body, "\n");
        if (!$first_line) {
            return false;
        }

        return strpos($first_line, ',') !== false;
    }
}
