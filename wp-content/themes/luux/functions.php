<?php
/**
 * Luux theme setup.
 * Custom theme, no parent. Tailwind v4 compiled to assets/css/main.css.
 */

defined('ABSPATH') || exit;

require get_template_directory() . '/inc/instagram.php';

/* ── Theme supports & menus ─────────────────────────────── */
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);

    register_nav_menus([
        'primary'             => __('Primary Navigation', 'luux'),
        'footer'              => __('Footer Navigation', 'luux'),
        'footer_travel'       => __('Footer — Travel Styles', 'luux'),
        'footer_destinations' => __('Footer — Destinations', 'luux'),
    ]);
});

/* ── Assets ─────────────────────────────────────────────── */
add_action('wp_enqueue_scripts', function () {
    $css = get_template_directory() . '/assets/css/main.css';
    wp_enqueue_style(
        'luux-main',
        get_template_directory_uri() . '/assets/css/main.css',
        [],
        file_exists($css) ? filemtime($css) : '1.0.0' // cache-bust on every build
    );

    $js = get_template_directory() . '/assets/js/main.js';
    if (file_exists($js)) {
        wp_enqueue_script(
            'luux-main',
            get_template_directory_uri() . '/assets/js/main.js',
            [],
            filemtime($js),
            ['strategy' => 'defer']
        );
    }
});

/* ── ACF: save/load field groups as JSON in the repo ────── */
add_filter('acf/settings/save_json', fn() => get_template_directory() . '/acf-json');
add_filter('acf/settings/load_json', function ($paths) {
    $paths[] = get_template_directory() . '/acf-json';
    return $paths;
});

/* ── ACF Site Options ───────────────────────────────────── */
function luux_site_options_slug(): string {
    return 'luux-site-options';
}

function luux_is_site_options_field_group(array $group): bool {
    return ($group['key'] ?? '') === 'group_luux_site_options'
        || ($group['title'] ?? '') === 'Site Options';
}

/**
 * Canonical field definitions from theme JSON (never run through prepare/import here).
 */
function luux_get_site_options_field_group_raw(): ?array {
    static $group = null;

    if ($group !== null) {
        return $group === false ? null : $group;
    }

    $path = get_template_directory() . '/acf-json/group_luux_site_options.json';
    if (! is_readable($path)) {
        $group = false;
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (! is_array($decoded)) {
        $group = false;
        return null;
    }

    $decoded['location'] = [
        [
            [
                'param'    => 'options_page',
                'operator' => '==',
                'value'    => luux_site_options_slug(),
            ],
        ],
    ];
    $decoded['active'] = true;
    $decoded['key']    = 'group_luux_site_options';

    $group = $decoded;
    return $group;
}

add_action('acf/init', function () {
    if (! function_exists('acf_add_options_page')) {
        return;
    }

    acf_add_options_page([
        'page_title' => __('Site Options', 'luux'),
        'menu_title' => __('Site Options', 'luux'),
        'menu_slug'  => luux_site_options_slug(),
        'capability' => 'edit_posts',
        'redirect'   => false,
    ]);
}, 0);

// Theme-owned field group — works even when nothing is synced to the database.
add_action('acf/include_fields', function () {
    if (! function_exists('acf_add_local_field_group')) {
        return;
    }

    $raw = luux_get_site_options_field_group_raw();
    if (! $raw) {
        return;
    }

    if (function_exists('acf_remove_local_field_group')) {
        acf_remove_local_field_group('group_luux_site_options');
    }

    acf_add_local_field_group($raw);
}, 99);

add_filter('acf/load_field_group', function ($group) {
    if (! is_array($group) || ($group['key'] ?? '') !== 'group_luux_site_options') {
        return $group;
    }

    $raw = luux_get_site_options_field_group_raw();
    return $raw ?? $group;
});

add_filter('acf/load_fields', function ($fields, $parent) {
    if (! is_array($parent) || ($parent['key'] ?? '') !== 'group_luux_site_options') {
        return $fields;
    }

    $raw = luux_get_site_options_field_group_raw();
    if (! $raw || empty($raw['fields']) || ! is_array($raw['fields'])) {
        return $fields;
    }

    return $raw['fields'];
}, 999, 2);

// Ensure Site Options always attaches to our options page (even with no DB field group).
add_filter('acf/location/rule_match/options_page', function ($match, $rule, $screen, $field_group) {
    if (($field_group['key'] ?? '') !== 'group_luux_site_options') {
        return $match;
    }

    return ($screen['options_page'] ?? '') === luux_site_options_slug();
}, 10, 4);

add_filter('acf/load_field_groups', function ($field_groups, $args = []) {
    if (! is_array($field_groups)) {
        $field_groups = [];
    }

    $site_options = [];
    $others       = [];

    foreach ($field_groups as $group) {
        if (is_array($group) && luux_is_site_options_field_group($group)) {
            $site_options[] = $group;
            continue;
        }
        $others[] = $group;
    }

    $canonical = null;
    foreach ($site_options as $group) {
        if (($group['key'] ?? '') === 'group_luux_site_options') {
            $canonical = $group;
            break;
        }
    }

    if (! $canonical) {
        $canonical = luux_get_site_options_field_group_raw();
    }

    if ($canonical) {
        $others[] = $canonical;
    } elseif ($site_options !== []) {
        $others[] = $site_options[0];
    }

    return $others;
}, 20, 2);

add_filter('acf/load_value/name=social_links', function ($value, $post_id) {
    if ($post_id !== 'options' && $post_id !== 'option') {
        return $value;
    }

    return is_array($value) ? $value : [];
}, 10, 2);

add_filter('acf/load_value/name=legal_links', function ($value, $post_id) {
    if ($post_id !== 'options' && $post_id !== 'option') {
        return $value;
    }

    return is_array($value) ? $value : [];
}, 10, 2);

add_action('admin_enqueue_scripts', function (string $hook): void {
    if ($hook !== 'toplevel_page_' . luux_site_options_slug()) {
        return;
    }

    wp_enqueue_media();
});

add_action('admin_notices', function () {
    if (! current_user_can('edit_posts')) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    $on_site_options = $page === luux_site_options_slug();

    $on_field_groups = function_exists('get_current_screen')
        && get_current_screen()
        && get_current_screen()->id === 'edit-acf-field-group';

    if (! $on_site_options && ! $on_field_groups) {
        return;
    }

    if (! function_exists('acf_add_options_page')) {
        if ($on_site_options) {
            echo '<div class="notice notice-error"><p><strong>Luux:</strong> ACF Pro is required for Site Options.</p></div>';
        }
        return;
    }

    if (! is_readable(get_template_directory() . '/acf-json/group_luux_site_options.json')) {
        if ($on_site_options) {
            echo '<div class="notice notice-error"><p><strong>Luux:</strong> Missing <code>acf-json/group_luux_site_options.json</code> on the server. Deploy the theme.</p></div>';
        }
        return;
    }

    if ($on_site_options && function_exists('acf_get_field_groups')) {
        $groups = acf_get_field_groups(['options_page' => luux_site_options_slug()]);
        if (empty($groups)) {
            echo '<div class="notice notice-warning"><p><strong>Luux:</strong> Site Options fields are not linked yet. Deploy the latest theme and reload this page — the theme registers fields from JSON automatically. <strong>Do not trash</strong> the Site Options field group.</p></div>';
        }
    }

    if ($on_field_groups && function_exists('acf_get_field_groups') && current_user_can('manage_options')) {
        $duplicates = 0;
        foreach (acf_get_field_groups() as $group) {
            if (luux_is_site_options_field_group($group) && ! empty($group['active'])) {
                $duplicates++;
            }
        }
        if ($duplicates > 1) {
            echo '<div class="notice notice-error"><p><strong>Luux:</strong> Multiple <em>Site Options</em> field groups are active. Trash the <strong>duplicate</strong> only — leave <code>group_luux_site_options</code> or sync once from JSON. Never trash both.</p></div>';
        }
    }
});

/* ── Flexible Content router ────────────────────────────── *
 * Loops page_sections and includes template-parts/layouts/{layout}.php.
 * Underscores in layout names map to hyphens in filenames:
 * image_text_split → template-parts/layouts/image-text-split.php
 */
function luux_uses_hero_header(): bool {
    if (is_front_page()) {
        return true;
    }
    if (! function_exists('get_field')) {
        return false;
    }
    $sections = get_field('page_sections');
    if (empty($sections) || ! is_array($sections)) {
        return false;
    }
    $first = $sections[0]['acf_fc_layout'] ?? '';
    return in_array($first, ['hero', 'resort_hero', 'contact_hero'], true);
}

/**
 * Contact-style pages use the dark blue (#0C1535) footer variant.
 * True when the page contains a contact_hero or contact_options section.
 */
function luux_uses_blue_footer(): bool {
    if (! function_exists('get_field')) {
        return false;
    }
    $sections = get_field('page_sections');
    if (empty($sections) || ! is_array($sections)) {
        return false;
    }
    foreach ($sections as $section) {
        $layout = $section['acf_fc_layout'] ?? '';
        if (in_array($layout, ['contact_hero', 'contact_options'], true)) {
            return true;
        }
    }
    return false;
}

function luux_render_sections(): void {
    if (!function_exists('have_rows') || !have_rows('page_sections')) {
        return;
    }
    while (have_rows('page_sections')) {
        the_row();
        $layout = str_replace('_', '-', get_row_layout());
        get_template_part('template-parts/layouts/' . $layout);
    }
}

/* ── Light hardening / cleanup ──────────────────────────── */
remove_action('wp_head', 'wp_generator');
add_filter('xmlrpc_enabled', '__return_false');

// Brochure site: comments off everywhere.
add_action('admin_init', function () {
    update_option('default_comment_status', 'closed');
});
