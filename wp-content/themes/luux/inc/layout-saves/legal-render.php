<?php
/**
 * Legal layouts — frontend render fallback when ACF have_rows() finds nothing.
 * Scoped to legal_header / legal_section only.
 */

defined('ABSPATH') || exit;

/** @return list<string> */
function luux_acf_legal_layout_short_names(): array {
    return ['legal_header', 'legal_section'];
}

function luux_acf_legal_layout_matches_slug(string $layout): bool {
    return luux_acf_legal_header_layout_matches($layout)
        || luux_acf_legal_section_layout_matches($layout);
}

function luux_acf_legal_layout_to_short_name(string $layout): string {
    if (luux_acf_legal_header_layout_matches($layout)) {
        return 'legal_header';
    }

    if (luux_acf_legal_section_layout_matches($layout)) {
        return 'legal_section';
    }

    return '';
}

/**
 * @return array<int, string> db_index => short layout name
 */
function luux_acf_discover_legal_row_layouts(int $post_id): array {
    $layouts = [];
    $raw     = get_metadata('post', $post_id);

    if (! is_array($raw)) {
        $raw = [];
    }

    foreach (array_keys($raw) as $key) {
        if (! preg_match('/^page_sections_(\d+)_acf_fc_layout$/', $key, $matches)) {
            continue;
        }

        $index  = (int) $matches[1];
        $layout = luux_acf_resolve_meta_storage_value($raw[$key]);

        if (! is_string($layout) || ! luux_acf_legal_layout_matches_slug($layout)) {
            continue;
        }

        $layouts[$index] = luux_acf_legal_layout_to_short_name($layout);
    }

    $layout_list = luux_acf_parse_page_sections_layout_list($raw['page_sections'][0] ?? null);

    foreach ($layout_list as $i => $layout) {
        if (! luux_acf_legal_layout_matches_slug($layout)) {
            continue;
        }

        $index = (int) $i;

        if (! isset($layouts[$index])) {
            $layouts[$index] = luux_acf_legal_layout_to_short_name($layout);
        }
    }

    foreach (array_keys($raw) as $key) {
        if (preg_match('/^page_sections_(\d+)_clauses(?:_\d+_|$)/', $key, $matches)) {
            $index = (int) $matches[1];

            if (! isset($layouts[$index])) {
                $layouts[$index] = 'legal_section';
            }
        }
    }

    $header_stash = get_post_meta($post_id, '_luux_legal_header_stash', true);

    if (is_array($header_stash)) {
        foreach (array_keys($header_stash) as $row_key) {
            $index = (int) $row_key;

            if (! isset($layouts[$index])) {
                $layouts[$index] = 'legal_header';
            }
        }
    }

    $section_stash = get_post_meta($post_id, '_luux_legal_section_stash', true);

    if (is_array($section_stash)) {
        foreach (array_keys($section_stash) as $row_key) {
            $index = (int) $row_key;

            if (! isset($layouts[$index])) {
                $layouts[$index] = 'legal_section';
            }
        }
    }

    ksort($layouts);

    return $layouts;
}

/**
 * Ensure ACF layout keys exist for a legal row (does not migrate legacy list to integer count).
 */
function luux_acf_ensure_legal_layout_meta(int $post_id, int $db_index, string $layout): void {
    $layout = luux_acf_legal_layout_to_short_name($layout);

    if ($layout === '') {
        return;
    }

    $layout_key = function_exists('luux_acf_page_section_layout_key')
        ? luux_acf_page_section_layout_key($layout)
        : null;

    update_post_meta($post_id, 'page_sections_' . $db_index . '_acf_fc_layout', $layout);

    if ($layout_key) {
        luux_acf_replace_section_meta($post_id, '_page_sections_' . $db_index, $layout_key, 'field_luux_page_sections');
    }

    $stored = get_post_meta($post_id, 'page_sections', true);
    $list   = luux_acf_parse_page_sections_layout_list($stored);

    if ($list !== []) {
        while (count($list) <= $db_index) {
            $list[] = '';
        }

        if ($list[$db_index] !== $layout) {
            $list[$db_index] = $layout;
            update_post_meta($post_id, 'page_sections', $list);
        }

        return;
    }

    $count = is_numeric($stored) ? (int) $stored : 0;
    $count = max($count, $db_index + 1);

    luux_acf_replace_section_meta($post_id, 'page_sections', $count, 'field_luux_page_sections');
    update_post_meta($post_id, '_page_sections', 'field_luux_page_sections');
}

/**
 * @param array<string, mixed> $row_meta
 * @return array<string, mixed>
 */
function luux_acf_hydrate_legal_row_meta_from_stash(int $post_id, int $db_index, string $layout, array $row_meta): array {
    $stash_key = $layout === 'legal_header' ? '_luux_legal_header_stash' : '_luux_legal_section_stash';
    $stash     = get_post_meta($post_id, $stash_key, true);

    if (! is_array($stash)) {
        return $row_meta;
    }

    $fields = $stash[(string) $db_index] ?? null;

    if (! is_array($fields) || $fields === []) {
        return $row_meta;
    }

    $prefix = 'page_sections_0_';
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

        $row_meta[$meta_key]      = $value;
        $row_meta[$ref_prefix . $name] = $ref;
    }

    if ($layout === 'legal_section' && ($row_meta[$prefix . 'clauses'] ?? '') === '') {
        $clauses_json = $fields['_clauses_json'] ?? null;
        $clauses      = [];

        if (is_string($clauses_json) && $clauses_json !== '') {
            $clauses = luux_acf_legal_section_decode_clauses_json($clauses_json);
        }

        if ($clauses !== []) {
            $row_meta[$prefix . 'clauses'] = count($clauses);

            foreach ($clauses as $i => $clause) {
                if (! is_array($clause)) {
                    continue;
                }

                if (! empty($clause['title'])) {
                    $row_meta[$prefix . 'clauses_' . $i . '_title'] = (string) $clause['title'];
                    $row_meta[$ref_prefix . 'clauses_' . $i . '_title'] = 'field_luux_legal_section_clause_title';
                }

                if (! empty($clause['body'])) {
                    $row_meta[$prefix . 'clauses_' . $i . '_body'] = (string) $clause['body'];
                    $row_meta[$ref_prefix . 'clauses_' . $i . '_body'] = 'field_luux_legal_section_clause_body';
                }
            }

            $row_meta[$ref_prefix . 'clauses'] = 'field_luux_legal_section_clauses';
        }
    }

    return $row_meta;
}

/**
 * Render legal page sections directly from postmeta when primary ACF paths find nothing.
 */
function luux_render_legal_sections_from_meta(int $post_id): bool {
    if (! function_exists('acf_setup_meta') || ! function_exists('have_rows')) {
        return false;
    }

    $row_layouts = luux_acf_discover_legal_row_layouts($post_id);

    if ($row_layouts === []) {
        return false;
    }

    $full_meta = function_exists('luux_acf_get_page_section_meta')
        ? luux_acf_get_page_section_meta($post_id)
        : [];
    $rendered  = false;

    foreach ($row_layouts as $db_index => $layout) {
        if (function_exists('luux_build_single_row_meta') && $full_meta !== []) {
            $full_meta['page_sections_' . $db_index . '_acf_fc_layout'] = $layout;
            $row_meta = luux_build_single_row_meta($full_meta, $db_index);
        } else {
            $row_meta = [
                'page_sections'                 => 1,
                '_page_sections'                => 'field_luux_page_sections',
                'page_sections_0_acf_fc_layout' => $layout,
            ];

            $layout_key = function_exists('luux_acf_page_section_layout_key')
                ? luux_acf_page_section_layout_key($layout)
                : null;

            if ($layout_key) {
                $row_meta['_page_sections_0'] = $layout_key;
            }

            $value_prefix = 'page_sections_' . $db_index . '_';
            $ref_prefix   = '_page_sections_' . $db_index . '_';
            $raw          = get_metadata('post', $post_id);

            if (is_array($raw)) {
                foreach (array_keys($raw) as $key) {
                    if (str_starts_with($key, $value_prefix)) {
                        $suffix = substr($key, strlen($value_prefix));

                        if ($suffix !== '' && $suffix !== 'acf_fc_layout') {
                            $row_meta['page_sections_0_' . $suffix] = luux_acf_resolve_meta_storage_value($raw[$key]);
                        }
                    }

                    if (str_starts_with($key, $ref_prefix)) {
                        $suffix = substr($key, strlen($ref_prefix));

                        if ($suffix !== '') {
                            $row_meta['_page_sections_0_' . $suffix] = luux_acf_resolve_meta_storage_value($raw[$key]);
                        }
                    }
                }
            }
        }

        $row_meta = luux_acf_hydrate_legal_row_meta_from_stash($post_id, $db_index, $layout, $row_meta);

        acf_setup_meta($row_meta, $post_id, true);

        if (have_rows('page_sections', $post_id)) {
            while (have_rows('page_sections', $post_id)) {
                the_row();
                get_template_part('template-parts/layouts/' . str_replace('_', '-', $layout));
                $rendered = true;
            }
        }

        if (function_exists('acf_reset_meta')) {
            acf_reset_meta($post_id);
        }
    }

    return $rendered;
}
