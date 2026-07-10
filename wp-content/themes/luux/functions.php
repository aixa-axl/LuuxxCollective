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
        'capability' => 'manage_options',
        'redirect'   => false,
    ]);
}, 0);

// Deactivate duplicate "Site Options" field groups (DB copy + local copy = broken admin UI).
add_action('acf/init', function () {
    if (! is_admin() || ! current_user_can('manage_options') || ! function_exists('acf_get_field_groups')) {
        return;
    }

    $version = '6';
    if (get_option('luux_site_options_deduped') === $version) {
        return;
    }

    foreach (acf_get_field_groups() as $group) {
        if (! luux_is_site_options_field_group($group)) {
            continue;
        }
        if (($group['key'] ?? '') === 'group_luux_site_options') {
            continue;
        }
        if (! function_exists('acf_update_field_group')) {
            continue;
        }

        $group['active'] = false;
        acf_update_field_group($group);
    }

    update_option('luux_site_options_deduped', $version, false);
}, 100);

// Only one Site Options group may render on the options page.
add_filter('acf/load_field_groups', function ($field_groups) {
    if (! is_array($field_groups)) {
        return $field_groups;
    }

    $matches = [];
    $others  = [];

    foreach ($field_groups as $group) {
        if (is_array($group) && luux_is_site_options_field_group($group)) {
            $matches[] = $group;
            continue;
        }
        $others[] = $group;
    }

    if (count($matches) <= 1) {
        return $field_groups;
    }

    $canonical = null;
    foreach ($matches as $group) {
        if (($group['key'] ?? '') === 'group_luux_site_options') {
            $canonical = $group;
            break;
        }
    }

    if (! $canonical) {
        $canonical = $matches[0];
    }

    $others[] = $canonical;

    return $others;
});

// If the DB copy is incomplete, use the full field list from JSON.
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
    if (! function_exists('acf_get_field_groups') || ! current_user_can('manage_options')) {
        return;
    }

    $duplicates = 0;
    foreach (acf_get_field_groups() as $group) {
        if (luux_is_site_options_field_group($group) && ! empty($group['active'])) {
            $duplicates++;
        }
    }

    $on_field_groups = function_exists('get_current_screen')
        && get_current_screen()
        && get_current_screen()->id === 'edit-acf-field-group';

    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    $on_site_options = $page === luux_site_options_slug();

    if (! $on_field_groups && ! $on_site_options) {
        return;
    }

    if ($duplicates > 1) {
        echo '<div class="notice notice-error"><p><strong>Luux:</strong> Multiple active <em>Site Options</em> field groups detected. Trash every <em>Site Options</em> group <strong>except</strong> the one with key <code>group_luux_site_options</code>, then sync once from JSON. Duplicate groups break image uploads and tabs.</p></div>';
    }

    if (! $on_site_options) {
        return;
    }

    if (! function_exists('acf_add_options_page')) {
        echo '<div class="notice notice-error"><p><strong>Luux:</strong> ACF Pro is required for Site Options.</p></div>';
        return;
    }

    if (! is_readable(get_template_directory() . '/acf-json/group_luux_site_options.json')) {
        echo '<div class="notice notice-error"><p><strong>Luux:</strong> Missing <code>acf-json/group_luux_site_options.json</code> on the server.</p></div>';
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
