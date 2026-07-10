<?php
/**
 * ACF repair — purge broken DB field groups and register canonical JSON definitions.
 */

defined('ABSPATH') || exit;

const LUUX_ACF_REPAIR_VERSION = 5;

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
        delete_option('options_' . $name . '_0_label');
        delete_option('options_' . $name . '_0_url');
        delete_option('options_' . $name . '_0_icon');
        delete_option('options_' . $name . '_0_link');
    }
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
    $result['deleted'] = luux_acf_delete_managed_groups();

    foreach (luux_acf_managed_field_groups() as $managed) {
        if (! luux_acf_load_group_from_json($managed['file'])) {
            $result['errors'][] = 'Missing acf-json/' . $managed['file'];
        }
    }

    if ($result['errors'] === []) {
        update_option('luux_acf_repair_version', LUUX_ACF_REPAIR_VERSION, false);
        update_option('luux_acf_repaired_at', time(), false);
    }

    return $result;
}

function luux_acf_register_managed_groups_from_json(): void {
    if (! function_exists('acf_add_local_field_group')) {
        return;
    }

    foreach (luux_acf_managed_field_groups() as $managed) {
        $group = luux_acf_load_group_from_json($managed['file']);
        if (! $group) {
            continue;
        }

        $group['key']    = $managed['key'];
        $group['active'] = true;

        if ($managed['key'] === 'group_luux_site_options') {
            $group['location'] = luux_acf_site_options_location();
        }

        if (function_exists('acf_remove_local_field_group')) {
            acf_remove_local_field_group($managed['key']);
        }

        acf_add_local_field_group($group);
    }
}

function luux_acf_repair_url(): string {
    return wp_nonce_url(
        add_query_arg('luux_acf_repair', '1', admin_url('tools.php?page=luux-acf-repair')),
        'luux_acf_repair'
    );
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

add_action('acf/include_fields', 'luux_acf_register_managed_groups_from_json', 99);

add_action('admin_menu', function (): void {
    add_management_page(
        __('Luux ACF Repair', 'luux'),
        __('Luux ACF Repair', 'luux'),
        'manage_options',
        'luux-acf-repair',
        function (): void {
            $result = null;

            if (isset($_GET['luux_acf_repair']) && check_admin_referer('luux_acf_repair')) {
                $result = luux_acf_repair_managed_groups();
            }

            echo '<div class="wrap">';
            echo '<h1>Luux ACF Repair</h1>';
            echo '<p>Use this if page editors or Site Options show a blank screen, or fields will not save.</p>';

            if (is_array($result)) {
                if ($result['errors'] !== []) {
                    echo '<div class="notice notice-error"><p>' . esc_html(implode(' ', $result['errors'])) . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>Repair complete. Deleted '
                        . esc_html((string) $result['deleted'])
                        . ' broken database field group(s). Field definitions now load from theme JSON.</p></div>';
                }
            }

            echo '<p><a class="button button-primary button-hero" href="' . esc_url(luux_acf_repair_url()) . '">Run ACF repair now</a></p>';
            echo '<p><em>Safe to run — page content and Site Options values stay in the database.</em></p>';
            echo '</div>';
        }
    );
});

add_action('admin_notices', function (): void {
    if (! current_user_can('manage_options')) {
        return;
    }

    if (! function_exists('acf_get_field_groups')) {
        echo '<div class="notice notice-error"><p><strong>Luux:</strong> ACF Pro is not active. Go to <strong>Plugins</strong> and activate <strong>Advanced Custom Fields PRO</strong>.</p></div>';
        return;
    }

    if (function_exists('is_plugin_active')) {
        $free_active = is_plugin_active('advanced-custom-fields/acf.php');
        $pro_active  = is_plugin_active('advanced-custom-fields-pro/acf.php');

        if ($free_active && $pro_active) {
            echo '<div class="notice notice-error"><p><strong>Luux:</strong> Deactivate the free <strong>Advanced Custom Fields</strong> plugin. Keep only <strong>ACF PRO</strong>.</p></div>';
        }
    }

    $repair_url = esc_url(admin_url('tools.php?page=luux-acf-repair'));

    echo '<div class="notice notice-warning"><p><strong>Luux:</strong> Blank page editor or Site Options? '
        . '<a href="' . $repair_url . '">Open Tools → Luux ACF Repair</a> and click <strong>Run ACF repair now</strong>.</p></div>';
}, 5);
