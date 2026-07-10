<?php
/**
 * ACF repair — load complete field definitions from theme JSON (not partial DB copies).
 */

defined('ABSPATH') || exit;

const LUUX_ACF_REPAIR_VERSION = 10;

/** @return list<string> */
function luux_acf_json_paths(): array {
    return [
        get_template_directory() . '/acf-json',
        get_template_directory() . '/inc/acf-field-groups',
    ];
}

/** @return list<array{file: string, key: string, title: string}> */
function luux_acf_managed_field_groups(): array {
    return [
        [
            'file'  => 'group_luux_site_options.json',
            'key'   => 'group_luux_site_options',
            'title' => 'Site Options',
        ],
        [
            'file'  => 'group_luux_page_sections.json',
            'key'   => 'group_luux_page_sections',
            'title' => 'Page Sections',
        ],
    ];
}

function luux_acf_json_dir(): string {
    foreach (luux_acf_json_paths() as $dir) {
        if (is_dir($dir)) {
            return $dir;
        }
    }

    return get_template_directory() . '/acf-json';
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
        $group['location'] = luux_acf_site_options_location();
    }

    return $group;
}

function luux_acf_site_options_location(): array {
    return [
        [
            [
                'param'    => 'options_page',
                'operator' => '==',
                'value'    => luux_site_options_slug(),
            ],
        ],
    ];
}

function luux_acf_purge_legacy_option_repeaters(): void {
    foreach (['social_links', 'legal_links'] as $name) {
        delete_option('options_' . $name);
        delete_option('_options_' . $name);
    }
}

function luux_acf_delete_definition_posts(): int {
    $deleted = 0;

    foreach (['acf-field', 'acf-field-group', 'acf-ui-options-page'] as $post_type) {
        $ids = get_posts([
            'post_type'      => $post_type,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        foreach ($ids as $post_id) {
            if (wp_delete_post($post_id, true)) {
                $deleted++;
            }
        }
    }

    return $deleted;
}

/**
 * @return array{deleted: int, errors: list<string>}
 */
function luux_acf_repair_managed_groups(): array {
    $result = [
        'deleted' => 0,
        'errors'  => [],
    ];

    if (! function_exists('acf_add_local_field_group')) {
        $result['errors'][] = 'ACF Pro is not active.';
        return $result;
    }

    luux_acf_purge_legacy_option_repeaters();
    $result['deleted'] = luux_acf_delete_definition_posts();

    foreach (luux_acf_managed_field_groups() as $managed) {
        if (! luux_acf_prepare_group_from_json($managed['file'], $managed['key'])) {
            $result['errors'][] = 'Missing ' . $managed['file'] . ' in acf-json/ or inc/acf-field-groups/.';
        }
    }

    if ($result['errors'] === []) {
        luux_acf_register_managed_groups_from_json();
        luux_acf_relink_option_field_keys();
        luux_acf_relink_page_section_fields();
        update_option('luux_acf_repair_version', LUUX_ACF_REPAIR_VERSION, false);
        update_option('luux_acf_repaired_at', time(), false);

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    return $result;
}

function luux_acf_register_managed_groups_from_json(): void {
    if (! function_exists('acf_add_local_field_group') || ! function_exists('acf_add_local_field')) {
        return;
    }

    $site_options = luux_acf_prepare_group_from_json('group_luux_site_options.json', 'group_luux_site_options');
    if ($site_options) {
        if (function_exists('acf_remove_local_field_group')) {
            acf_remove_local_field_group('group_luux_site_options');
        }
        acf_add_local_field_group($site_options);
    }

    $page_sections = luux_acf_prepare_group_from_json('group_luux_page_sections.json', 'group_luux_page_sections');
    $fc_field      = luux_acf_get_page_sections_field();

    if (! $page_sections || ! $fc_field) {
        return;
    }

    if (function_exists('acf_remove_local_field_group')) {
        acf_remove_local_field_group('group_luux_page_sections');
    }

    // Flexible content layouts must be registered as a standalone local field.
    $page_sections['fields'] = [];
    acf_add_local_field_group($page_sections);

    $fc_field['parent'] = 'group_luux_page_sections';
    acf_add_local_field($fc_field);
}

function luux_acf_page_sections_layout_count(): int {
    $field = luux_acf_get_page_sections_field();

    if (! $field || empty($field['layouts']) || ! is_array($field['layouts'])) {
        return 0;
    }

    return count($field['layouts']);
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

/** @return array<string, mixed> */
function luux_acf_diagnostics(): array {
    $diag = [
        'acf_pro'              => function_exists('acf_add_options_page'),
        'json_dir'             => luux_acf_json_dir(),
        'json_files'           => [],
        'field_groups'         => [],
        'options_groups'       => 0,
        'page_groups'          => 0,
        'page_sections_layouts' => luux_acf_page_sections_layout_count(),
        'live_layouts'          => 0,
    ];

    foreach (luux_acf_managed_field_groups() as $managed) {
        $path = luux_acf_resolve_json_path($managed['file']);
        $diag['json_files'][$managed['file']] = $path ? $path : false;
    }

    if (function_exists('acf_get_field_groups')) {
        foreach (acf_get_field_groups() as $group) {
            $diag['field_groups'][] = ($group['title'] ?? '?') . ' [' . ($group['key'] ?? '?') . '] local=' . ($group['local'] ?? 'db');

            if (($group['title'] ?? '') === 'Site Options') {
                $diag['options_groups']++;
            }
            if (($group['title'] ?? '') === 'Page Sections') {
                $diag['page_groups']++;
            }
        }

        $for_options = acf_get_field_groups([
            'options_page' => luux_site_options_slug(),
        ]);
        $diag['options_screen_groups'] = count($for_options);

        $for_pages = acf_get_field_groups([
            'post_type' => 'page',
        ]);
        $diag['page_screen_groups'] = count($for_pages);
    }

    if (function_exists('acf_get_field')) {
        $live = acf_get_field('field_luux_page_sections');
        if (is_array($live) && ! empty($live['layouts']) && is_array($live['layouts'])) {
            $diag['live_layouts'] = count($live['layouts']);
        }
    }

    return $diag;
}

function luux_acf_repair_url(): string {
    return wp_nonce_url(
        add_query_arg('luux_acf_repair', '1', admin_url('tools.php?page=luux-acf-repair')),
        'luux_acf_repair'
    );
}

function luux_acf_render_repair_page(): void {
    $result = null;

    if (isset($_GET['luux_acf_repair']) && check_admin_referer('luux_acf_repair')) {
        $result = luux_acf_repair_managed_groups();
    }

    $diag = luux_acf_diagnostics();

    echo '<div class="wrap">';
    echo '<h1>Luux ACF Repair</h1>';

    if (is_array($result)) {
        if ($result['errors'] !== []) {
            echo '<div class="notice notice-error"><p>' . esc_html(implode(' ', $result['errors'])) . '</p></div>';
        } else {
            echo '<div class="notice notice-success"><p><strong>Repair complete.</strong> Removed '
                . esc_html((string) $result['deleted'])
                . ' broken ACF definition record(s). Field groups now load from theme JSON.</p></div>';
        }
    }

    echo '<p>If page editors or Site Options are blank, run repair below. Page content and option <em>values</em> are not deleted.</p>';
    echo '<p><a class="button button-primary button-hero" href="' . esc_url(luux_acf_repair_url()) . '">Run ACF repair now</a></p>';

    echo '<h2>Diagnostics</h2><table class="widefat striped" style="max-width:48rem"><tbody>';
    echo '<tr><th>ACF Pro active</th><td>' . ($diag['acf_pro'] ? 'Yes' : '<strong style="color:#b32d2e">No</strong>') . '</td></tr>';
    echo '<tr><th>Page Sections layouts in JSON</th><td>' . esc_html((string) $diag['page_sections_layouts']) . ' (want 20+)</td></tr>';
    echo '<tr><th>Page Sections layouts loaded by ACF</th><td>' . esc_html((string) ($diag['live_layouts'] ?? 0)) . ' (want 20+)</td></tr>';

    foreach ($diag['json_files'] as $file => $path) {
        if ($path) {
            echo '<tr><th>' . esc_html($file) . '</th><td>Found at <code>' . esc_html((string) $path) . '</code></td></tr>';
        } else {
            echo '<tr><th>' . esc_html($file) . '</th><td><strong style="color:#b32d2e">Missing</strong></td></tr>';
        }
    }

    echo '<tr><th>Site Options groups (total)</th><td>' . esc_html((string) $diag['options_groups']) . ' (want 1)</td></tr>';
    echo '<tr><th>Page Sections groups (total)</th><td>' . esc_html((string) $diag['page_groups']) . ' (want 1)</td></tr>';
    echo '<tr><th>Groups on Site Options screen</th><td>' . esc_html((string) ($diag['options_screen_groups'] ?? 0)) . ' (want 1)</td></tr>';
    echo '<tr><th>Groups on Page editor</th><td>' . esc_html((string) ($diag['page_screen_groups'] ?? 0)) . ' (want 1)</td></tr>';
    echo '</tbody></table>';

    if (! empty($diag['field_groups'])) {
        echo '<h3>Registered field groups</h3><ul style="list-style:disc;padding-left:1.25rem">';
        foreach ($diag['field_groups'] as $line) {
            echo '<li><code>' . esc_html($line) . '</code></li>';
        }
        echo '</ul>';
    }

    echo '</div>';
}

add_action('init', function (): void {
    if (! is_admin()) {
        return;
    }

    luux_acf_purge_legacy_option_repeaters();

    if ((int) get_option('luux_acf_repair_version', 0) >= LUUX_ACF_REPAIR_VERSION) {
        return;
    }

    luux_acf_repair_managed_groups();
}, 5);

add_action('acf/include_fields', 'luux_acf_register_managed_groups_from_json', 1);

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
    }
}, 20);

add_action('admin_menu', function (): void {
    add_management_page(
        __('Luux ACF Repair', 'luux'),
        __('Luux ACF Repair', 'luux'),
        'manage_options',
        'luux-acf-repair',
        'luux_acf_render_repair_page'
    );
});

add_action('admin_notices', function (): void {
    if (! current_user_can('manage_options')) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && $screen->id === 'tools_page_luux-acf-repair') {
        return;
    }

    if (! function_exists('acf_get_field_groups')) {
        echo '<div class="notice notice-error"><p><strong>Luux:</strong> ACF Pro is not active.</p></div>';
        return;
    }

    $diag = luux_acf_diagnostics();

    if (($diag['options_screen_groups'] ?? 0) < 1
        || ($diag['page_screen_groups'] ?? 0) < 1
        || ($diag['page_sections_layouts'] ?? 0) < 5
        || ($diag['live_layouts'] ?? 0) < 5) {
        $repair_url = esc_url(admin_url('tools.php?page=luux-acf-repair'));
        echo '<div class="notice notice-error"><p><strong>Luux:</strong> ACF field groups need repair. '
            . '<a href="' . $repair_url . '">Open Tools → Luux ACF Repair</a> and run repair.</p></div>';
    }
}, 5);
