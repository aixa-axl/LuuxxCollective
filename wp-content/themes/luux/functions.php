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

function luux_site_options_location(): array {
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

/**
 * Canonical Site Options field group from theme JSON (complete field definitions).
 */
function luux_get_site_options_field_group(): ?array {
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

    if (function_exists('acf_prepare_field_group_for_import')) {
        $decoded = acf_prepare_field_group_for_import($decoded);
    }

    $decoded['location'] = luux_site_options_location();
    $decoded['active']   = true;

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

// Register the complete field group from JSON so local fields win over a partial DB sync.
add_action('acf/include_fields', function () {
    if (! function_exists('acf_add_local_field_group')) {
        return;
    }

    $json = luux_get_site_options_field_group();
    if (! $json) {
        return;
    }

    if (function_exists('acf_remove_local_field_group')) {
        acf_remove_local_field_group('group_luux_site_options');
    }

    acf_add_local_field_group($json);
}, 99);

// DB sync on production can leave wrong location rules or incomplete child fields.
add_filter('acf/load_field_group', function ($group) {
    if (! is_array($group) || ($group['key'] ?? '') !== 'group_luux_site_options') {
        return $group;
    }

    $json = luux_get_site_options_field_group();
    return $json ?? $group;
});

add_filter('acf/load_fields', function ($fields, $parent) {
    if (! is_array($parent) || ($parent['key'] ?? '') !== 'group_luux_site_options') {
        return $fields;
    }

    $json = luux_get_site_options_field_group();
    if (! $json || empty($json['fields']) || ! is_array($json['fields'])) {
        return $fields;
    }

    return $json['fields'];
}, 20, 2);

// Corrupt repeater data in wp_options can white-screen the Social tab.
add_filter('acf/load_value/name=social_links', function ($value) {
    return is_array($value) ? $value : [];
});

add_filter('acf/location/rule_match/options_page', function ($match, $rule, $screen, $field_group) {
    if (($field_group['key'] ?? '') !== 'group_luux_site_options') {
        return $match;
    }

    $slug = $screen['options_page'] ?? '';
    if ($slug === luux_site_options_slug()) {
        return true;
    }

    return $match;
}, 10, 4);

add_action('admin_enqueue_scripts', function (string $hook): void {
    if ($hook !== 'toplevel_page_' . luux_site_options_slug()) {
        return;
    }

    wp_enqueue_media();
});

add_action('admin_notices', function () {
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if ($page !== luux_site_options_slug()) {
        return;
    }

    if (! function_exists('acf_add_options_page')) {
        echo '<div class="notice notice-error"><p><strong>Luux:</strong> ACF Pro is required for Site Options. Activate Advanced Custom Fields PRO.</p></div>';
        return;
    }

    $path = get_template_directory() . '/acf-json/group_luux_site_options.json';
    if (! is_readable($path)) {
        echo '<div class="notice notice-error"><p><strong>Luux:</strong> Missing <code>acf-json/group_luux_site_options.json</code> on the server. Deploy the theme or upload that file.</p></div>';
        return;
    }

    if (! function_exists('acf_get_field_groups')) {
        return;
    }

    $groups = acf_get_field_groups(['options_page' => luux_site_options_slug()]);
    if (! empty($groups)) {
        return;
    }

    $pages = function_exists('acf_get_options_pages') ? acf_get_options_pages() : [];
    $slugs = $pages ? array_column($pages, 'menu_slug') : [];

    echo '<div class="notice notice-warning"><p><strong>Luux:</strong> No field groups are linked to this options page yet.';
    if ($slugs && ! in_array(luux_site_options_slug(), $slugs, true)) {
        echo ' Registered options page slugs: <code>' . esc_html(implode('</code>, <code>', $slugs)) . '</code>.';
        echo ' This page expects <code>' . esc_html(luux_site_options_slug()) . '</code>.';
    }
    echo ' Deploy the latest theme, then under <strong>Custom Fields → Field Groups</strong> trash any <strong>Site Options</strong> group whose location is not Options Page, and sync it again from the JSON file.</p></div>';
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
