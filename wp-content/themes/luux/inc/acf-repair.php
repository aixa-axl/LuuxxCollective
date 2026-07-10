<?php
/**
 * ACF repair — clean database field definitions and import from theme JSON.
 */

defined('ABSPATH') || exit;

const LUUX_ACF_REPAIR_VERSION = 6;

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
    return get_template_directory() . '/acf-json';
}

function luux_acf_load_group_from_json(string $filename): ?array {
    $path = luux_acf_json_dir() . '/' . $filename;
    if (! is_readable($path)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : null;
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

function luux_acf_delete_all_field_posts(): int {
    $deleted = 0;

    foreach (['acf-field', 'acf-field-group'] as $post_type) {
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
 * @return array{deleted: int, imported: int, errors: list<string>}
 */
function luux_acf_repair_managed_groups(): array {
    $result = [
        'deleted'  => 0,
        'imported' => 0,
        'errors'   => [],
    ];

    if (! function_exists('acf_import_field_group')) {
        $result['errors'][] = 'ACF Pro is not active.';
        return $result;
    }

    luux_acf_purge_legacy_option_repeaters();
    $result['deleted'] = luux_acf_delete_all_field_posts();

    foreach (luux_acf_managed_field_groups() as $managed) {
        $group = luux_acf_load_group_from_json($managed['file']);
        if (! $group) {
            $result['errors'][] = 'Missing ' . luux_acf_json_dir() . '/' . $managed['file'];
            continue;
        }

        if ($managed['key'] === 'group_luux_site_options') {
            $group['location'] = luux_acf_site_options_location();
        }

        $group['active'] = true;

        $imported = acf_import_field_group($group);
        if (! is_array($imported) || empty($imported['ID'])) {
            $result['errors'][] = 'Import failed for ' . $managed['title'] . '.';
            continue;
        }

        $result['imported']++;
    }

    if ($result['errors'] === []) {
        update_option('luux_acf_repair_version', LUUX_ACF_REPAIR_VERSION, false);
        update_option('luux_acf_repaired_at', time(), false);
    }

    return $result;
}

/** @return array<string, mixed> */
function luux_acf_diagnostics(): array {
    $diag = [
        'acf_pro'        => function_exists('acf_add_options_page'),
        'json_dir'       => luux_acf_json_dir(),
        'json_files'     => [],
        'field_groups'   => [],
        'options_groups' => 0,
        'page_groups'    => 0,
    ];

    foreach (luux_acf_managed_field_groups() as $managed) {
        $path = luux_acf_json_dir() . '/' . $managed['file'];
        $diag['json_files'][$managed['file']] = is_readable($path);
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

        if (function_exists('acf_get_field_groups')) {
            $for_options = acf_get_field_groups([
                'options_page' => luux_site_options_slug(),
            ]);
            $diag['options_screen_groups'] = count($for_options);

            $for_pages = acf_get_field_groups([
                'post_type' => 'page',
            ]);
            $diag['page_screen_groups'] = count($for_pages);
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
                . ' ACF database record(s) and imported '
                . esc_html((string) $result['imported'])
                . ' field group(s) from theme JSON.</p></div>';
        }
    }

    echo '<p>If page editors or Site Options are blank, run repair below. Page content and option <em>values</em> are not deleted.</p>';
    echo '<p><a class="button button-primary button-hero" href="' . esc_url(luux_acf_repair_url()) . '">Run ACF repair now</a></p>';

    echo '<h2>Diagnostics</h2><table class="widefat striped" style="max-width:48rem"><tbody>';
    echo '<tr><th>ACF Pro active</th><td>' . ($diag['acf_pro'] ? 'Yes' : '<strong style="color:#b32d2e">No</strong>') . '</td></tr>';
    echo '<tr><th>JSON directory</th><td><code>' . esc_html($diag['json_dir']) . '</code></td></tr>';

    foreach ($diag['json_files'] as $file => $ok) {
        echo '<tr><th>' . esc_html($file) . '</th><td>' . ($ok ? 'Found' : '<strong style="color:#b32d2e">Missing</strong>') . '</td></tr>';
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

    if (($diag['options_screen_groups'] ?? 0) < 1 || ($diag['page_screen_groups'] ?? 0) < 1) {
        $repair_url = esc_url(admin_url('tools.php?page=luux-acf-repair'));
        echo '<div class="notice notice-error"><p><strong>Luux:</strong> ACF field groups are not attached to pages or Site Options. '
            . '<a href="' . $repair_url . '">Open Tools → Luux ACF Repair</a> and run repair.</p></div>';
    }
}, 5);
