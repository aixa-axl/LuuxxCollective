<?php
/**
 * ACF bootstrap — Site Options field group + shared JSON helpers.
 * Page Sections logic lives in inc/page-sections.php (kept separate on purpose).
 */

defined('ABSPATH') || exit;

const LUUX_ACF_BOOTSTRAP_VERSION = 6;

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

    return $group['fields'][0];
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
    if (! $site_options) {
        return;
    }

    if (function_exists('acf_remove_local_field_group')) {
        acf_remove_local_field_group('group_luux_site_options');
    }

    acf_add_local_field_group($site_options);
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

add_action('acf/include_fields', 'luux_acf_register_site_options_group', 1);

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
