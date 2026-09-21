<?php
/**
 * Section render fallback — renders each page_sections row once from postmeta.
 * Used when the normal ACF have_rows() loop finds nothing useful.
 * Hydrates legal_header / legal_section from stash when needed.
 */

defined('ABSPATH') || exit;

/**
 * Format legal wysiwyg HTML for front-end output (paragraphs + line breaks).
 */
function luux_format_legal_html(mixed $html): string {
    if (! is_string($html)) {
        return '';
    }

    $html = trim($html);

    if ($html === '') {
        return '';
    }

    // Undo accidental double-encoding from AJAX/REST round-trips.
    if (
        str_contains($html, '&lt;br')
        || str_contains($html, '&lt;p')
        || str_contains($html, '&lt;div')
        || str_contains($html, '&lt;strong')
    ) {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // Repair stripslashes corruption: JSON "\n\n" became literal "nn".
    // Only at sentence boundaries / before capitals — never inside words like "connection".
    $html = preg_replace('/(?<=[.!?…])nn+(?=\s*[A-Z“"])/', "<br /><br />", $html) ?? $html;
    $html = preg_replace('/(?<=[.!?…])nn+/', "<br /><br />", $html) ?? $html;
    $html = preg_replace('/(?<=[a-z0-9])nn(?=[A-Z])/', "<br /><br />", $html) ?? $html;

    // Normalise break tags (TinyMCE / paste variants).
    $html = preg_replace('/<br\s*\/?>/i', '<br />', $html) ?? $html;

    $has_block = (bool) preg_match('/<\s*(?:p|div|ul|ol|li|h[1-6]|table)\b/i', $html);
    $has_br    = (bool) preg_match('/<\s*br\s*\/?>/i', $html);

    if (! $has_block && ! $has_br) {
        // Plain text or inline-only markup — preserve line breaks.
        if (preg_match('/\r\n|\r|\n/', $html)) {
            $html = preg_replace('/\r\n|\r|\n/', "<br />\n", $html) ?? $html;
        } else {
            $html = wpautop($html);
        }
    } elseif (! $has_br && preg_match('/\r\n|\r|\n/', $html)) {
        // Has tags (e.g. <strong>) but breaks were stored as newlines — keep tags, add <br>.
        $html = preg_replace('/\r\n|\r|\n/', "<br />\n", $html) ?? $html;
    }

    // Allow br explicitly in case a host kses config is tight.
    $allowed       = wp_kses_allowed_html('post');
    $allowed['br'] = [
        'class' => true,
        'style' => true,
        'clear' => true,
    ];

    return wp_kses($html, $allowed);
}

/** @return list<string> */
function luux_acf_legal_layout_short_names(): array {
    return ['legal_header', 'legal_section'];
}

function luux_acf_legal_layout_matches_slug(string $layout): bool {
    return (function_exists('luux_acf_legal_header_layout_matches') && luux_acf_legal_header_layout_matches($layout))
        || (function_exists('luux_acf_legal_section_layout_matches') && luux_acf_legal_section_layout_matches($layout));
}

function luux_acf_legal_layout_to_short_name(string $layout): string {
    if (function_exists('luux_acf_legal_header_layout_matches') && luux_acf_legal_header_layout_matches($layout)) {
        return 'legal_header';
    }

    if (function_exists('luux_acf_legal_section_layout_matches') && luux_acf_legal_section_layout_matches($layout)) {
        return 'legal_section';
    }

    return '';
}

/**
 * Normalize stored layout slug to the template name (e.g. legal_header, hero).
 */
function luux_acf_normalize_section_layout_slug(string $layout): string {
    $layout = trim($layout);

    if ($layout === '') {
        return '';
    }

    $legal = luux_acf_legal_layout_to_short_name($layout);

    if ($legal !== '') {
        return $legal;
    }

    if (str_starts_with($layout, 'layout_luux_')) {
        return substr($layout, strlen('layout_luux_'));
    }

    if (str_starts_with($layout, 'layout_')) {
        return substr($layout, strlen('layout_'));
    }

    return $layout;
}

/**
 * Layouts currently declared by the editor (page_sections list or integer count).
 * Does NOT invent rows from stash / orphan field keys — empty editor = empty list.
 * Falls back to acf_fc_layout keys when count was wiped to 0 but layout shells remain.
 *
 * @return array<int, string> db_index => short layout name
 */
function luux_acf_authoritative_section_row_layouts(int $post_id): array {
    $layouts = [];
    $stored  = get_post_meta($post_id, 'page_sections', true);
    $list    = function_exists('luux_acf_parse_page_sections_layout_list')
        ? luux_acf_parse_page_sections_layout_list($stored)
        : [];

    if ($list !== []) {
        foreach ($list as $i => $layout) {
            if (! is_string($layout) || $layout === '') {
                continue;
            }

            $layouts[(int) $i] = luux_acf_normalize_section_layout_slug($layout);
        }

        return array_filter($layouts, static fn ($layout) => is_string($layout) && $layout !== '');
    }

    $count = is_numeric($stored) ? (int) $stored : 0;

    if ($count > 0) {
        for ($i = 0; $i < $count; $i++) {
            $layout = get_post_meta($post_id, 'page_sections_' . $i . '_acf_fc_layout', true);

            if (! is_string($layout) || $layout === '') {
                continue;
            }

            $layouts[$i] = luux_acf_normalize_section_layout_slug($layout);
        }

        if ($layouts !== []) {
            return array_filter($layouts, static fn ($layout) => is_string($layout) && $layout !== '');
        }
    }

    // Count stuck at 0 after a wipe, but layout shells (or hero field rows) still exist.
    $raw = get_metadata('post', $post_id);

    if (! is_array($raw)) {
        return [];
    }

    foreach (array_keys($raw) as $key) {
        if (! preg_match('/^page_sections_(\d+)_acf_fc_layout$/', $key, $matches)) {
            continue;
        }

        $index  = (int) $matches[1];
        $layout = luux_acf_resolve_meta_storage_value($raw[$key]);

        if (! is_string($layout) || $layout === '') {
            continue;
        }

        $layouts[$index] = luux_acf_normalize_section_layout_slug($layout);
    }

    if ($layouts !== []) {
        ksort($layouts);

        return array_filter($layouts, static fn ($layout) => is_string($layout) && $layout !== '');
    }

    // Hero fields saved without an acf_fc_layout shell (custom persist after wipe).
    // Use hero-specific keys only — never plain "heading" (legal_header also has that).
    foreach (array_keys($raw) as $key) {
        if (! preg_match('/^page_sections_(\d+)_(?:background_image|background_video|subheading|media_type)$/', $key, $matches)) {
            continue;
        }

        $index = (int) $matches[1];

        if (! isset($layouts[$index])) {
            $layouts[$index] = 'hero';
        }
    }

    $hero_stash = get_post_meta($post_id, '_luux_hero_stash', true);

    if (is_array($hero_stash)) {
        foreach (array_keys($hero_stash) as $row_key) {
            $index = (int) $row_key;

            if (! isset($layouts[$index])) {
                $layouts[$index] = 'hero';
            }
        }
    }

    ksort($layouts);

    return array_filter($layouts, static fn ($layout) => is_string($layout) && $layout !== '');
}

/**
 * @return array<int, string> db_index => short layout name
 */
function luux_acf_discover_legal_row_layouts(int $post_id): array {
    $all = luux_acf_discover_all_section_row_layouts($post_id);
    $out = [];

    foreach ($all as $index => $layout) {
        if (luux_acf_legal_layout_matches_slug($layout)) {
            $out[$index] = luux_acf_legal_layout_to_short_name($layout);
        }
    }

    return $out;
}

/**
 * Discover every page_sections row on a page (all layout types).
 * Authoritative editor list only — stash hydrates content, it does not create rows.
 *
 * @return array<int, string> db_index => short layout name
 */
function luux_acf_discover_all_section_row_layouts(int $post_id): array {
    return luux_acf_authoritative_section_row_layouts($post_id);
}

/**
 * Drop legal stash + orphan legal field meta for rows no longer in the editor list.
 * Scoped to legal_header / legal_section only — other layouts untouched.
 */
function luux_acf_prune_orphaned_legal_meta(int $post_id): void {
    if ($post_id < 1 || get_post_type($post_id) !== 'page') {
        return;
    }

    $layouts      = luux_acf_authoritative_section_row_layouts($post_id);
    $keep_header  = [];
    $keep_section = [];

    foreach ($layouts as $index => $layout) {
        $short = luux_acf_legal_layout_to_short_name((string) $layout);

        if ($short === 'legal_header') {
            $keep_header[(int) $index] = true;
        }

        if ($short === 'legal_section') {
            $keep_section[(int) $index] = true;
        }
    }

    $header_stash = get_post_meta($post_id, '_luux_legal_header_stash', true);

    if (is_array($header_stash)) {
        if ($keep_header === []) {
            delete_post_meta($post_id, '_luux_legal_header_stash');
        } else {
            $next = [];

            foreach ($header_stash as $row_key => $fields) {
                if (isset($keep_header[(int) $row_key])) {
                    $next[(string) (int) $row_key] = $fields;
                }
            }

            if ($next === []) {
                delete_post_meta($post_id, '_luux_legal_header_stash');
            } elseif ($next !== $header_stash) {
                update_post_meta($post_id, '_luux_legal_header_stash', $next);
            }
        }
    }

    $section_stash = get_post_meta($post_id, '_luux_legal_section_stash', true);

    if (is_array($section_stash)) {
        if ($keep_section === []) {
            delete_post_meta($post_id, '_luux_legal_section_stash');
        } else {
            $next = [];

            foreach ($section_stash as $row_key => $fields) {
                if (isset($keep_section[(int) $row_key])) {
                    $next[(string) (int) $row_key] = $fields;
                }
            }

            if ($next === []) {
                delete_post_meta($post_id, '_luux_legal_section_stash');
            } elseif ($next !== $section_stash) {
                update_post_meta($post_id, '_luux_legal_section_stash', $next);
            }
        }
    }

    // Remove leftover legal-only field keys for indices no longer declared as legal.
    $raw = get_metadata('post', $post_id);

    if (! is_array($raw)) {
        return;
    }

    foreach (array_keys($raw) as $key) {
        if (! is_string($key)) {
            continue;
        }

        // Clauses only exist on legal_section — safe to drop when that row is gone.
        if (preg_match('/^_?page_sections_(\d+)_clauses(?:_.*)?$/', $key, $matches)) {
            $index = (int) $matches[1];

            if (! isset($keep_section[$index])) {
                delete_post_meta($post_id, $key);
            }

            continue;
        }

        // Shared names (heading/intro/section_id): only remove when the row index
        // is no longer in the editor list at all (orphan), never when another layout owns it.
        if (preg_match('/^_?page_sections_(\d+)_(?:heading|intro|section_id)$/', $key, $matches)) {
            $index = (int) $matches[1];

            if (! isset($layouts[$index])) {
                delete_post_meta($post_id, $key);
            }
        }
    }

    foreach (array_keys($raw) as $key) {
        if (! is_string($key) || ! preg_match('/^_?page_sections_(\d+)_acf_fc_layout$/', $key, $matches)) {
            continue;
        }

        $index = (int) $matches[1];

        if (isset($layouts[$index])) {
            continue;
        }

        $layout_val = luux_acf_resolve_meta_storage_value($raw[$key] ?? []);

        if (! is_string($layout_val) || ! luux_acf_legal_layout_matches_slug($layout_val)) {
            continue;
        }

        delete_post_meta($post_id, $key);
    }
}

/**
 * One-shot: prune orphaned legal stash/meta on pages that no longer declare legal layouts.
 * Runs once after deploy so Terms goes blank when layouts were already deleted.
 */
function luux_acf_maybe_prune_orphaned_legal_sitewide(): void {
    if (get_option('luux_legal_orphan_prune_v1')) {
        return;
    }

    $page_ids = get_posts([
        'post_type'              => 'page',
        'post_status'            => 'any',
        'posts_per_page'         => -1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'meta_query'             => [
            'relation' => 'OR',
            [
                'key'     => '_luux_legal_header_stash',
                'compare' => 'EXISTS',
            ],
            [
                'key'     => '_luux_legal_section_stash',
                'compare' => 'EXISTS',
            ],
        ],
    ]);

    foreach ($page_ids as $page_id) {
        luux_acf_prune_orphaned_legal_meta((int) $page_id);
    }

    update_option('luux_legal_orphan_prune_v1', 1, false);
}

/**
 * Wipe every page_sections row + legal stash on a page (fresh start).
 * Scoped helper — only call for pages the editor intentionally cleared.
 */
function luux_acf_wipe_page_sections_completely(int $post_id): void {
    if ($post_id < 1 || get_post_type($post_id) !== 'page') {
        return;
    }

    delete_post_meta($post_id, '_luux_legal_header_stash');
    delete_post_meta($post_id, '_luux_legal_section_stash');

    $raw = get_metadata('post', $post_id);

    if (! is_array($raw)) {
        $raw = [];
    }

    foreach (array_keys($raw) as $key) {
        if (! is_string($key)) {
            continue;
        }

        if (
            $key === 'page_sections'
            || $key === '_page_sections'
            || str_starts_with($key, 'page_sections_')
            || str_starts_with($key, '_page_sections_')
        ) {
            delete_post_meta($post_id, $key);
        }
    }

    update_post_meta($post_id, 'page_sections', 0);
    update_post_meta($post_id, '_page_sections', 'field_luux_page_sections');
}

/**
 * One-shot v2: Terms was re-populated by stash restore after layouts were deleted.
 * Fully clear the Terms page so the editor can start again. Does not touch other pages.
 */
function luux_acf_maybe_reset_terms_page_v2(): void {
    if (get_option('luux_legal_terms_reset_v2')) {
        return;
    }

    $slugs = ['terms-conditions', 'terms-and-conditions', 'terms'];

    foreach ($slugs as $slug) {
        $pages = get_posts([
            'name'                   => $slug,
            'post_type'              => 'page',
            'post_status'            => 'any',
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        foreach ($pages as $page_id) {
            luux_acf_wipe_page_sections_completely((int) $page_id);
        }
    }

    update_option('luux_legal_terms_reset_v2', 1, false);
}

/**
 * One-shot v3: after the Terms wipe, Hero field meta could save while page_sections
 * stayed at 0 — repair the FC shell so the hero renders without another editor dance.
 */
function luux_acf_maybe_repair_terms_hero_shell_v3(): void {
    if (get_option('luux_legal_terms_hero_repair_v3')) {
        return;
    }

    if (! function_exists('luux_acf_ensure_hero_layout_meta')) {
        return;
    }

    $slugs = ['terms-conditions', 'terms-and-conditions', 'terms'];

    foreach ($slugs as $slug) {
        $pages = get_posts([
            'name'                   => $slug,
            'post_type'              => 'page',
            'post_status'            => 'any',
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        ]);

        foreach ($pages as $page_id) {
            $page_id = (int) $page_id;
            $stored  = get_post_meta($page_id, 'page_sections', true);
            $list    = luux_acf_parse_page_sections_layout_list($stored);
            $count   = is_numeric($stored) ? (int) $stored : 0;

            if ($list !== [] || $count > 0) {
                // Still ensure acf_fc_layout if hero fields exist at 0.
                if (get_post_meta($page_id, 'page_sections_0_heading', true) !== ''
                    || get_post_meta($page_id, 'page_sections_0_background_image', true) !== ''
                    || get_post_meta($page_id, '_luux_hero_stash', true)
                ) {
                    luux_acf_ensure_hero_layout_meta($page_id, 0);
                }

                continue;
            }

            $has_hero_fields = get_post_meta($page_id, 'page_sections_0_heading', true) !== ''
                || get_post_meta($page_id, 'page_sections_0_subheading', true) !== ''
                || get_post_meta($page_id, 'page_sections_0_background_image', true) !== ''
                || get_post_meta($page_id, 'page_sections_0_media_type', true) !== '';

            $stash = get_post_meta($page_id, '_luux_hero_stash', true);

            if (! $has_hero_fields && ! (is_array($stash) && $stash !== [])) {
                continue;
            }

            luux_acf_ensure_hero_layout_meta($page_id, 0);

            // Rehydrate field values from stash if the FC shell was empty.
            if (function_exists('luux_acf_restore_hero_from_stash')) {
                luux_acf_restore_hero_from_stash($page_id);
            }
        }
    }

    update_option('luux_legal_terms_hero_repair_v3', 1, false);
}

/**
 * One-shot v4: Legal Header added after Hero often saved fields/stash without growing
 * page_sections count — ensure the FC shell exists and refill from stash.
 */
function luux_acf_maybe_repair_terms_legal_header_v4(): void {
    if (get_option('luux_legal_terms_legal_header_repair_v4')) {
        return;
    }

    $slugs = ['terms-conditions', 'terms-and-conditions', 'terms'];

    foreach ($slugs as $slug) {
        $pages = get_posts([
            'name'                   => $slug,
            'post_type'              => 'page',
            'post_status'            => 'any',
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        ]);

        foreach ($pages as $page_id) {
            $page_id = (int) $page_id;
            $stored  = get_post_meta($page_id, 'page_sections', true);
            $count   = is_numeric($stored) ? (int) $stored : 0;
            $list    = luux_acf_parse_page_sections_layout_list($stored);

            if ($list !== []) {
                $count = max($count, count($list));
            }

            $stash = get_post_meta($page_id, '_luux_legal_header_stash', true);

            $stash_max = 0;

            if (is_array($stash) && $stash !== []) {
                $stash_max = max(array_map('intval', array_keys($stash))) + 1;
            }

            // Scan row indexes for legal_header shells or orphan heading/intro.
            $max = max($count, $stash_max, 2);

            for ($i = 0; $i < $max; $i++) {
                $layout = (string) get_post_meta($page_id, 'page_sections_' . $i . '_acf_fc_layout', true);
                $layout = function_exists('luux_acf_normalize_section_layout_slug')
                    ? luux_acf_normalize_section_layout_slug($layout)
                    : $layout;

                $has_fields = get_post_meta($page_id, 'page_sections_' . $i . '_heading', true) !== ''
                    || get_post_meta($page_id, 'page_sections_' . $i . '_intro', true) !== '';

                $in_stash = is_array($stash) && ! empty($stash[(string) $i]);

                // Don't claim index 0 (usually Hero). Only ensure legal_header shells / orphans.
                if ($layout === 'legal_header' || (($has_fields || $in_stash) && $layout === '' && $i > 0)) {
                    luux_acf_ensure_legal_layout_meta($page_id, $i, 'legal_header');
                }
            }

            if (function_exists('luux_acf_restore_legal_header_from_stash')) {
                luux_acf_restore_legal_header_from_stash($page_id);
            }
        }
    }

    update_option('luux_legal_terms_legal_header_repair_v4', 1, false);
}

add_action('init', 'luux_acf_maybe_prune_orphaned_legal_sitewide', 20);
add_action('init', 'luux_acf_maybe_reset_terms_page_v2', 21);
add_action('init', 'luux_acf_maybe_repair_terms_hero_shell_v3', 22);
add_action('init', 'luux_acf_maybe_repair_terms_legal_header_v4', 23);

add_action('acf/save_post', function ($post_id): void {
    if (! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page') {
        return;
    }

    luux_acf_prune_orphaned_legal_meta((int) $post_id);
}, 100000);

add_action('save_post_page', function (int $post_id): void {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    luux_acf_prune_orphaned_legal_meta($post_id);
}, 100000);

add_action('rest_after_insert_page', function (\WP_Post $post): void {
    if ($post->post_type !== 'page') {
        return;
    }

    luux_acf_prune_orphaned_legal_meta((int) $post->ID);
}, 100000);

/**
 * Ensure ACF layout keys exist for a legal row (does not migrate legacy list to integer count).
 * Grows modern page_sections count when saving a new legal row after Hero (etc).
 */
function luux_acf_ensure_legal_layout_meta(int $post_id, int $db_index, string $layout): void {
    $layout = luux_acf_legal_layout_to_short_name($layout);

    if ($layout === '') {
        return;
    }

    $db_index = (int) $db_index;
    $stored   = get_post_meta($post_id, 'page_sections', true);
    $list     = luux_acf_parse_page_sections_layout_list($stored);

    if ($list !== []) {
        // Only fill an existing FC slot — do not append layouts the editor removed.
        if (! isset($list[$db_index])) {
            return;
        }

        $current = luux_acf_normalize_section_layout_slug((string) $list[$db_index]);

        // Do not convert a different layout into a legal layout.
        if ($current !== '' && $current !== $layout && ! luux_acf_legal_layout_matches_slug($current)) {
            return;
        }

        if ($list[$db_index] !== $layout) {
            $list[$db_index] = $layout;
            update_post_meta($post_id, 'page_sections', $list);
        }
    } else {
        $count = is_numeric($stored) ? (int) $stored : 0;
        $count = max($count, $db_index + 1);

        update_post_meta($post_id, 'page_sections', $count);
        update_post_meta($post_id, '_page_sections', 'field_luux_page_sections');
    }

    $layout_key = function_exists('luux_acf_page_section_layout_key')
        ? luux_acf_page_section_layout_key($layout)
        : null;

    update_post_meta($post_id, 'page_sections_' . $db_index . '_acf_fc_layout', $layout);

    if ($layout_key) {
        update_post_meta($post_id, '_page_sections_' . $db_index, $layout_key);
    }
}

/**
 * @param array<string, mixed> $row_meta
 * @return array<string, mixed>
 */
function luux_acf_hydrate_legal_row_meta_from_stash(int $post_id, int $db_index, string $layout, array $row_meta): array {
    $layout = luux_acf_legal_layout_to_short_name($layout);

    if ($layout === '') {
        return $row_meta;
    }

    $stash_key = $layout === 'legal_header' ? '_luux_legal_header_stash' : '_luux_legal_section_stash';
    $stash     = get_post_meta($post_id, $stash_key, true);

    if (! is_array($stash)) {
        return $row_meta;
    }

    $fields = $stash[(string) $db_index] ?? null;

    if (! is_array($fields) || $fields === []) {
        return $row_meta;
    }

    $prefix     = 'page_sections_0_';
    $ref_prefix = '_page_sections_0_';

    if ($layout === 'legal_header') {
        $field_map = luux_acf_legal_header_field_map();
    } else {
        $field_map = luux_acf_legal_section_field_map();
    }

    foreach ($field_map as $field_key => [$name, $ref]) {
        $meta_key = $prefix . $name;

        if (($row_meta[$meta_key] ?? '') !== '') {
            continue;
        }

        $value = $fields[$name] ?? null;

        if ($value === null || $value === '') {
            continue;
        }

        $row_meta[$meta_key]           = $value;
        $row_meta[$ref_prefix . $name] = $ref;
    }

    if ($layout === 'legal_section' && ($row_meta[$prefix . 'clauses'] ?? '') === '') {
        $clauses_json = $fields['_clauses_json'] ?? null;
        $clauses      = [];

        if (is_string($clauses_json) && $clauses_json !== '' && function_exists('luux_acf_legal_section_decode_clauses_json')) {
            $clauses = luux_acf_legal_section_decode_clauses_json($clauses_json);
        }

        if ($clauses !== []) {
            $row_meta[$prefix . 'clauses'] = count($clauses);

            foreach ($clauses as $i => $clause) {
                if (! is_array($clause)) {
                    continue;
                }

                if (! empty($clause['title'])) {
                    $row_meta[$prefix . 'clauses_' . $i . '_title']       = (string) $clause['title'];
                    $row_meta[$ref_prefix . 'clauses_' . $i . '_title'] = 'field_luux_legal_section_clause_title';
                }

                if (! empty($clause['body'])) {
                    $row_meta[$prefix . 'clauses_' . $i . '_body']       = (string) $clause['body'];
                    $row_meta[$ref_prefix . 'clauses_' . $i . '_body'] = 'field_luux_legal_section_clause_body';
                }
            }

            $row_meta[$ref_prefix . 'clauses'] = 'field_luux_legal_section_clauses';
        }
    }

    return $row_meta;
}

/**
 * Build an isolated single-row meta payload for acf_setup_meta().
 *
 * @return array<string, mixed>
 */
function luux_acf_build_isolated_section_row_meta(int $post_id, int $db_index, string $layout, array $full_meta): array {
    $layout = luux_acf_normalize_section_layout_slug($layout);

    $full_meta['page_sections_' . $db_index . '_acf_fc_layout'] = $layout;

    if (function_exists('luux_build_single_row_meta') && $full_meta !== []) {
        $row_meta = luux_build_single_row_meta($full_meta, $db_index);
    } else {
        $row_meta = [
            'page_sections'                 => 1,
            '_page_sections'                => 'field_luux_page_sections',
            'page_sections_0_acf_fc_layout' => $layout,
        ];
    }

    $row_meta['page_sections']                 = 1;
    $row_meta['_page_sections']                = 'field_luux_page_sections';
    $row_meta['page_sections_0_acf_fc_layout'] = $layout;

    $layout_key = function_exists('luux_acf_page_section_layout_key')
        ? luux_acf_page_section_layout_key($layout)
        : null;

    if ($layout_key) {
        $row_meta['_page_sections_0'] = $layout_key;
    }

    if (luux_acf_legal_layout_matches_slug($layout)) {
        $row_meta = luux_acf_hydrate_legal_row_meta_from_stash($post_id, $db_index, $layout, $row_meta);
    }

    return $row_meta;
}

/**
 * Last-resort renderer: one pass per saved section row (Hero, legal, etc).
 * Never uses a multi-iteration have_rows while() with a fixed layout name — that caused duplicates.
 */
function luux_render_legal_sections_from_meta(int $post_id): bool {
    return luux_render_sections_from_direct_meta($post_id);
}

/**
 * @return bool True when at least one section template was included.
 */
function luux_render_sections_from_direct_meta(int $post_id): bool {
    if (! function_exists('acf_setup_meta') || ! function_exists('have_rows')) {
        return false;
    }

    $row_layouts = luux_acf_discover_all_section_row_layouts($post_id);

    if ($row_layouts === []) {
        return false;
    }

    $full_meta = function_exists('luux_acf_get_page_section_meta')
        ? luux_acf_get_page_section_meta($post_id)
        : [];

    if (! is_array($full_meta)) {
        $full_meta = [];
    }

    $rendered = false;

    foreach ($row_layouts as $db_index => $layout) {
        $layout = luux_acf_normalize_section_layout_slug((string) $layout);

        if ($layout === '') {
            continue;
        }

        $row_meta = luux_acf_build_isolated_section_row_meta($post_id, (int) $db_index, $layout, $full_meta);

        acf_setup_meta($row_meta, $post_id, true);

        if (function_exists('luux_set_section_row_index_override')) {
            luux_set_section_row_index_override((int) $db_index);
        }

        if (have_rows('page_sections', $post_id)) {
            the_row();
            get_template_part('template-parts/layouts/' . str_replace('_', '-', $layout));
            $rendered = true;

            // Drain any extra rows without rendering — prevents duplicate legal blocks.
            while (have_rows('page_sections', $post_id)) {
                the_row();
            }
        }

        if (function_exists('luux_set_section_row_index_override')) {
            luux_set_section_row_index_override(null);
        }

        if (function_exists('acf_reset_meta')) {
            acf_reset_meta($post_id);
        }
    }

    return $rendered;
}
