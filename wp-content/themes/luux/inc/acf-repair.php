<?php
/**
 * One-click ACF field group repair for Site Options + Page Sections.
 */

defined('ABSPATH') || exit;

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

function luux_acf_load_group_from_json(string $filename): ?array {
    $path = get_template_directory() . '/acf-json/' . $filename;
    if (! is_readable($path)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : null;
}

/** @return array{site_options: int, page_sections: int, managed: int} */
function luux_acf_count_managed_groups(): array {
    $counts = [
        'site_options'   => 0,
        'page_sections'  => 0,
        'managed'        => 0,
    ];

    if (! function_exists('acf_get_field_groups')) {
        return $counts;
    }

    $managed_keys = array_column(luux_acf_managed_field_groups(), 'key');

    foreach (acf_get_field_groups() as $group) {
        $key   = $group['key'] ?? '';
        $title = $group['title'] ?? '';

        if ($title === 'Site Options') {
            $counts['site_options']++;
        }
        if ($title === 'Page Sections') {
            $counts['page_sections']++;
        }
        if (in_array($key, $managed_keys, true)) {
            $counts['managed']++;
        }
    }

    return $counts;
}

function luux_acf_delete_managed_groups(): int {
    $managed_titles = array_column(luux_acf_managed_field_groups(), 'title');
    $deleted        = 0;

    $posts = get_posts([
        'post_type'      => 'acf-field-group',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    foreach ($posts as $post_id) {
        $title = get_the_title($post_id);
        if (! in_array($title, $managed_titles, true)) {
            continue;
        }

        if (wp_delete_post($post_id, true)) {
            $deleted++;
        }
    }

    if (function_exists('acf_get_field_groups') && function_exists('acf_delete_field_group')) {
        $managed_keys = array_column(luux_acf_managed_field_groups(), 'key');

        foreach (acf_get_field_groups() as $group) {
            $key = $group['key'] ?? '';
            $id  = $group['ID'] ?? 0;

            if (! $id || ! in_array($key, $managed_keys, true)) {
                continue;
            }

            if (acf_delete_field_group($id)) {
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
        $result['errors'][] = 'ACF import is unavailable. Activate Advanced Custom Fields PRO.';
        return $result;
    }

    $result['deleted'] = luux_acf_delete_managed_groups();

    foreach (luux_acf_managed_field_groups() as $managed) {
        $group = luux_acf_load_group_from_json($managed['file']);
        if (! $group) {
            $result['errors'][] = 'Missing theme file: acf-json/' . $managed['file'];
            continue;
        }

        $imported = acf_import_field_group($group);
        if (! is_array($imported) || empty($imported['ID'])) {
            $result['errors'][] = 'Import failed for ' . $managed['title'] . '.';
            continue;
        }

        $result['imported']++;
    }

    if ($result['errors'] === []) {
        update_option('luux_acf_repaired_at', time(), false);
    }

    return $result;
}

function luux_acf_repair_url(): string {
    return wp_nonce_url(
        add_query_arg('luux_acf_repair', '1', admin_url('index.php')),
        'luux_acf_repair'
    );
}

add_action('admin_init', function (): void {
    if (! is_admin() || ! current_user_can('manage_options')) {
        return;
    }

    if (! isset($_GET['luux_acf_repair'])) {
        return;
    }

    check_admin_referer('luux_acf_repair');

    $result = luux_acf_repair_managed_groups();

    $query = [
        'luux_acf_repair_done' => '1',
        'luux_deleted'         => (string) $result['deleted'],
        'luux_imported'        => (string) $result['imported'],
    ];

    if ($result['errors'] !== []) {
        $query['luux_acf_repair_error'] = rawurlencode(implode(' ', $result['errors']));
    }

    wp_safe_redirect(add_query_arg($query, admin_url('index.php')));
    exit;
});

add_action('admin_notices', function (): void {
    if (! current_user_can('manage_options')) {
        return;
    }

    if (isset($_GET['luux_acf_repair_done'])) {
        $deleted  = isset($_GET['luux_deleted']) ? (int) $_GET['luux_deleted'] : 0;
        $imported = isset($_GET['luux_imported']) ? (int) $_GET['luux_imported'] : 0;
        $error    = isset($_GET['luux_acf_repair_error']) ? sanitize_text_field(wp_unslash($_GET['luux_acf_repair_error'])) : '';

        if ($error !== '') {
            echo '<div class="notice notice-error"><p><strong>Luux ACF repair failed:</strong> ' . esc_html($error) . '</p></div>';
            return;
        }

        echo '<div class="notice notice-success"><p><strong>Luux ACF repair complete.</strong> Removed '
            . esc_html((string) $deleted)
            . ' old field group(s) and imported '
            . esc_html((string) $imported)
            . ' from theme JSON. Try editing a page and saving Site Options again.</p></div>';
        return;
    }

    if (! function_exists('acf_get_field_groups')) {
        echo '<div class="notice notice-error"><p><strong>Luux:</strong> Advanced Custom Fields PRO is not active. Activate ACF Pro — reinstalling the zip only helps if the plugin is missing or corrupted.</p></div>';
        return;
    }

    if (function_exists('is_plugin_active')) {
        $free_active = is_plugin_active('advanced-custom-fields/acf.php');
        $pro_active  = is_plugin_active('advanced-custom-fields-pro/acf.php');

        if ($free_active && $pro_active) {
            echo '<div class="notice notice-error"><p><strong>Luux:</strong> Both free ACF and ACF Pro are active. Deactivate the free <strong>Advanced Custom Fields</strong> plugin and keep only <strong>ACF PRO</strong>.</p></div>';
        }
    }

    $counts = luux_acf_count_managed_groups();
    if ($counts['site_options'] <= 1 && $counts['page_sections'] <= 1 && $counts['managed'] >= 2) {
        return;
    }

    $repair_url = luux_acf_repair_url();

    echo '<div class="notice notice-error"><p><strong>Luux ACF needs repair.</strong> ';
    echo 'Detected ' . esc_html((string) $counts['site_options']) . ' Site Options group(s) and ';
    echo esc_html((string) $counts['page_sections']) . ' Page Sections group(s). ';
    echo 'This causes blank page editors and Site Options that will not save. ';
    echo '<a class="button button-primary" href="' . esc_url($repair_url) . '">Repair ACF field groups</a>';
    echo ' (safe — page content and Site Options values stay in the database).</p></div>';
}, 5);
