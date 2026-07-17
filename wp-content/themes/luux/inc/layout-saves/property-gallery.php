<?php
/**
 * Property Gallery layout — direct postmeta save/read for legacy imported pages.
 * Scalars are blocked + custom-saved; images repeater stays unblocked.
 */

defined('ABSPATH') || exit;

const LUUX_PROPERTY_GALLERY_STASH_META = '_luux_property_gallery_stash';
const LUUX_PROPERTY_GALLERY_LAYOUT     = 'property_gallery';

/** @return array<string, array{0: string, 1: string, 2: string}> */
function luux_acf_property_gallery_field_map(): array {
    return [
        'field_luux_property_gallery_heading'    => ['heading', 'field_luux_property_gallery_heading', 'text'],
        'field_luux_property_gallery_text'       => ['text', 'field_luux_property_gallery_text', 'text'],
        'field_luux_property_gallery_section_id' => ['section_id', 'field_luux_property_gallery_section_id', 'text'],
    ];
}

function luux_acf_property_gallery_layout_matches(string $layout): bool {
    return in_array($layout, [LUUX_PROPERTY_GALLERY_LAYOUT, 'layout_luux_property_gallery'], true);
}

/** @return list<int> */
function luux_acf_property_gallery_db_row_indices(int $post_id): array {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta === []) {
        return [];
    }

    $indices = [];
    $count   = luux_acf_page_sections_row_count($meta);

    for ($i = 0; $i < $count; $i++) {
        if (luux_acf_property_gallery_layout_matches(luux_page_section_layout_slug($meta, $i))) {
            $indices[] = $i;
        }
    }

    return $indices;
}

function luux_acf_property_gallery_row_layout(int $post_id, int $index): string {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta !== []) {
        return luux_page_section_layout_slug($meta, $index);
    }

    return luux_page_section_layout_slug(
        ['page_sections' => get_post_meta($post_id, 'page_sections', true)],
        $index
    );
}

function luux_acf_resolve_property_gallery_db_index(int $post_id, int $row_index, int $row_nth = 0): int {
    $resolved = luux_find_section_row_index($post_id, LUUX_PROPERTY_GALLERY_LAYOUT);

    if ($resolved !== null) {
        return $resolved;
    }

    $db_indices = luux_acf_property_gallery_db_row_indices($post_id);

    if (isset($db_indices[$row_nth])) {
        return $db_indices[$row_nth];
    }

    if (luux_acf_property_gallery_layout_matches(luux_acf_property_gallery_row_layout($post_id, $row_index))) {
        return $row_index;
    }

    return $row_index;
}

/** @return array<string, mixed>|null */
function luux_acf_property_gallery_rest_payload(?array $set = null, bool $reset = false): ?array {
    static $payload = null;

    if ($reset) {
        $payload = null;

        return null;
    }

    if ($set !== null) {
        $payload = $set;
    }

    return $payload;
}

function luux_acf_property_gallery_capture_rest_request(WP_REST_Request $request): void {
    $params = $request->get_json_params();

    if (! is_array($params)) {
        return;
    }

    if (! empty($params['acf']) && is_array($params['acf'])) {
        luux_acf_property_gallery_rest_payload($params['acf']);

        return;
    }

    if (! empty($params['meta']['acf']) && is_array($params['meta']['acf'])) {
        luux_acf_property_gallery_rest_payload($params['meta']['acf']);
    }
}

function luux_acf_property_gallery_attachment_id(mixed $value): int {
    if (is_numeric($value)) {
        return (int) $value;
    }

    if (is_array($value) && ! empty($value['ID'])) {
        return (int) $value['ID'];
    }

    if (is_array($value) && ! empty($value['id'])) {
        return (int) $value['id'];
    }

    return 0;
}

/** @return list<string> */
function luux_acf_property_gallery_valid_sizes(): array {
    return ['col1-top', 'col1-bottom', 'col2-top', 'col2-bottom', 'col3-top', 'col3-bottom'];
}

/**
 * @param array<int, array<string, mixed>> $images
 * @return list<array{image: int, size: string}>
 */
function luux_acf_property_gallery_normalize_images(array $images): array {
    $normalized  = [];
    $valid_sizes = luux_acf_property_gallery_valid_sizes();

    foreach ($images as $item) {
        if (! is_array($item)) {
            continue;
        }

        $image = $item['field_luux_property_gallery_image'] ?? $item['image'] ?? null;
        $size  = $item['field_luux_property_gallery_size'] ?? $item['size'] ?? null;

        if ($image === null || $image === '' || $size === null || $size === '') {
            continue;
        }

        $image_id = luux_acf_property_gallery_attachment_id($image);

        if ($image_id < 1) {
            continue;
        }

        $size = (string) $size;

        if (! in_array($size, $valid_sizes, true)) {
            continue;
        }

        $normalized[] = ['image' => $image_id, 'size' => $size];
    }

    return $normalized;
}

/** @return list<array{image: int, size: string}> */
function luux_acf_property_gallery_decode_images_json(mixed $json): array {
    if (! is_string($json) || $json === '') {
        return [];
    }

    $decoded = json_decode(wp_unslash($json), true);

    return is_array($decoded) ? luux_acf_property_gallery_normalize_images($decoded) : [];
}

function luux_acf_property_gallery_row_has_field(array $row, string $field_key, string $name): bool {
    return array_key_exists($field_key, $row) || array_key_exists($name, $row);
}

function luux_acf_property_gallery_row_value(array $row, string $field_key, string $name): mixed {
    if (array_key_exists($field_key, $row)) {
        return wp_unslash($row[$field_key]);
    }

    if (array_key_exists($name, $row)) {
        return wp_unslash($row[$name]);
    }

    return null;
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_property_gallery_post_rows_from_acf_array(array $fc): array {
    $rows       = [];
    $form_index = 0;

    foreach ($fc as $key => $row) {
        if (! is_array($row) || empty($row['acf_fc_layout'])) {
            continue;
        }

        if (luux_acf_property_gallery_layout_matches((string) $row['acf_fc_layout'])) {
            $index = $form_index;

            if (is_numeric($key)) {
                $index = (int) $key;
            } elseif (preg_match('/^row-(\d+)$/', (string) $key, $matches)) {
                $index = (int) $matches[1];
            }

            $rows[] = ['index' => $index, 'row' => $row];
        }

        $form_index++;
    }

    return $rows;
}

/**
 * @param array<string, mixed> $data
 * @return list<array{index: int, row: array<string, mixed>}>
 */
function luux_acf_property_gallery_find_rows_in_array(array $data): array {
    $rows = [];

    foreach ($data as $key => $value) {
        if (! is_array($value)) {
            continue;
        }

        if (luux_acf_property_gallery_layout_matches((string) ($value['acf_fc_layout'] ?? ''))) {
            $index  = is_numeric($key) ? (int) $key : count($rows);
            $rows[] = ['index' => $index, 'row' => $value];
            continue;
        }

        if (function_exists('luux_acf_array_is_sequential_list') && luux_acf_array_is_sequential_list($value)) {
            foreach ($value as $child_key => $child) {
                if (! is_array($child)) {
                    continue;
                }

                if (luux_acf_property_gallery_layout_matches((string) ($child['acf_fc_layout'] ?? ''))) {
                    $index  = is_numeric($child_key) ? (int) $child_key : count($rows);
                    $rows[] = ['index' => $index, 'row' => $child];
                }
            }
        }
    }

    return $rows;
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_property_gallery_early_post_rows(): array {
    if (empty($_POST['luux_property_gallery']) || ! is_array($_POST['luux_property_gallery'])) {
        return [];
    }

    $field_map   = luux_acf_property_gallery_field_map();
    $name_to_key = [];

    foreach ($field_map as $key => [$name]) {
        $name_to_key[$name] = $key;
    }

    $rows = [];

    foreach ($_POST['luux_property_gallery'] as $index => $fields) {
        if (! is_array($fields)) {
            continue;
        }

        $row = ['acf_fc_layout' => LUUX_PROPERTY_GALLERY_LAYOUT];

        foreach ($fields as $name => $value) {
            if (! is_string($name)) {
                continue;
            }

            $value = wp_unslash($value);

            if ($name === 'images_json') {
                $images = luux_acf_property_gallery_decode_images_json($value);

                if ($images !== []) {
                    $row['field_luux_property_gallery_images'] = $images;
                    $row['images']                             = $images;
                }

                continue;
            }

            if (! array_key_exists($name, $name_to_key)) {
                continue;
            }

            if ($value === '' || $value === null) {
                continue;
            }

            $row[$name_to_key[$name]] = $value;
            $row[$name]               = $value;
        }

        if (count($row) < 2) {
            continue;
        }

        $rows[] = ['index' => (int) $index, 'row' => $row];
    }

    return $rows;
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_property_gallery_post_rows_from_acf(): array {
    if (empty($_POST['acf']) || ! is_array($_POST['acf'])) {
        return [];
    }

    $fc = $_POST['acf']['field_luux_page_sections'] ?? null;

    if (! is_array($fc)) {
        return luux_acf_property_gallery_find_rows_in_array($_POST['acf']);
    }

    return luux_acf_property_gallery_post_rows_from_acf_array($fc);
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_property_gallery_post_rows_from_payload(array $payload): array {
    if (! empty($payload['field_luux_page_sections']) && is_array($payload['field_luux_page_sections'])) {
        return luux_acf_property_gallery_post_rows_from_acf_array($payload['field_luux_page_sections']);
    }

    return luux_acf_property_gallery_find_rows_in_array($payload);
}

/**
 * @param list<array{index: int, row: array<string, mixed>}> $primary
 * @param list<array{index: int, row: array<string, mixed>}> ...$sources
 * @return list<array{index: int, row: array<string, mixed>}>
 */
function luux_acf_property_gallery_enrich_rows(array $primary, array ...$sources): array {
    foreach ($primary as &$item) {
        if (! is_array($item['row'] ?? null)) {
            continue;
        }

        $has_images = ! empty($item['row']['field_luux_property_gallery_images']) || ! empty($item['row']['images']);

        if ($has_images) {
            continue;
        }

        foreach ($sources as $source) {
            foreach ($source as $candidate) {
                $cand   = $candidate['row'] ?? [];
                $images = is_array($cand) ? ($cand['field_luux_property_gallery_images'] ?? $cand['images'] ?? null) : null;

                if (is_array($images) && $images !== []) {
                    $images                                      = luux_acf_property_gallery_normalize_images($images);
                    $item['row']['field_luux_property_gallery_images'] = $images;
                    $item['row']['images']                             = $images;
                    break 2;
                }
            }
        }
    }
    unset($item);

    return $primary;
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_property_gallery_post_rows_with_indices(): array {
    $early = luux_acf_property_gallery_early_post_rows();
    $acf   = luux_acf_property_gallery_post_rows_from_acf();
    $rest  = [];

    $payload = luux_acf_property_gallery_rest_payload();

    if (is_array($payload) && $payload !== []) {
        $rest = luux_acf_property_gallery_post_rows_from_payload($payload);
    }

    if ($early !== []) {
        return luux_acf_property_gallery_enrich_rows($early, $acf, $rest);
    }

    if ($acf !== []) {
        return luux_acf_property_gallery_enrich_rows($acf, $rest);
    }

    return $rest;
}

/**
 * @param list<array{image: int, size: string}> $images
 */
function luux_acf_persist_property_gallery_images(int $post_id, int $db_index, array $images): void {
    $prefix = 'page_sections_' . (int) $db_index . '_';
    $images = luux_acf_property_gallery_normalize_images($images);
    $count  = 0;

    foreach ($images as $i => $item) {
        $image_id = (int) ($item['image'] ?? 0);
        $size     = (string) ($item['size'] ?? '');

        if ($image_id < 1 || $size === '') {
            continue;
        }

        $i = (int) $i;

        luux_acf_replace_section_meta(
            $post_id,
            $prefix . 'images_' . $i . '_image',
            $image_id,
            'field_luux_property_gallery_image'
        );
        luux_acf_replace_section_meta(
            $post_id,
            $prefix . 'images_' . $i . '_size',
            $size,
            'field_luux_property_gallery_size'
        );
        $count = $i + 1;
    }

    if ($count > 0) {
        luux_acf_replace_section_meta($post_id, $prefix . 'images', $count, 'field_luux_property_gallery_images');
    }
}

/**
 * @param array<string, mixed> $row
 */
function luux_acf_persist_property_gallery_row(int $post_id, int $db_index, array $row): void {
    $prefix    = 'page_sections_' . (int) $db_index . '_';
    $field_map = luux_acf_property_gallery_field_map();

    foreach ($field_map as $field_key => [$name, $ref, $type]) {
        if (! luux_acf_property_gallery_row_has_field($row, $field_key, $name)) {
            continue;
        }

        $value    = luux_acf_property_gallery_row_value($row, $field_key, $name);
        $meta_key = $prefix . $name;

        if ($value === '' || $value === null) {
            continue;
        }

        luux_acf_replace_section_meta($post_id, $meta_key, $value, $ref);
    }

    $images = $row['field_luux_property_gallery_images'] ?? $row['images'] ?? null;

    if (is_array($images) && $images !== []) {
        luux_acf_persist_property_gallery_images($post_id, $db_index, $images);
    }
}

/**
 * @param array<string, string> $fields
 */
function luux_acf_stash_property_gallery_row(int $post_id, int $row_index, array $fields): void {
    $stash = get_post_meta($post_id, LUUX_PROPERTY_GALLERY_STASH_META, true);

    if (! is_array($stash)) {
        $stash = [];
    }

    $stash[(string) $row_index] = $fields;
    update_post_meta($post_id, LUUX_PROPERTY_GALLERY_STASH_META, $stash);
}

function luux_acf_restore_property_gallery_from_stash(int $post_id): void {
    $stash = get_post_meta($post_id, LUUX_PROPERTY_GALLERY_STASH_META, true);

    if (! is_array($stash) || $stash === []) {
        return;
    }

    $field_map = luux_acf_property_gallery_field_map();
    $nth       = 0;

    foreach ($stash as $row_key => $fields) {
        if (! is_array($fields) || $fields === []) {
            continue;
        }

        $db_index = luux_acf_resolve_property_gallery_db_index($post_id, (int) $row_key, $nth);
        $row      = ['acf_fc_layout' => LUUX_PROPERTY_GALLERY_LAYOUT];

        foreach ($fields as $name => $value) {
            if (! is_string($name) || $value === '' || $value === null) {
                continue;
            }

            if ($name === '_images_json') {
                $images = luux_acf_property_gallery_decode_images_json($value);

                if ($images !== []) {
                    $row['field_luux_property_gallery_images'] = $images;
                    $row['images']                             = $images;
                }

                continue;
            }

            foreach ($field_map as $key => [$map_name]) {
                if ($map_name !== $name) {
                    continue;
                }

                $row[$key]  = $value;
                $row[$name] = $value;
            }
        }

        if (count($row) > 1) {
            luux_acf_persist_property_gallery_row($post_id, (int) $db_index, $row);
        }

        $nth++;
    }
}

function luux_acf_persist_property_gallery_from_request(int $post_id): void {
    $post_rows = luux_acf_property_gallery_post_rows_with_indices();

    if ($post_rows === []) {
        return;
    }

    $field_map = luux_acf_property_gallery_field_map();

    foreach ($post_rows as $n => $item) {
        $db_index = luux_acf_resolve_property_gallery_db_index($post_id, (int) ($item['index'] ?? 0), $n);
        $stash    = [];

        foreach ($field_map as $field_key => [$name]) {
            if (! luux_acf_property_gallery_row_has_field($item['row'], $field_key, $name)) {
                continue;
            }

            $value = luux_acf_property_gallery_row_value($item['row'], $field_key, $name);

            if ($value === '' || $value === null) {
                continue;
            }

            $stash[$name]            = is_scalar($value) ? (string) $value : '';
            $item['row'][$field_key] = $value;
            $item['row'][$name]      = $value;
        }

        $images = $item['row']['field_luux_property_gallery_images'] ?? $item['row']['images'] ?? null;

        if (is_array($images) && $images !== []) {
            $images                                      = luux_acf_property_gallery_normalize_images($images);
            $stash['_images_json']                       = wp_json_encode($images);
            $item['row']['images']                       = $images;
            $item['row']['field_luux_property_gallery_images'] = $images;
        }

        if ($stash !== []) {
            luux_acf_stash_property_gallery_row($post_id, (int) $db_index, $stash);
        }

        luux_acf_persist_property_gallery_row($post_id, (int) $db_index, $item['row']);
    }
}

function luux_acf_save_property_gallery_meta(int $post_id): void {
    luux_acf_persist_property_gallery_from_request($post_id);
    luux_acf_restore_property_gallery_from_stash($post_id);
    luux_acf_property_gallery_rest_payload(null, true);
}

function luux_acf_is_property_gallery_meta_name(int $post_id, string $name): bool {
    if (! preg_match('/^page_sections_(\d+)_(.+)$/', $name, $matches)) {
        return false;
    }

    $index  = (int) $matches[1];
    $suffix = $matches[2];

    if (! luux_acf_property_gallery_layout_matches(luux_acf_property_gallery_row_layout($post_id, $index))) {
        return false;
    }

    return in_array($suffix, ['heading', 'text', 'section_id'], true);
}

function luux_acf_ajax_save_property_gallery_fields(): void {
    if (! current_user_can('edit_pages')) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }

    check_ajax_referer('luux_property_gallery_save', 'nonce');

    $post_id   = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    $row_index = isset($_POST['row_index']) ? (int) $_POST['row_index'] : -1;
    $row_nth   = isset($_POST['row_nth']) ? (int) $_POST['row_nth'] : 0;
    $fields    = isset($_POST['fields']) && is_array($_POST['fields']) ? wp_unslash($_POST['fields']) : [];

    if (! empty($_POST['images_json']) && is_string($_POST['images_json'])) {
        $fields['images_json'] = wp_unslash($_POST['images_json']);
    }

    if ($post_id < 1 || get_post_type($post_id) !== 'page' || $row_index < 0 || $fields === []) {
        wp_send_json_error(['message' => 'Invalid request'], 400);
    }

    if (! current_user_can('edit_post', $post_id)) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }

    $db_index  = luux_acf_resolve_property_gallery_db_index($post_id, $row_index, $row_nth);
    $field_map = luux_acf_property_gallery_field_map();
    $row       = ['acf_fc_layout' => LUUX_PROPERTY_GALLERY_LAYOUT];
    $stash     = [];

    foreach ($fields as $name => $value) {
        if (! is_string($name)) {
            continue;
        }

        if ($name === 'images_json') {
            $images = luux_acf_property_gallery_decode_images_json($value);

            if ($images !== []) {
                $row['field_luux_property_gallery_images'] = $images;
                $row['images']                             = $images;
                $stash['_images_json']                     = is_string($value) ? wp_unslash($value) : wp_json_encode($images);
            }

            continue;
        }

        if ($value === '' || $value === null) {
            continue;
        }

        $stash[$name] = is_scalar($value) ? (string) $value : '';

        foreach ($field_map as $key => [$map_name]) {
            if ($map_name !== $name) {
                continue;
            }

            $row[$key]  = $value;
            $row[$name] = $value;
        }
    }

    if ($stash !== []) {
        luux_acf_stash_property_gallery_row($post_id, $db_index, $stash);
    }

    luux_acf_persist_property_gallery_row($post_id, $db_index, $row);

    wp_send_json_success(['row_index' => $db_index]);
}

/**
 * @return list<array{image: int, size: string}>
 */
function luux_property_gallery_images_from_meta(int $post_id, int $row_index): array {
    $count_raw = luux_read_section_meta($post_id, $row_index, 'images');

    if (is_array($count_raw)) {
        $rows = [];

        foreach (luux_acf_property_gallery_normalize_images($count_raw) as $item) {
            $rows[] = ['image' => (int) $item['image'], 'size' => (string) $item['size']];
        }

        return $rows;
    }

    $count = is_numeric($count_raw) ? (int) $count_raw : 0;

    if ($count < 1) {
        for ($i = 0; $i < 30; $i++) {
            $image = luux_read_section_meta($post_id, $row_index, 'images_' . $i . '_image');

            if ($image !== null && (int) $image > 0) {
                $count = $i + 1;
                continue;
            }

            break;
        }
    }

    if ($count < 1) {
        return [];
    }

    $rows = [];

    for ($i = 0; $i < $count; $i++) {
        $image = luux_read_section_meta($post_id, $row_index, 'images_' . $i . '_image');
        $size  = luux_read_section_meta($post_id, $row_index, 'images_' . $i . '_size');

        if ($image !== null && is_numeric($image) && (int) $image > 0 && $size !== null && $size !== '') {
            $rows[] = ['image' => (int) $image, 'size' => (string) $size];
        }
    }

    return $rows;
}

add_filter('acf/pre_update_metadata', function ($check, $post_id, $name, $value, $hidden) {
    if ($hidden || ! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page') {
        return $check;
    }

    if (! is_string($name) || ! luux_acf_is_property_gallery_meta_name((int) $post_id, $name)) {
        return $check;
    }

    return true;
}, 10, 5);

add_filter('acf/load_value', function ($value, $post_id, $field) {
    if (! is_admin() || ! is_array($field)) {
        return $value;
    }

    $name = $field['name'] ?? '';
    $key  = $field['key'] ?? '';

    if (! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page' || ! function_exists('acf_get_loop')) {
        return $value;
    }

    $loop = acf_get_loop('active');

    if (! is_array($loop) || ($loop['selector'] ?? '') !== 'page_sections') {
        return $value;
    }

    $row_index = (int) ($loop['i'] ?? -1);

    if ($row_index < 0) {
        return $value;
    }

    $full_meta = luux_acf_get_page_section_meta((int) $post_id);

    if (! luux_acf_property_gallery_layout_matches(luux_page_section_layout_slug($full_meta, $row_index))) {
        return $value;
    }

    if ($name === 'images' && $key === 'field_luux_property_gallery_images') {
        $from_meta = luux_property_gallery_images_from_meta((int) $post_id, $row_index);

        return $from_meta !== [] ? $from_meta : $value;
    }

    if (! in_array($name, ['heading', 'text', 'section_id'], true)) {
        return $value;
    }

    if (
        function_exists('luux_page_sections_uses_legacy_storage')
        && luux_page_sections_uses_legacy_storage((int) $post_id)
    ) {
        $direct = luux_read_section_meta((int) $post_id, $row_index, $name);

        if ($direct !== null && $direct !== '') {
            return $direct;
        }
    }

    if ($value !== null && $value !== false && $value !== '') {
        return $value;
    }

    $stash = get_post_meta((int) $post_id, LUUX_PROPERTY_GALLERY_STASH_META, true);

    if (is_array($stash) && isset($stash[(string) $row_index][$name]) && $stash[(string) $row_index][$name] !== '') {
        return $stash[(string) $row_index][$name];
    }

    $direct = luux_read_section_meta((int) $post_id, $row_index, $name);

    if ($direct === null || $direct === '') {
        return $value;
    }

    return $direct;
}, 26, 3);

add_filter('rest_pre_insert_page', function ($prepared_post, WP_REST_Request $request) {
    luux_acf_property_gallery_capture_rest_request($request);

    return $prepared_post;
}, 5, 2);

add_filter('rest_pre_update_page', function ($prepared_post, WP_REST_Request $request) {
    luux_acf_property_gallery_capture_rest_request($request);

    return $prepared_post;
}, 5, 2);

add_action('acf/save_post', function ($post_id): void {
    if (! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page') {
        return;
    }

    luux_acf_save_property_gallery_meta((int) $post_id);
}, 99999);

add_action('save_post_page', function (int $post_id): void {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    luux_acf_save_property_gallery_meta($post_id);
}, 99999);

add_action('rest_after_insert_page', function (\WP_Post $post, WP_REST_Request $request): void {
    if ($post->post_type !== 'page') {
        return;
    }

    luux_acf_property_gallery_capture_rest_request($request);
    luux_acf_save_property_gallery_meta((int) $post->ID);
}, 99999, 2);

add_action('wp_ajax_luux_save_property_gallery_fields', 'luux_acf_ajax_save_property_gallery_fields');

add_action('admin_enqueue_scripts', function (string $hook): void {
    if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    if (! $screen || $screen->post_type !== 'page') {
        return;
    }

    $path = get_template_directory() . '/assets/js/admin-layout-property-gallery.js';

    if (! is_readable($path)) {
        return;
    }

    wp_enqueue_script(
        'luux-admin-layout-property-gallery',
        get_template_directory_uri() . '/assets/js/admin-layout-property-gallery.js',
        ['jquery', 'acf-input', 'wp-api-fetch', 'wp-data'],
        (string) filemtime($path),
        true
    );

    wp_localize_script('luux-admin-layout-property-gallery', 'luuxLayoutPropertyGallery', [
        'nonce'   => wp_create_nonce('luux_property_gallery_save'),
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
});
