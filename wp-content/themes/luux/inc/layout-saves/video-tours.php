<?php
/**
 * Video Tours layout — direct postmeta save/read for legacy imported pages.
 * Scalars + media are blocked + custom-saved; preserves left/right media sync.
 */

defined('ABSPATH') || exit;

const LUUX_VIDEO_TOURS_STASH_META = '_luux_video_tours_stash';
const LUUX_VIDEO_TOURS_LAYOUT     = 'video_tours';

/** @return array<string, array{0: string, 1: string, 2: string}> */
function luux_acf_video_tours_field_map(): array {
    return [
        'field_luux_video_tours_heading'           => ['heading', 'field_luux_video_tours_heading', 'text'],
        'field_luux_video_tours_text'              => ['text', 'field_luux_video_tours_text', 'wysiwyg'],
        'field_luux_video_tours_media_type_left'   => ['media_type_left', 'field_luux_video_tours_media_type_left', 'text'],
        'field_luux_video_tours_media_type_right'  => ['media_type_right', 'field_luux_video_tours_media_type_right', 'text'],
        'field_luux_video_tours_image_left'        => ['image_left', 'field_luux_video_tours_image_left', 'image'],
        'field_luux_video_tours_image_right'       => ['image_right', 'field_luux_video_tours_image_right', 'image'],
        'field_luux_video_tours_video_left'        => ['video_left', 'field_luux_video_tours_video_left', 'file'],
        'field_luux_video_tours_video_right'       => ['video_right', 'field_luux_video_tours_video_right', 'file'],
        'field_luux_video_tours_section_id'        => ['section_id', 'field_luux_video_tours_section_id', 'text'],
    ];
}

function luux_acf_video_tours_layout_matches(string $layout): bool {
    return in_array($layout, [LUUX_VIDEO_TOURS_LAYOUT, 'layout_luux_video_tours'], true);
}

/** @return list<int> */
function luux_acf_video_tours_db_row_indices(int $post_id): array {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta === []) {
        return [];
    }

    $indices = [];
    $count   = luux_acf_page_sections_row_count($meta);

    for ($i = 0; $i < $count; $i++) {
        if (luux_acf_video_tours_layout_matches(luux_page_section_layout_slug($meta, $i))) {
            $indices[] = $i;
        }
    }

    return $indices;
}

function luux_acf_video_tours_row_layout(int $post_id, int $index): string {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta !== []) {
        return luux_page_section_layout_slug($meta, $index);
    }

    return luux_page_section_layout_slug(
        ['page_sections' => get_post_meta($post_id, 'page_sections', true)],
        $index
    );
}

function luux_acf_resolve_video_tours_db_index(int $post_id, int $row_index, int $row_nth = 0): int {
    $db_indices = luux_acf_video_tours_db_row_indices($post_id);

    if (isset($db_indices[$row_nth])) {
        return $db_indices[$row_nth];
    }

    if (luux_acf_video_tours_layout_matches(luux_acf_video_tours_row_layout($post_id, $row_index))) {
        return $row_index;
    }

    return $row_index;
}

/** @return array<string, mixed>|null */
function luux_acf_video_tours_rest_payload(?array $set = null, bool $reset = false): ?array {
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

function luux_acf_video_tours_capture_rest_request(WP_REST_Request $request): void {
    $params = $request->get_json_params();

    if (! is_array($params)) {
        return;
    }

    if (! empty($params['acf']) && is_array($params['acf'])) {
        luux_acf_video_tours_rest_payload($params['acf']);

        return;
    }

    if (! empty($params['meta']['acf']) && is_array($params['meta']['acf'])) {
        luux_acf_video_tours_rest_payload($params['meta']['acf']);
    }
}

function luux_acf_video_tours_attachment_id(mixed $value): int {
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

function luux_acf_video_tours_row_has_field(array $row, string $field_key, string $name): bool {
    if (array_key_exists($field_key, $row) || array_key_exists($name, $row)) {
        return true;
    }

    foreach (array_keys($row) as $key) {
        if (! is_string($key)) {
            continue;
        }

        if ($key === $field_key || $key === $name || str_ends_with($key, '_' . $name)) {
            return true;
        }
    }

    return false;
}

function luux_acf_video_tours_row_value(array $row, string $field_key, string $name): mixed {
    if (array_key_exists($field_key, $row)) {
        return wp_unslash($row[$field_key]);
    }

    if (array_key_exists($name, $row)) {
        return wp_unslash($row[$name]);
    }

    foreach ($row as $key => $value) {
        if (! is_string($key)) {
            continue;
        }

        if ($key === $field_key || $key === $name || str_ends_with($key, '_' . $name)) {
            return wp_unslash($value);
        }
    }

    return null;
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_video_tours_post_rows_from_acf_array(array $fc): array {
    $rows       = [];
    $form_index = 0;

    foreach ($fc as $key => $row) {
        if (! is_array($row) || empty($row['acf_fc_layout'])) {
            continue;
        }

        if (luux_acf_video_tours_layout_matches((string) $row['acf_fc_layout'])) {
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
function luux_acf_video_tours_find_rows_in_array(array $data): array {
    $rows = [];

    foreach ($data as $key => $value) {
        if (! is_array($value)) {
            continue;
        }

        if (luux_acf_video_tours_layout_matches((string) ($value['acf_fc_layout'] ?? ''))) {
            $index  = is_numeric($key) ? (int) $key : count($rows);
            $rows[] = ['index' => $index, 'row' => $value];
            continue;
        }

        if (function_exists('luux_acf_array_is_sequential_list') && luux_acf_array_is_sequential_list($value)) {
            foreach ($value as $child_key => $child) {
                if (! is_array($child)) {
                    continue;
                }

                if (luux_acf_video_tours_layout_matches((string) ($child['acf_fc_layout'] ?? ''))) {
                    $index  = is_numeric($child_key) ? (int) $child_key : count($rows);
                    $rows[] = ['index' => $index, 'row' => $child];
                }
            }
        }
    }

    return $rows;
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_video_tours_early_post_rows(): array {
    if (empty($_POST['luux_video_tours']) || ! is_array($_POST['luux_video_tours'])) {
        return [];
    }

    $field_map   = luux_acf_video_tours_field_map();
    $name_to_key = [];

    foreach ($field_map as $key => [$name]) {
        $name_to_key[$name] = $key;
    }

    $rows = [];

    foreach ($_POST['luux_video_tours'] as $index => $fields) {
        if (! is_array($fields)) {
            continue;
        }

        $row = ['acf_fc_layout' => LUUX_VIDEO_TOURS_LAYOUT];

        foreach ($fields as $name => $value) {
            if (! is_string($name) || ! array_key_exists($name, $name_to_key)) {
                continue;
            }

            $value = wp_unslash($value);
            $type  = $field_map[$name_to_key[$name]][2] ?? 'text';

            if (in_array($type, ['image', 'file'], true)) {
                $id = luux_acf_video_tours_attachment_id($value);

                if ($id > 0) {
                    $row[$name_to_key[$name]] = $id;
                    $row[$name]               = $id;
                }

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
function luux_acf_video_tours_post_rows_from_acf(): array {
    if (empty($_POST['acf']) || ! is_array($_POST['acf'])) {
        return [];
    }

    $fc = $_POST['acf']['field_luux_page_sections'] ?? null;

    if (! is_array($fc)) {
        return luux_acf_video_tours_find_rows_in_array($_POST['acf']);
    }

    return luux_acf_video_tours_post_rows_from_acf_array($fc);
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_video_tours_post_rows_from_payload(array $payload): array {
    if (! empty($payload['field_luux_page_sections']) && is_array($payload['field_luux_page_sections'])) {
        return luux_acf_video_tours_post_rows_from_acf_array($payload['field_luux_page_sections']);
    }

    return luux_acf_video_tours_find_rows_in_array($payload);
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_video_tours_post_rows_with_indices(): array {
    $early = luux_acf_video_tours_early_post_rows();
    $acf   = luux_acf_video_tours_post_rows_from_acf();
    $rest  = [];

    $payload = luux_acf_video_tours_rest_payload();

    if (is_array($payload) && $payload !== []) {
        $rest = luux_acf_video_tours_post_rows_from_payload($payload);
    }

    if ($early !== []) {
        return $early;
    }

    if ($acf !== []) {
        return $acf;
    }

    return $rest;
}

/**
 * Ensure ACF reference keys exist for all video_tours fields (no full-page migration).
 */
function luux_acf_relink_video_tours_meta_for_post(int $post_id): void {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta === []) {
        return;
    }

    $count     = luux_acf_page_sections_row_count($meta);
    $field_map = luux_acf_video_tours_field_map();

    for ($i = 0; $i < $count; $i++) {
        if (! luux_acf_video_tours_layout_matches(luux_page_section_layout_slug($meta, $i))) {
            continue;
        }

        $prefix = "page_sections_{$i}_";

        foreach ($field_map as [, [$name, $ref]]) {
            $meta_key = $prefix . $name;

            if (metadata_exists('post', $post_id, $meta_key)) {
                update_post_meta($post_id, '_' . $meta_key, $ref);
            }
        }
    }
}

/**
 * @param array<string, mixed> $row
 */
function luux_acf_persist_video_tours_row(int $post_id, int $db_index, array $row): void {
    $prefix    = 'page_sections_' . (int) $db_index . '_';
    $field_map = luux_acf_video_tours_field_map();

    foreach ($field_map as $field_key => [$name, $ref, $type]) {
        if (! luux_acf_video_tours_row_has_field($row, $field_key, $name)) {
            continue;
        }

        $value    = luux_acf_video_tours_row_value($row, $field_key, $name);
        $meta_key = $prefix . $name;

        if (in_array($type, ['image', 'file'], true)) {
            $id = luux_acf_video_tours_attachment_id($value);

            if ($id > 0) {
                luux_acf_replace_section_meta($post_id, $meta_key, $id, $ref);
            } else {
                while (delete_post_meta($post_id, $meta_key)) {
                }
                while (delete_post_meta($post_id, '_' . $meta_key)) {
                }
            }

            continue;
        }

        if ($value === '' || $value === null) {
            continue;
        }

        luux_acf_replace_section_meta($post_id, $meta_key, $value, $ref);
    }

    foreach (['left', 'right'] as $side) {
        $video_key  = $prefix . 'video_' . $side;
        $type_key   = $prefix . 'media_type_' . $side;
        $video_id   = luux_acf_video_tours_attachment_id(get_post_meta($post_id, $video_key, true));
        $media_type = get_post_meta($post_id, $type_key, true);

        if ($video_id && $media_type !== 'video') {
            luux_acf_replace_section_meta(
                $post_id,
                $type_key,
                'video',
                'field_luux_video_tours_media_type_' . $side
            );
            luux_acf_replace_section_meta(
                $post_id,
                $video_key,
                $video_id,
                'field_luux_video_tours_video_' . $side
            );
        }
    }
}

/**
 * @param array<string, string|int> $fields
 */
function luux_acf_stash_video_tours_row(int $post_id, int $row_index, array $fields): void {
    $stash = get_post_meta($post_id, LUUX_VIDEO_TOURS_STASH_META, true);

    if (! is_array($stash)) {
        $stash = [];
    }

    $stash[(string) $row_index] = $fields;
    update_post_meta($post_id, LUUX_VIDEO_TOURS_STASH_META, $stash);
}

function luux_acf_restore_video_tours_from_stash(int $post_id): void {
    $stash = get_post_meta($post_id, LUUX_VIDEO_TOURS_STASH_META, true);

    if (! is_array($stash) || $stash === []) {
        return;
    }

    $db_indices = luux_acf_video_tours_db_row_indices($post_id);
    $field_map  = luux_acf_video_tours_field_map();
    $nth        = 0;

    foreach ($stash as $row_key => $fields) {
        if (! is_array($fields) || $fields === []) {
            continue;
        }

        $row_index = (int) $row_key;
        $db_index  = $db_indices[$nth] ?? $row_index;

        $row = ['acf_fc_layout' => LUUX_VIDEO_TOURS_LAYOUT];

        foreach ($fields as $name => $value) {
            if (! is_string($name) || $value === '' || $value === null) {
                continue;
            }

            foreach ($field_map as $key => [$map_name, , $type]) {
                if ($map_name !== $name) {
                    continue;
                }

                if (in_array($type, ['image', 'file'], true)) {
                    $id = luux_acf_video_tours_attachment_id($value);

                    if ($id > 0) {
                        $row[$key]  = $id;
                        $row[$name] = $id;
                    }

                    continue;
                }

                $row[$key]  = $value;
                $row[$name] = $value;
            }
        }

        if (count($row) > 1) {
            luux_acf_persist_video_tours_row($post_id, (int) $db_index, $row);
        }

        $nth++;
    }

    luux_acf_sync_video_tours_media_meta($post_id);
    luux_acf_relink_video_tours_meta_for_post($post_id);
}

function luux_acf_persist_video_tours_from_request(int $post_id): void {
    $post_rows = luux_acf_video_tours_post_rows_with_indices();

    if ($post_rows === []) {
        return;
    }

    $field_map = luux_acf_video_tours_field_map();

    foreach ($post_rows as $n => $item) {
        $db_index = luux_acf_resolve_video_tours_db_index($post_id, (int) ($item['index'] ?? 0), $n);
        $stash    = [];

        foreach ($field_map as $field_key => [$name, , $type]) {
            if (! luux_acf_video_tours_row_has_field($item['row'], $field_key, $name)) {
                continue;
            }

            $value = luux_acf_video_tours_row_value($item['row'], $field_key, $name);

            if ($value === '' || $value === null || $value === 0) {
                continue;
            }

            if (in_array($type, ['image', 'file'], true)) {
                $id = luux_acf_video_tours_attachment_id($value);

                if ($id > 0) {
                    $stash[$name]            = (string) $id;
                    $item['row'][$field_key] = $id;
                    $item['row'][$name]      = $id;
                }

                continue;
            }

            $stash[$name] = is_scalar($value) ? (string) $value : '';
        }

        if ($stash !== []) {
            luux_acf_stash_video_tours_row($post_id, (int) $db_index, $stash);
        }

        luux_acf_persist_video_tours_row($post_id, (int) $db_index, $item['row']);
    }
}

function luux_acf_save_video_tours_meta(int $post_id): void {
    luux_acf_persist_video_tours_from_request($post_id);
    luux_acf_restore_video_tours_from_stash($post_id);
    luux_acf_video_tours_rest_payload(null, true);
}

function luux_acf_sync_video_tours_media_meta(int $post_id): void {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta === []) {
        return;
    }

    $count = luux_acf_page_sections_row_count($meta);

    for ($i = 0; $i < $count; $i++) {
        if (! luux_acf_video_tours_layout_matches(luux_page_section_layout_slug($meta, $i))) {
            continue;
        }

        $prefix = "page_sections_{$i}_";

        foreach (['left', 'right'] as $side) {
            $type_key   = $prefix . 'media_type_' . $side;
            $video_key  = $prefix . 'video_' . $side;
            $media_type = get_post_meta($post_id, $type_key, true) ?: 'image';
            $video_id   = luux_acf_video_tours_attachment_id(get_post_meta($post_id, $video_key, true));

            if ($video_id && $media_type !== 'video') {
                $media_type = 'video';
                luux_acf_replace_section_meta(
                    $post_id,
                    $type_key,
                    'video',
                    'field_luux_video_tours_media_type_' . $side
                );
            }

            update_post_meta($post_id, '_' . $type_key, 'field_luux_video_tours_media_type_' . $side);

            if ($media_type === 'video') {
                delete_post_meta($post_id, $prefix . 'image_' . $side);
                delete_post_meta($post_id, '_' . $prefix . 'image_' . $side);

                if (get_post_meta($post_id, $video_key, true)) {
                    update_post_meta($post_id, '_' . $video_key, 'field_luux_video_tours_video_' . $side);
                }

                continue;
            }

            delete_post_meta($post_id, $video_key);
            delete_post_meta($post_id, '_' . $video_key);

            $image_key = $prefix . 'image_' . $side;

            if (get_post_meta($post_id, $image_key, true)) {
                update_post_meta($post_id, '_' . $image_key, 'field_luux_video_tours_image_' . $side);
            }
        }
    }
}

function luux_acf_is_video_tours_meta_name(int $post_id, string $name): bool {
    if (! preg_match('/^page_sections_(\d+)_(.+)$/', $name, $matches)) {
        return false;
    }

    $index  = (int) $matches[1];
    $suffix = $matches[2];

    if (! luux_acf_video_tours_layout_matches(luux_acf_video_tours_row_layout($post_id, $index))) {
        return false;
    }

    return in_array($suffix, [
        'heading',
        'text',
        'media_type_left',
        'media_type_right',
        'image_left',
        'image_right',
        'video_left',
        'video_right',
        'section_id',
    ], true);
}

function luux_acf_ajax_save_video_tours_fields(): void {
    if (! current_user_can('edit_pages')) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }

    check_ajax_referer('luux_video_tours_save', 'nonce');

    $post_id   = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    $row_index = isset($_POST['row_index']) ? (int) $_POST['row_index'] : -1;
    $row_nth   = isset($_POST['row_nth']) ? (int) $_POST['row_nth'] : 0;
    $fields    = isset($_POST['fields']) && is_array($_POST['fields']) ? wp_unslash($_POST['fields']) : [];

    foreach (['heading', 'text', 'media_type_left', 'media_type_right', 'image_left', 'image_right', 'video_left', 'video_right', 'section_id'] as $key) {
        if (isset($_POST[$key]) && is_string($_POST[$key]) && $_POST[$key] !== '') {
            $fields[$key] = wp_unslash($_POST[$key]);
        }
    }

    if ($post_id < 1 || get_post_type($post_id) !== 'page' || $row_index < 0 || $fields === []) {
        wp_send_json_error(['message' => 'Invalid request'], 400);
    }

    if (! current_user_can('edit_post', $post_id)) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }

    $db_index  = luux_acf_resolve_video_tours_db_index($post_id, $row_index, $row_nth);
    $field_map = luux_acf_video_tours_field_map();
    $row       = ['acf_fc_layout' => LUUX_VIDEO_TOURS_LAYOUT];
    $stash     = [];

    foreach ($fields as $name => $value) {
        if (! is_string($name) || $value === '' || $value === null) {
            continue;
        }

        $stash[$name] = is_scalar($value) ? (string) $value : '';

        foreach ($field_map as $key => [$map_name, , $type]) {
            if ($map_name !== $name) {
                continue;
            }

            if (in_array($type, ['image', 'file'], true)) {
                $id = luux_acf_video_tours_attachment_id($value);

                if ($id > 0) {
                    $row[$key]    = $id;
                    $row[$name]   = $id;
                    $stash[$name] = (string) $id;
                }

                continue;
            }

            $row[$key]  = $value;
            $row[$name] = $value;
        }
    }

    if ($stash !== []) {
        luux_acf_stash_video_tours_row($post_id, $db_index, $stash);
    }

    luux_acf_persist_video_tours_row($post_id, $db_index, $row);
    luux_acf_sync_video_tours_media_meta($post_id);
    luux_acf_relink_video_tours_meta_for_post($post_id);

    wp_send_json_success(['row_index' => $db_index]);
}

/**
 * Read a video_tours sub-field from the current flexible row, with direct postmeta fallback.
 */
function luux_video_tours_sub_field(string $name) {
    $value = get_sub_field($name);

    if ($value !== null && $value !== false && $value !== '') {
        return $value;
    }

    $post_id = get_the_ID();

    if (! $post_id || ! function_exists('get_row_index')) {
        return $value;
    }

    $row_index = (int) get_row_index() - 1;

    if ($row_index < 0) {
        return $value;
    }

    $direct = luux_read_section_meta((int) $post_id, $row_index, $name);

    if ($direct === null || $direct === '') {
        return $value;
    }

    return $direct;
}

add_filter('acf/pre_update_metadata', function ($check, $post_id, $name, $value, $hidden) {
    if ($hidden || ! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page') {
        return $check;
    }

    if (! is_string($name) || ! luux_acf_is_video_tours_meta_name((int) $post_id, $name)) {
        return $check;
    }

    return true;
}, 10, 5);

add_filter('acf/load_value', function ($value, $post_id, $field) {
    if (! is_admin() || ! is_array($field)) {
        return $value;
    }

    $name = $field['name'] ?? '';

    if (! in_array($name, [
        'heading',
        'text',
        'media_type_left',
        'media_type_right',
        'video_left',
        'video_right',
        'image_left',
        'image_right',
        'section_id',
    ], true)) {
        return $value;
    }

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

    if (! luux_acf_video_tours_layout_matches(luux_page_section_layout_slug($full_meta, $row_index))) {
        return $value;
    }

    if (
        function_exists('luux_page_sections_uses_legacy_storage')
        && luux_page_sections_uses_legacy_storage((int) $post_id)
    ) {
        $direct = luux_read_section_meta((int) $post_id, $row_index, $name);

        if ($direct !== null && $direct !== '') {
            if (in_array($name, ['video_left', 'video_right', 'image_left', 'image_right'], true)) {
                return (int) $direct;
            }

            return $direct;
        }
    }

    if ($value !== null && $value !== false && $value !== '') {
        return $value;
    }

    $stash = get_post_meta((int) $post_id, LUUX_VIDEO_TOURS_STASH_META, true);

    if (is_array($stash)) {
        $db_indices = luux_acf_video_tours_db_row_indices((int) $post_id);
        $stash_key  = (string) $row_index;

        foreach ($db_indices as $db_index) {
            if ((int) $db_index === $row_index && isset($stash[(string) $db_index][$name])) {
                $stash_key = (string) $db_index;
                break;
            }
        }

        if (isset($stash[$stash_key][$name]) && $stash[$stash_key][$name] !== '') {
            $stashed = $stash[$stash_key][$name];

            if (in_array($name, ['video_left', 'video_right', 'image_left', 'image_right'], true)) {
                return (int) $stashed;
            }

            return $stashed;
        }
    }

    $direct = luux_read_section_meta((int) $post_id, $row_index, $name);

    if ($direct === null || $direct === '') {
        return $value;
    }

    if (in_array($name, ['video_left', 'video_right', 'image_left', 'image_right'], true)) {
        return (int) $direct;
    }

    return $direct;
}, 26, 3);

add_filter('rest_pre_insert_page', function ($prepared_post, WP_REST_Request $request) {
    luux_acf_video_tours_capture_rest_request($request);

    return $prepared_post;
}, 5, 2);

add_filter('rest_pre_update_page', function ($prepared_post, WP_REST_Request $request) {
    luux_acf_video_tours_capture_rest_request($request);

    return $prepared_post;
}, 5, 2);

add_action('acf/save_post', function ($post_id): void {
    if (! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page') {
        return;
    }

    luux_acf_save_video_tours_meta((int) $post_id);
}, 99999);

add_action('save_post_page', function (int $post_id): void {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    luux_acf_save_video_tours_meta($post_id);
}, 99999);

add_action('rest_after_insert_page', function (\WP_Post $post, WP_REST_Request $request): void {
    if ($post->post_type !== 'page') {
        return;
    }

    luux_acf_video_tours_capture_rest_request($request);
    luux_acf_save_video_tours_meta((int) $post->ID);
}, 99999, 2);

add_action('wp_ajax_luux_save_video_tours_fields', 'luux_acf_ajax_save_video_tours_fields');
add_action('wp_ajax_luux_save_video_tours_media', 'luux_acf_ajax_save_video_tours_fields');

add_action('admin_enqueue_scripts', function (string $hook): void {
    if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    if (! $screen || $screen->post_type !== 'page') {
        return;
    }

    $path = get_template_directory() . '/assets/js/admin-layout-video-tours.js';

    if (! is_readable($path)) {
        return;
    }

    wp_enqueue_script(
        'luux-admin-layout-video-tours',
        get_template_directory_uri() . '/assets/js/admin-layout-video-tours.js',
        ['jquery', 'acf-input', 'wp-api-fetch', 'wp-data'],
        (string) filemtime($path),
        true
    );

    wp_localize_script('luux-admin-layout-video-tours', 'luuxLayoutVideoTours', [
        'nonce'   => wp_create_nonce('luux_video_tours_save'),
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
});
