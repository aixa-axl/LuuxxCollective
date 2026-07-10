<?php
/**
 * ACF bootstrap — Site Options field group + shared JSON helpers.
 * Page Sections logic lives in inc/page-sections.php (kept separate on purpose).
 */

defined('ABSPATH') || exit;

const LUUX_ACF_BOOTSTRAP_VERSION = 7;

function luux_site_options_slug(): string {
    return 'luux-site-options';
}

/** @return list<string> */
function luux_acf_json_paths(): array {
    return [
        get_template_directory() . '/acf-json',
        get_template_directory() . '/inc/acf-field-groups',
    ];
}

function luux_acf_resolve_json_path(string $filename): ?string {
    foreach (luux_acf_json_paths() as $dir) {
        $path = $dir . '/' . $filename;
        if (is_readable($path)) {
            return $path;
        }
    }

    return null;
}

function luux_acf_load_group_from_json(string $filename): ?array {
    $path = luux_acf_resolve_json_path($filename);
    if (! $path) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : null;
}

function luux_acf_prepare_group_from_json(string $filename, string $key): ?array {
    $group = luux_acf_load_group_from_json($filename);
    if (! $group) {
        return null;
    }

    $group['key']    = $key;
    $group['active'] = true;

    if ($key === 'group_luux_site_options') {
        $group['location'] = [
            [
                [
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => luux_site_options_slug(),
                ],
            ],
        ];
    }

    return $group;
}

function luux_acf_get_page_sections_field(): ?array {
    $group = luux_acf_prepare_group_from_json('group_luux_page_sections.json', 'group_luux_page_sections');
    if (! $group || empty($group['fields'][0]) || ! is_array($group['fields'][0])) {
        return null;
    }

    return luux_acf_normalize_field_tree($group['fields'][0]);
}

/**
 * Ensure JSON-loaded fields include keys ACF expects (PHP 8.4 safe).
 *
 * @param array<string, mixed> $field
 * @return array<string, mixed>
 */
function luux_acf_normalize_field(array $field): array {
    $type = $field['type'] ?? '';

    if ($type === 'textarea') {
        $field['new_lines']     = $field['new_lines'] ?? '';
        $field['rows']          = $field['rows'] ?? '';
        $field['maxlength']     = $field['maxlength'] ?? '';
        $field['placeholder']   = $field['placeholder'] ?? '';
        $field['default_value'] = $field['default_value'] ?? '';
    }

    if ($type === 'text') {
        $field['maxlength']     = $field['maxlength'] ?? '';
        $field['placeholder']   = $field['placeholder'] ?? '';
        $field['default_value'] = $field['default_value'] ?? '';
    }

    if ($type === 'wysiwyg') {
        $field['tabs']          = $field['tabs'] ?? 'all';
        $field['toolbar']       = $field['toolbar'] ?? 'full';
        $field['media_upload']  = $field['media_upload'] ?? 1;
        $field['delay']         = $field['delay'] ?? 0;
        $field['default_value'] = $field['default_value'] ?? '';
    }

    if ($type === 'link') {
        $field['return_format'] = $field['return_format'] ?? 'array';
    }

    if ($type === 'image') {
        $field['return_format'] = $field['return_format'] ?? 'array';
        $field['preview_size']  = $field['preview_size'] ?? 'medium';
        $field['library']       = $field['library'] ?? 'all';
        $field['min_width']     = $field['min_width'] ?? '';
        $field['min_height']    = $field['min_height'] ?? '';
        $field['min_size']      = $field['min_size'] ?? '';
        $field['max_width']     = $field['max_width'] ?? '';
        $field['max_height']    = $field['max_height'] ?? '';
        $field['max_size']      = $field['max_size'] ?? '';
        $field['mime_types']    = $field['mime_types'] ?? '';
    }

    if ($type === 'file') {
        $field['return_format'] = $field['return_format'] ?? 'array';
        $field['library']       = $field['library'] ?? 'all';
        $field['min_size']      = $field['min_size'] ?? '';
        $field['max_size']      = $field['max_size'] ?? '';
        $field['mime_types']    = $field['mime_types'] ?? '';
    }

    if ($type === 'button_group') {
        $field['choices']       = $field['choices'] ?? [];
        $field['default_value'] = $field['default_value'] ?? '';
        $field['return_format'] = $field['return_format'] ?? 'value';
        $field['layout']        = $field['layout'] ?? 'horizontal';
    }

    if ($type === 'true_false') {
        $field['default_value'] = $field['default_value'] ?? 0;
        $field['ui']            = $field['ui'] ?? 0;
        $field['ui_on_text']    = $field['ui_on_text'] ?? '';
        $field['ui_off_text']   = $field['ui_off_text'] ?? '';
    }

    if ($type === 'select') {
        $field['choices']       = $field['choices'] ?? [];
        $field['default_value'] = $field['default_value'] ?? '';
        $field['return_format'] = $field['return_format'] ?? 'value';
        $field['multiple']      = $field['multiple'] ?? 0;
        $field['ui']            = $field['ui'] ?? 0;
        $field['ajax']          = $field['ajax'] ?? 0;
        $field['placeholder']   = $field['placeholder'] ?? '';
    }

    if ($type === 'repeater') {
        $field['layout']        = $field['layout'] ?? 'table';
        $field['button_label']  = $field['button_label'] ?? '';
        $field['min']           = $field['min'] ?? 0;
        $field['max']           = $field['max'] ?? 0;
        $field['rows_per_page'] = $field['rows_per_page'] ?? 20;
        $field['collapsed']     = $field['collapsed'] ?? '';
    }

    if ($type === 'flexible_content') {
        $field['button_label'] = $field['button_label'] ?? '';
        $field['min']          = $field['min'] ?? '';
        $field['max']          = $field['max'] ?? '';
    }

    if ($type === 'group') {
        $field['layout'] = $field['layout'] ?? 'block';
    }

    if ($type === 'gallery') {
        $field['return_format'] = $field['return_format'] ?? 'array';
        $field['preview_size']  = $field['preview_size'] ?? 'medium';
        $field['library']       = $field['library'] ?? 'all';
        $field['min']           = $field['min'] ?? 0;
        $field['max']           = $field['max'] ?? 0;
    }

    if ($type === 'oembed') {
        $field['width']  = $field['width'] ?? '';
        $field['height'] = $field['height'] ?? '';
    }

    return $field;
}

/**
 * @param array<string, mixed> $field
 * @return array<string, mixed>
 */
function luux_acf_normalize_field_tree(array $field): array {
    $field = luux_acf_normalize_field($field);

    if (! empty($field['sub_fields']) && is_array($field['sub_fields'])) {
        foreach ($field['sub_fields'] as $index => $sub_field) {
            if (is_array($sub_field)) {
                $field['sub_fields'][$index] = luux_acf_normalize_field_tree($sub_field);
            }
        }
    }

    if (! empty($field['layouts']) && is_array($field['layouts'])) {
        foreach ($field['layouts'] as $index => $layout) {
            if (! is_array($layout)) {
                continue;
            }

            if (! empty($layout['sub_fields']) && is_array($layout['sub_fields'])) {
                foreach ($layout['sub_fields'] as $sub_index => $sub_field) {
                    if (is_array($sub_field)) {
                        $layout['sub_fields'][$sub_index] = luux_acf_normalize_field_tree($sub_field);
                    }
                }
            }

            $field['layouts'][$index] = $layout;
        }
    }

    return $field;
}

/**
 * @param array<int, array<string, mixed>> $fields
 */
function luux_acf_walk_fields(array $fields, callable $callback): void {
    foreach ($fields as $field) {
        if (! is_array($field)) {
            continue;
        }

        $callback($field);

        if (! empty($field['sub_fields']) && is_array($field['sub_fields'])) {
            luux_acf_walk_fields($field['sub_fields'], $callback);
        }

        if (! empty($field['layouts']) && is_array($field['layouts'])) {
            foreach ($field['layouts'] as $layout) {
                if (! empty($layout['sub_fields']) && is_array($layout['sub_fields'])) {
                    luux_acf_walk_fields($layout['sub_fields'], $callback);
                }
            }
        }
    }
}

function luux_acf_register_site_options_group(): void {
    if (! function_exists('acf_add_local_field_group')) {
        return;
    }

    $site_options = luux_acf_prepare_group_from_json('group_luux_site_options.json', 'group_luux_site_options');
    if (! $site_options || empty($site_options['fields']) || ! is_array($site_options['fields'])) {
        return;
    }

    foreach ($site_options['fields'] as $index => $field) {
        if (is_array($field)) {
            $site_options['fields'][$index] = luux_acf_normalize_field_tree($field);
        }
    }

    if (function_exists('acf_remove_local_field_group')) {
        acf_remove_local_field_group('group_luux_site_options');
    }

    acf_add_local_field_group($site_options);
}

function luux_acf_maybe_relink_option_field_keys(): void {
    $stored_key = (string) get_option('_options_site_logo', '');
    $expected   = 'field_luux_options_site_logo';

    if ($stored_key === $expected) {
        return;
    }

    luux_acf_relink_option_field_keys();
}

function luux_acf_relink_option_field_keys(): void {
    $group = luux_acf_prepare_group_from_json('group_luux_site_options.json', 'group_luux_site_options');
    if (! $group || empty($group['fields']) || ! is_array($group['fields'])) {
        return;
    }

    luux_acf_walk_fields($group['fields'], function (array $field): void {
        $name = $field['name'] ?? '';
        $key  = $field['key'] ?? '';
        $type = $field['type'] ?? '';

        if ($name === '' || $key === '' || $type === 'tab') {
            return;
        }

        update_option('_options_' . $name, $key, false);
    });
}

function luux_acf_relink_page_section_fields(): void {
    $pages = get_posts([
        'post_type'      => 'page',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    foreach ($pages as $page_id) {
        update_post_meta($page_id, '_page_sections', 'field_luux_page_sections');
    }
}

function luux_acf_run_bootstrap(): void {
    $current = (int) get_option('luux_acf_bootstrap_version', 0);

    if ($current < 1) {
        luux_acf_relink_option_field_keys();
    }

    if ($current < LUUX_ACF_BOOTSTRAP_VERSION) {
        luux_acf_relink_page_section_fields();
        luux_acf_relink_page_section_meta_keys();
    }

    update_option('luux_acf_bootstrap_version', LUUX_ACF_BOOTSTRAP_VERSION, false);
}

add_filter('acf/load_field', function ($field) {
    if (! is_array($field)) {
        return $field;
    }

    return luux_acf_normalize_field($field);
}, 1);

add_action('acf/include_fields', 'luux_acf_register_site_options_group', 1);

add_action('acf/init', function (): void {
    luux_acf_maybe_relink_option_field_keys();
}, 6);

add_action('acf/init', function (): void {
    if ((int) get_option('luux_acf_bootstrap_version', 0) >= LUUX_ACF_BOOTSTRAP_VERSION) {
        return;
    }

    luux_acf_run_bootstrap();
}, 5);

add_filter('acf/location/screen', function ($screen) {
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if ($page === luux_site_options_slug()) {
        $screen['options_page'] = luux_site_options_slug();
    }

    return $screen;
});

add_filter('acf/location/rule_match/options_page', function ($match, $rule, $screen, $field_group) {
    if (($field_group['key'] ?? '') !== 'group_luux_site_options') {
        return $match;
    }

    if (($rule['value'] ?? '') !== luux_site_options_slug()) {
        return $match;
    }

    return ($screen['options_page'] ?? '') === luux_site_options_slug();
}, 10, 4);

add_action('acf/save_post', function ($post_id): void {
    if ($post_id === 'options' || $post_id === 'option') {
        luux_acf_relink_option_field_keys();
    }
}, 20);

require get_template_directory() . '/inc/page-sections.php';
