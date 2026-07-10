<?php
/**
 * Luux theme setup.
 * Custom theme, no parent. Tailwind v4 compiled to assets/css/main.css.
 */

defined('ABSPATH') || exit;

require get_template_directory() . '/inc/instagram.php';
require get_template_directory() . '/inc/site-options.php';

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

/* ── ACF JSON + Site Options page ───────────────────────── */
add_filter('acf/settings/save_json', fn() => get_template_directory() . '/acf-json');
add_filter('acf/settings/load_json', function ($paths) {
    $paths[] = get_template_directory() . '/acf-json';
    return $paths;
});

function luux_site_options_slug(): string {
    return 'luux-site-options';
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

add_action('admin_enqueue_scripts', function (string $hook): void {
    if ($hook !== 'toplevel_page_' . luux_site_options_slug()) {
        return;
    }

    wp_enqueue_media();
});

add_action('admin_notices', function () {
    if (! current_user_can('edit_posts') || ! function_exists('acf_get_field_groups')) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (! $screen) {
        return;
    }

    $on_site_options = isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === luux_site_options_slug();
    $on_acf          = $screen->id === 'edit-acf-field-group' || $screen->post_type === 'acf-field-group';
    $on_page_edit    = $screen->base === 'post' && $screen->post_type === 'page';

    if (! $on_site_options && ! $on_acf && ! $on_page_edit) {
        return;
    }

    if (! function_exists('acf_add_options_page')) {
        echo '<div class="notice notice-error"><p><strong>Luux:</strong> ACF Pro is required.</p></div>';
        return;
    }

    foreach (['group_luux_site_options.json', 'group_luux_page_sections.json'] as $file) {
        if (! is_readable(get_template_directory() . '/acf-json/' . $file)) {
            echo '<div class="notice notice-error"><p><strong>Luux:</strong> Missing <code>acf-json/' . esc_html($file) . '</code>. Deploy the theme.</p></div>';
            return;
        }
    }

    $site_options_count = 0;
    $page_sections_count = 0;

    foreach (acf_get_field_groups() as $group) {
        $title = $group['title'] ?? '';
        if ($title === 'Site Options') {
            $site_options_count++;
        }
        if ($title === 'Page Sections') {
            $page_sections_count++;
        }
    }

    if ($site_options_count > 1 || $page_sections_count > 1) {
        echo '<div class="notice notice-error"><p><strong>Luux:</strong> Duplicate ACF field groups detected. Go to <strong>ACF → Field Groups</strong>, trash every duplicate <strong>Site Options</strong> and <strong>Page Sections</strong> row, empty the trash, then click <strong>Sync</strong> on the remaining JSON copies.</p></div>';
    } elseif ($on_site_options || $on_acf) {
        echo '<div class="notice notice-info"><p><strong>Luux:</strong> Field groups load from theme JSON. If fields do not save, sync <strong>Site Options</strong> and <strong>Page Sections</strong> under ACF → Field Groups.</p></div>';
    }
});

// Remove corrupt legacy repeater rows that crash the options screen.
add_action('acf/init', function () {
    if (! is_admin()) {
        return;
    }

    foreach (['social_links', 'legal_links'] as $name) {
        $raw = get_option('options_' . $name);
        if ($raw === false) {
            continue;
        }

        if (! is_array(maybe_unserialize($raw))) {
            delete_option('options_' . $name);
            delete_option('_options_' . $name);
        }
    }
}, 5);

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
