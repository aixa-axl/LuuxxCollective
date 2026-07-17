<?php
/**
 * Luux theme setup.
 * Custom theme, no parent. Tailwind v4 compiled to assets/css/main.css.
 */

defined('ABSPATH') || exit;

require get_template_directory() . '/inc/instagram.php';
require get_template_directory() . '/inc/site-options.php';
require get_template_directory() . '/inc/acf-bootstrap.php';

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

/* ── ACF JSON sync + Site Options page ───────────────────── */
add_filter('acf/settings/save_json', fn() => get_template_directory() . '/acf-json');
add_filter('acf/settings/load_json', function ($paths) {
    $paths[] = get_template_directory() . '/acf-json';
    $paths[] = get_template_directory() . '/inc/acf-field-groups';
    return $paths;
});

add_action('acf/init', function () {
    if (! function_exists('acf_add_options_page')) {
        return;
    }

    acf_add_options_page([
        'page_title' => __('Site Options', 'luux'),
        'menu_title' => __('Site Options', 'luux'),
        'menu_slug'  => luux_site_options_slug(),
        'capability' => 'edit_posts',
        'post_id'    => 'options',
        'autoload'   => true,
        'redirect'   => false,
    ]);
}, 0);

add_action('admin_enqueue_scripts', function (string $hook): void {
    if ($hook !== 'toplevel_page_' . luux_site_options_slug()) {
        return;
    }

    wp_enqueue_media();
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
 * Hero group tag settings from the first hero section on the current page.
 *
 * @return array{show: bool, logo: int}
 */
function luux_get_hero_group_tag(): array {
    $show = true;
    $logo = 0;

    if (! function_exists('get_field')) {
        return ['show' => $show, 'logo' => $logo];
    }

    $post_id = (int) get_queried_object_id();
    if (! $post_id) {
        return ['show' => $show, 'logo' => $logo];
    }

    if (
        function_exists('luux_page_sections_uses_legacy_storage')
        && luux_page_sections_uses_legacy_storage($post_id)
        && function_exists('luux_get_hero_group_tag_from_meta')
    ) {
        return luux_get_hero_group_tag_from_meta($post_id);
    }

    $sections = get_field('page_sections', $post_id);
    if (empty($sections) || ! is_array($sections)) {
        return ['show' => $show, 'logo' => $logo];
    }

    $hero_layouts = ['hero', 'resort_hero', 'contact_hero'];

    foreach ($sections as $section) {
        $layout = $section['acf_fc_layout'] ?? '';
        if (! in_array($layout, $hero_layouts, true)) {
            continue;
        }

        if ($layout === 'hero') {
            $show = $section['show_group_tag'] ?? true;
            if ($show === null || $show === '') {
                $show = true;
            }

            $logo = $section['group_tag_logo'] ?? 0;
            if (is_array($logo)) {
                $logo = (int) ($logo['ID'] ?? $logo['id'] ?? 0);
            } else {
                $logo = (int) $logo;
            }
        }

        break;
    }

    return ['show' => (bool) $show, 'logo' => $logo];
}

function luux_render_sections(): void {
    $post_id = get_the_ID();
    if (! $post_id) {
        return;
    }

    $legacy = function_exists('luux_page_sections_uses_legacy_storage')
        && luux_page_sections_uses_legacy_storage($post_id);

    // Legacy imports: row-by-row postmeta render so editor saves reach the frontend.
    if ($legacy) {
        if (function_exists('luux_render_page_sections_by_row') && luux_render_page_sections_by_row($post_id)) {
            return;
        }

        if (function_exists('have_rows') && luux_loop_page_sections($post_id)) {
            return;
        }

        return;
    }

    if (function_exists('luux_render_sections_from_meta') && luux_render_sections_from_meta($post_id)) {
        return;
    }

    if (function_exists('have_rows') && luux_loop_page_sections($post_id)) {
        return;
    }

    if (function_exists('luux_render_page_sections_by_row') && luux_render_page_sections_by_row($post_id)) {
        return;
    }
}

function luux_loop_page_sections(int $post_id): bool {
    if (! have_rows('page_sections', $post_id)) {
        return false;
    }

    while (have_rows('page_sections', $post_id)) {
        the_row();
        $layout = str_replace('_', '-', get_row_layout());
        get_template_part('template-parts/layouts/' . $layout);
    }

    return true;
}

function luux_render_sections_from_meta(int $post_id): bool {
    if (! function_exists('acf_setup_meta') || ! function_exists('have_rows')) {
        return false;
    }

    if (! function_exists('luux_acf_merged_page_meta')) {
        return false;
    }

    $meta = luux_acf_merged_page_meta($post_id);
    if ($meta === null) {
        return false;
    }

    acf_setup_meta($meta, $post_id, true);
    $rendered = luux_loop_page_sections($post_id);

    if (function_exists('acf_reset_meta')) {
        acf_reset_meta($post_id);
    }

    return $rendered;
}

/* ── Light hardening / cleanup ──────────────────────────── */
remove_action('wp_head', 'wp_generator');
add_filter('xmlrpc_enabled', '__return_false');

// Brochure site: comments off everywhere.
add_action('admin_init', function () {
    update_option('default_comment_status', 'closed');
});
