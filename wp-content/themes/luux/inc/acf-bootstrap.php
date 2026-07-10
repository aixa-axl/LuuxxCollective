<?php
/**
 * ACF bootstrap — register field groups from theme JSON and relink stored values.
 * Non-destructive: never deletes ACF definition posts from the database.
 */

defined('ABSPATH') || exit;

const LUUX_ACF_BOOTSTRAP_VERSION = 4;

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

function luux_acf_register_local_groups(): void {
    if (! function_exists('acf_add_local_field_group')) {
        return;
    }

    $site_options = luux_acf_prepare_group_from_json('group_luux_site_options.json', 'group_luux_site_options');
    if ($site_options) {
        if (function_exists('acf_remove_local_field_group')) {
            acf_remove_local_field_group('group_luux_site_options');
        }
        acf_add_local_field_group($site_options);
    }

    if (! function_exists('acf_add_local_field')) {
        return;
    }

    $page_sections = luux_acf_prepare_group_from_json('group_luux_page_sections.json', 'group_luux_page_sections');
    $fc_field      = luux_acf_get_page_sections_field();

    if (! $page_sections || ! $fc_field) {
        return;
    }

    if (function_exists('acf_remove_local_field_group')) {
        acf_remove_local_field_group('group_luux_page_sections');
    }

    // Page Sections: register the FC field separately so all layouts load in admin.
    $page_sections['fields'] = [];
    acf_add_local_field_group($page_sections);

    $fc_field['parent'] = 'group_luux_page_sections';
    acf_add_local_field($fc_field);
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

    if ($current < 2) {
        luux_acf_relink_page_section_fields();
        luux_acf_relink_page_section_meta_keys();
    }

    if ($current < 3) {
        luux_acf_relink_page_section_meta_keys();
    }

    if ($current < 4) {
        luux_acf_relink_page_section_meta_keys();
    }

    update_option('luux_acf_bootstrap_version', LUUX_ACF_BOOTSTRAP_VERSION, false);
}

add_action('acf/include_fields', 'luux_acf_register_local_groups', 1);

add_action('acf/init', function (): void {
    if ((int) get_option('luux_acf_bootstrap_version', 0) >= LUUX_ACF_BOOTSTRAP_VERSION) {
        return;
    }

    luux_acf_run_bootstrap();
}, 5);

add_filter('acf/load_field', function ($field) {
    if (! is_array($field) || ($field['key'] ?? '') !== 'field_luux_page_sections') {
        return $field;
    }

    $from_json = luux_acf_get_page_sections_field();
    if (! $from_json) {
        return $field;
    }

    $from_json['ID']     = $field['ID'] ?? 0;
    $from_json['parent'] = $field['parent'] ?? 'group_luux_page_sections';

    return $from_json;
}, 999);

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
        return;
    }

    if (is_numeric($post_id) && get_post_type((int) $post_id) === 'page') {
        luux_acf_relink_page_section_meta_for_post((int) $post_id);
    }
}, 20);

require get_template_directory() . '/inc/page-sections.php';
