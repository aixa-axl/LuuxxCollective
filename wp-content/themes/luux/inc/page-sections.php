<?php
/**
 * Page Sections — meta relinking and render helpers.
 * Isolated from Site Options bootstrap so options stay untouched.
 */

defined('ABSPATH') || exit;

function luux_acf_is_page_section_meta_key(string $key): bool {
    return (bool) preg_match('/^_?page_sections/', $key);
}

/** @return array<string, array<int, array<string, mixed>>> */
function luux_acf_page_section_layout_fields(): array {
    static $layouts = null;

    if ($layouts !== null) {
        return $layouts;
    }

    $layouts = [];
    $fc      = luux_acf_get_page_sections_field();

    if ($fc && ! empty($fc['layouts']) && is_array($fc['layouts'])) {
        foreach ($fc['layouts'] as $layout) {
            if (! empty($layout['name'])) {
                $layouts[$layout['name']] = $layout['sub_fields'] ?? [];
            }
        }
    }

    return $layouts;
}

function luux_acf_page_section_layout_key(string $layout_name): ?string {
    $fc = luux_acf_get_page_sections_field();

    if (! $fc || empty($fc['layouts']) || ! is_array($fc['layouts'])) {
        return null;
    }

    foreach ($fc['layouts'] as $layout) {
        if (($layout['name'] ?? '') === $layout_name) {
            return $layout['key'] ?? null;
        }
    }

    return null;
}

function luux_page_section_count_from_meta(array $meta): int {
    if (! empty($meta['page_sections'])) {
        return (int) $meta['page_sections'];
    }

    $max = -1;

    foreach (array_keys($meta) as $key) {
        if (preg_match('/^page_sections_(\d+)_acf_fc_layout$/', $key, $matches)) {
            $max = max($max, (int) $matches[1]);
        }
    }

    return $max + 1;
}

function luux_page_section_count(int $post_id): int {
    $stored = (int) get_post_meta($post_id, 'page_sections', true);
    if ($stored > 0) {
        return $stored;
    }

    $max = -1;
    $raw = get_metadata('post', $post_id);

    if (! is_array($raw)) {
        return 0;
    }

    foreach (array_keys($raw) as $key) {
        if (preg_match('/^page_sections_(\d+)_acf_fc_layout$/', $key, $matches)) {
            $max = max($max, (int) $matches[1]);
        }
    }

    return $max + 1;
}

/**
 * @param array<int, array<string, mixed>> $fields
 */
function luux_acf_resolve_section_field_key(array $fields, string $path): ?string {
    if (preg_match('/^([^_]+)_(\d+)_(.+)$/', $path, $matches)) {
        $repeater_name = $matches[1];
        $sub_path      = $matches[3];

        foreach ($fields as $field) {
            if (($field['name'] ?? '') !== $repeater_name) {
                continue;
            }

            if (($field['type'] ?? '') !== 'repeater') {
                continue;
            }

            return luux_acf_resolve_section_field_key($field['sub_fields'] ?? [], $sub_path);
        }

        return null;
    }

    foreach ($fields as $field) {
        $name = $field['name'] ?? '';
        $type = $field['type'] ?? '';

        if ($name === '') {
            continue;
        }

        if ($type === 'group' && str_starts_with($path, $name . '_')) {
            $resolved = luux_acf_resolve_section_field_key(
                $field['sub_fields'] ?? [],
                substr($path, strlen($name) + 1)
            );

            if ($resolved) {
                return $resolved;
            }
        }

        if ($path === $name) {
            return $field['key'] ?? null;
        }
    }

    return null;
}

/** @return array<string, mixed> */
function luux_acf_fix_section_meta_refs(array $meta): array {
    $layouts = luux_acf_page_section_layout_fields();
    $count   = luux_page_section_count_from_meta($meta);

    for ($i = 0; $i < $count; $i++) {
        $layout = $meta["page_sections_{$i}_acf_fc_layout"] ?? '';
        if ($layout === '' || ! isset($layouts[$layout])) {
            continue;
        }

        $layout_key = luux_acf_page_section_layout_key($layout);
        if ($layout_key) {
            $meta["_page_sections_{$i}"] = $layout_key;
        }

        $value_prefix = "page_sections_{$i}_";

        foreach (array_keys($meta) as $key) {
            if (! str_starts_with($key, $value_prefix)) {
                continue;
            }

            $path = substr($key, strlen($value_prefix));
            if ($path === '' || $path === 'acf_fc_layout') {
                continue;
            }

            $field_key = luux_acf_resolve_section_field_key($layouts[$layout], $path);
            if ($field_key) {
                $meta["_page_sections_{$i}_{$path}"] = $field_key;
            }
        }
    }

    $meta['_page_sections'] = 'field_luux_page_sections';

    if ($count > 0) {
        $meta['page_sections'] = $count;
    }

    return $meta;
}

/** @return array<string, mixed> */
function luux_acf_get_page_section_meta(int $post_id): array {
    $meta = [];
    $raw  = get_metadata('post', $post_id);

    if (! is_array($raw)) {
        return $meta;
    }

    foreach ($raw as $key => $values) {
        if (luux_acf_is_page_section_meta_key($key)) {
            $meta[$key] = $values[0];
        }
    }

    return luux_acf_fix_section_meta_refs($meta);
}

function luux_acf_relink_page_section_meta_for_post(int $post_id): void {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta === []) {
        return;
    }

    foreach ($meta as $key => $value) {
        if (! luux_acf_is_page_section_meta_key($key)) {
            continue;
        }

        update_post_meta($post_id, $key, $value);
    }
}

function luux_acf_relink_page_section_meta_keys(): void {
    $pages = get_posts([
        'post_type'      => 'page',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    foreach ($pages as $page_id) {
        luux_acf_relink_page_section_meta_for_post((int) $page_id);
    }
}
