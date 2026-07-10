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

/** @return list<string> */
function luux_site_options_field_group_keys(): array {
    return [
        'group_luux_site_options',
        'group_luux_site_options_social',
    ];
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

function luux_load_acf_json_field_group(string $filename, string $key): ?array {
    $path = get_template_directory() . '/acf-json/' . $filename;
    if (! is_readable($path)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (! is_array($decoded)) {
        return null;
    }

    $decoded['key']      = $key;
    $decoded['active']   = true;
    $decoded['location'] = luux_site_options_location();

    return $decoded;
}

function luux_register_site_options_field_groups(): void {
    if (! function_exists('acf_add_local_field_group')) {
        return;
    }

    $groups = [
        ['group_luux_site_options.json', 'group_luux_site_options'],
        ['group_luux_site_options_social.json', 'group_luux_site_options_social'],
    ];

    foreach ($groups as [$filename, $key]) {
        if (function_exists('acf_remove_local_field_group')) {
            acf_remove_local_field_group($key);
        }

        $group = luux_load_acf_json_field_group($filename, $key);
        if ($group) {
            acf_add_local_field_group($group);
        }
    }
}

function luux_is_site_options_field_group(array $group): bool {
    return in_array($group['key'] ?? '', luux_site_options_field_group_keys(), true);
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

add_action('acf/include_fields', 'luux_register_site_options_field_groups', 99);

add_filter('acf/location/rule_match/options_page', function ($match, $rule, $screen, $field_group) {
    if (! in_array($field_group['key'] ?? '', luux_site_options_field_group_keys(), true)) {
        return $match;
    }

    return ($screen['options_page'] ?? '') === luux_site_options_slug();
}, 10, 4);

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

// Drop corrupt repeater data that can white-screen the Social tab.
add_action('acf/init', function () {
    if (! is_admin()) {
        return;
    }

    foreach (['social_links', 'legal_links'] as $name) {
        $raw = get_option('options_' . $name);
        if ($raw === false) {
            continue;
        }

        $value = maybe_unserialize($raw);
        if (! is_array($value)) {
            delete_option('options_' . $name);
            delete_option('_options_' . $name);
        }
    }
}, 5);

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
    if ($page !== luux_site_options_slug()) {
        return;
    }

    if (! function_exists('acf_add_options_page')) {
        echo '<div class="notice notice-error"><p><strong>Luux:</strong> ACF Pro is required for Site Options.</p></div>';
        return;
    }

    foreach (['group_luux_site_options.json', 'group_luux_site_options_social.json'] as $file) {
        if (! is_readable(get_template_directory() . '/acf-json/' . $file)) {
            echo '<div class="notice notice-error"><p><strong>Luux:</strong> Missing <code>acf-json/' . esc_html($file) . '</code> on the server. Deploy the theme.</p></div>';
            return;
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
