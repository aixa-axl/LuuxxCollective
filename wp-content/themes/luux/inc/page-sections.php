<?php
/**
 * Page Sections — repair broken DB field definitions, relink meta, render helpers.
 * Does not touch Site Options.
 */

defined('ABSPATH') || exit;

const LUUX_PAGE_SECTIONS_REPAIR_VERSION = 6;

function luux_acf_is_page_section_meta_key(string $key): bool {
    return (bool) preg_match('/^_?page_sections/', $key);
}

function luux_acf_page_sections_layout_count(?array $field = null): int {
    if ($field === null && function_exists('acf_get_field')) {
        $field = acf_get_field('field_luux_page_sections');
    }

    if (! is_array($field) || empty($field['layouts']) || ! is_array($field['layouts'])) {
        return 0;
    }

    return count($field['layouts']);
}

/**
 * Register Page Sections from theme JSON via local PHP (wins over broken DB copies).
 */
function luux_acf_register_page_sections_local(): bool {
    if (! function_exists('acf_add_local_field_group') || ! function_exists('acf_add_local_field')) {
        return false;
    }

    $group = luux_acf_prepare_group_from_json('group_luux_page_sections.json', 'group_luux_page_sections');
    $fc    = luux_acf_get_page_sections_field();

    if (! $group || ! $fc || luux_acf_page_sections_layout_count($fc) < 1) {
        return false;
    }

    if (function_exists('acf_remove_local_field_group')) {
        acf_remove_local_field_group('group_luux_page_sections');
    }

    $group['fields'] = [];
    acf_add_local_field_group($group);

    $fc['parent'] = 'group_luux_page_sections';
    acf_add_local_field($fc);

    return true;
}

/**
 * Remove only the database copy of Page Sections (not Site Options, not page content).
 */
function luux_acf_delete_page_sections_db_group(): void {
    if (! function_exists('acf_get_field_group')) {
        return;
    }

    $group = acf_get_field_group('group_luux_page_sections');
    if (! is_array($group) || ! empty($group['local']) || empty($group['ID'])) {
        return;
    }

    if (function_exists('acf_delete_field_group')) {
        acf_delete_field_group((int) $group['ID']);
        return;
    }

    wp_delete_post((int) $group['ID'], true);
}

function luux_acf_import_page_sections_group(): bool {
    if (! function_exists('acf_import_field_group')) {
        return false;
    }

    $group = luux_acf_prepare_group_from_json('group_luux_page_sections.json', 'group_luux_page_sections');
    if (! $group) {
        return false;
    }

    acf_import_field_group($group);

    return true;
}

/**
 * Fix Page Sections field definitions when layouts are missing.
 *
 * @return array{ok: bool, message: string}
 */
function luux_acf_repair_page_sections_definitions(): array {
    $json_path = luux_acf_resolve_json_path('group_luux_page_sections.json');
    if (! $json_path) {
        return [
            'ok'      => false,
            'message' => 'Theme JSON missing on server. Check acf-json/ and inc/acf-field-groups/ were deployed.',
        ];
    }

    $json_fc = luux_acf_get_page_sections_field();
    if (! $json_fc || luux_acf_page_sections_layout_count($json_fc) < 1) {
        return [
            'ok'      => false,
            'message' => 'Page Sections JSON is unreadable or has no layouts.',
        ];
    }

    luux_acf_delete_page_sections_db_group();
    luux_acf_import_page_sections_group();
    luux_acf_register_page_sections_local();

    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }

    $layouts = luux_acf_page_sections_layout_count();
    if ($layouts < 20) {
        return [
            'ok'      => false,
            'message' => 'Repair ran but ACF still reports ' . $layouts . ' layouts (expected 25).',
        ];
    }

    update_option('luux_page_sections_repair_version', LUUX_PAGE_SECTIONS_REPAIR_VERSION, false);

    return [
        'ok'      => true,
        'message' => 'Page Sections repaired. ' . $layouts . ' layouts now available.',
    ];
}

function luux_acf_maybe_repair_page_sections_definitions(): void {
    if (luux_acf_page_sections_layout_count() >= 20) {
        return;
    }

    if ((int) get_option('luux_page_sections_repair_version', 0) >= LUUX_PAGE_SECTIONS_REPAIR_VERSION) {
        luux_acf_register_page_sections_local();
        return;
    }

    luux_acf_repair_page_sections_definitions();
}

/** @return array<string, mixed> */
function luux_acf_page_sections_diagnostics(): array {
    $json_path = luux_acf_resolve_json_path('group_luux_page_sections.json');
    $json_fc   = luux_acf_get_page_sections_field();
    $live      = function_exists('acf_get_field') ? acf_get_field('field_luux_page_sections') : null;
    $group     = function_exists('acf_get_field_group') ? acf_get_field_group('group_luux_page_sections') : null;

    return [
        'json_path'    => $json_path ?: false,
        'json_layouts' => luux_acf_page_sections_layout_count($json_fc),
        'live_layouts' => luux_acf_page_sections_layout_count($live),
        'group_source' => is_array($group) ? ($group['local'] ?? 'database') : 'none',
        'group_id'     => is_array($group) ? ($group['ID'] ?? 0) : 0,
        'repair_done'  => (int) get_option('luux_page_sections_repair_version', 0),
    ];
}

function luux_acf_render_page_sections_tools_page(): void {
    if (! current_user_can('manage_options')) {
        return;
    }

    $result = null;
    if (isset($_GET['luux_repair_page_sections']) && check_admin_referer('luux_repair_page_sections')) {
        $result = luux_acf_repair_page_sections_definitions();
        if ($result['ok']) {
            luux_acf_relink_page_section_meta_keys();
        }
    }

    $diag = luux_acf_page_sections_diagnostics();
    $url  = wp_nonce_url(
        add_query_arg('luux_repair_page_sections', '1', admin_url('tools.php?page=luux-page-sections')),
        'luux_repair_page_sections'
    );

    echo '<div class="wrap"><h1>Luux Page Sections</h1>';
    echo '<p>Site Options is not affected. This tool only repairs the Page Sections field group.</p>';

    if (is_array($result)) {
        $class = $result['ok'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
    }

    echo '<table class="widefat striped" style="max-width:40rem"><tbody>';
    echo '<tr><th>JSON file on server</th><td>' . ($diag['json_path'] ? '<code>' . esc_html((string) $diag['json_path']) . '</code>' : '<strong style="color:#b32d2e">Missing</strong>') . '</td></tr>';
    echo '<tr><th>Layouts in theme JSON</th><td>' . esc_html((string) $diag['json_layouts']) . ' (want 25)</td></tr>';
    echo '<tr><th>Layouts ACF is using</th><td>' . esc_html((string) $diag['live_layouts']) . ' (want 25)</td></tr>';
    echo '<tr><th>Field group source</th><td><code>' . esc_html((string) $diag['group_source']) . '</code></td></tr>';
    echo '</tbody></table>';

    echo '<p><a class="button button-primary" href="' . esc_url($url) . '">Repair Page Sections now</a></p>';
    echo '</div>';
}

/** @return array<string, array<int, array<string, mixed>>> */
function luux_acf_page_section_layout_fields(): array {
    static $layouts = null;

    if ($layouts !== null) {
        return $layouts;
    }

    $layouts = [];
    $fc      = luux_acf_get_page_sections_field();

    if ($fc && ! empty($fc['layouts']) && is_array($fc['layouts'])) {
        foreach ($fc['layouts'] as $layout) {
            if (! empty($layout['name'])) {
                $layouts[$layout['name']] = $layout['sub_fields'] ?? [];
            }
        }
    }

    return $layouts;
}

function luux_acf_page_section_layout_key(string $layout_name): ?string {
    $fc = luux_acf_get_page_sections_field();

    if (! $fc || empty($fc['layouts']) || ! is_array($fc['layouts'])) {
        return null;
    }

    foreach ($fc['layouts'] as $layout) {
        if (($layout['name'] ?? '') === $layout_name) {
            return $layout['key'] ?? null;
        }
    }

    return null;
}

function luux_page_section_count_from_meta(array $meta): int {
    if (! empty($meta['page_sections'])) {
        return (int) $meta['page_sections'];
    }

    $max = -1;

    foreach (array_keys($meta) as $key) {
        if (preg_match('/^page_sections_(\d+)_acf_fc_layout$/', $key, $matches)) {
            $max = max($max, (int) $matches[1]);
        }
    }

    return $max + 1;
}

function luux_page_section_count(int $post_id): int {
    $stored = (int) get_post_meta($post_id, 'page_sections', true);
    if ($stored > 0) {
        return $stored;
    }

    $max = -1;
    $raw = get_metadata('post', $post_id);

    if (! is_array($raw)) {
        return 0;
    }

    foreach (array_keys($raw) as $key) {
        if (preg_match('/^page_sections_(\d+)_acf_fc_layout$/', $key, $matches)) {
            $max = max($max, (int) $matches[1]);
        }
    }

    return $max + 1;
}

/**
 * @param array<int, array<string, mixed>> $fields
 */
function luux_acf_resolve_section_field_key(array $fields, string $path): ?string {
    if (preg_match('/^([^_]+)_(\d+)_(.+)$/', $path, $matches)) {
        $repeater_name = $matches[1];
        $sub_path      = $matches[3];

        foreach ($fields as $field) {
            if (($field['name'] ?? '') !== $repeater_name) {
                continue;
            }

            if (($field['type'] ?? '') !== 'repeater') {
                continue;
            }

            return luux_acf_resolve_section_field_key($field['sub_fields'] ?? [], $sub_path);
        }

        return null;
    }

    foreach ($fields as $field) {
        $name = $field['name'] ?? '';
        $type = $field['type'] ?? '';

        if ($name === '') {
            continue;
        }

        if ($type === 'group' && str_starts_with($path, $name . '_')) {
            $resolved = luux_acf_resolve_section_field_key(
                $field['sub_fields'] ?? [],
                substr($path, strlen($name) + 1)
            );

            if ($resolved) {
                return $resolved;
            }
        }

        if ($path === $name) {
            return $field['key'] ?? null;
        }
    }

    return null;
}

/** @return array<string, mixed> */
function luux_acf_fix_section_meta_refs(array $meta): array {
    $layouts = luux_acf_page_section_layout_fields();
    $count   = luux_page_section_count_from_meta($meta);

    for ($i = 0; $i < $count; $i++) {
        $layout = $meta["page_sections_{$i}_acf_fc_layout"] ?? '';
        if ($layout === '' || ! isset($layouts[$layout])) {
            continue;
        }

        $layout_key = luux_acf_page_section_layout_key($layout);
        if ($layout_key) {
            $meta["_page_sections_{$i}"] = $layout_key;
        }

        $value_prefix = "page_sections_{$i}_";

        foreach (array_keys($meta) as $key) {
            if (! str_starts_with($key, $value_prefix)) {
                continue;
            }

            $path = substr($key, strlen($value_prefix));
            if ($path === '' || $path === 'acf_fc_layout') {
                continue;
            }

            $field_key = luux_acf_resolve_section_field_key($layouts[$layout], $path);
            if ($field_key) {
                $meta["_page_sections_{$i}_{$path}"] = $field_key;
            }
        }
    }

    $meta['_page_sections'] = 'field_luux_page_sections';

    if ($count > 0) {
        $meta['page_sections'] = $count;
    }

    return $meta;
}

/** @return array<string, mixed> */
function luux_acf_get_page_section_meta(int $post_id): array {
    $meta = [];
    $raw  = get_metadata('post', $post_id);

    if (! is_array($raw)) {
        return $meta;
    }

    foreach ($raw as $key => $values) {
        if (luux_acf_is_page_section_meta_key($key)) {
            $meta[$key] = $values[0];
        }
    }

    return luux_acf_fix_section_meta_refs($meta);
}

function luux_acf_relink_page_section_meta_for_post(int $post_id): void {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta === []) {
        return;
    }

    foreach ($meta as $key => $value) {
        if (! luux_acf_is_page_section_meta_key($key)) {
            continue;
        }

        update_post_meta($post_id, $key, $value);
    }
}

function luux_acf_relink_page_section_meta_keys(): void {
    $pages = get_posts([
        'post_type'      => 'page',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    foreach ($pages as $page_id) {
        luux_acf_relink_page_section_meta_for_post((int) $page_id);
    }
}

/** @return array<string, mixed>|null */
function luux_acf_merged_page_meta(int $post_id): ?array {
    $section_meta = luux_acf_get_page_section_meta($post_id);

    if ($section_meta === [] || luux_page_section_count_from_meta($section_meta) < 1) {
        return null;
    }

    $all = [];
    $raw = get_metadata('post', $post_id);

    if (is_array($raw)) {
        foreach ($raw as $key => $values) {
            $all[$key] = $values[0];
        }
    }

    return array_merge($all, $section_meta);
}

add_action('acf/include_fields', function (): void {
    luux_acf_register_page_sections_local();
}, 20);

add_action('acf/init', 'luux_acf_maybe_repair_page_sections_definitions', 15);

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

add_filter('acf/pre_load_meta', function ($null, $post_id) {
    if (is_admin()) {
        return $null;
    }

    if (! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page') {
        return $null;
    }

    return luux_acf_merged_page_meta((int) $post_id);
}, 10, 2);

add_action('acf/save_post', function ($post_id): void {
    if (! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page') {
        return;
    }

    luux_acf_relink_page_section_meta_for_post((int) $post_id);
}, 20);

add_action('admin_menu', function (): void {
    add_management_page(
        __('Luux Page Sections', 'luux'),
        __('Luux Page Sections', 'luux'),
        'manage_options',
        'luux-page-sections',
        'luux_acf_render_page_sections_tools_page'
    );
});

add_action('admin_notices', function (): void {
    if (! current_user_can('manage_options') || ! function_exists('acf_get_field')) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && $screen->id === 'tools_page_luux-page-sections') {
        return;
    }

    if (luux_acf_page_sections_layout_count() >= 20) {
        return;
    }

    $url = esc_url(admin_url('tools.php?page=luux-page-sections'));
    echo '<div class="notice notice-error"><p><strong>Luux:</strong> Page Sections layouts are missing (site content will not show). ';
    echo '<a href="' . $url . '">Open Tools → Luux Page Sections</a> to repair.</p></div>';
});
