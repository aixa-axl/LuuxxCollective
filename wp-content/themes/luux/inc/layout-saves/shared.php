<?php
/**
 * Shared legacy layout save engine — direct postmeta read/write for imported pages.
 */

defined('ABSPATH') || exit;

/** @var array<string, array<string, mixed>> */
function &luux_layout_save_registry(): array {
    static $registry = [];

    return $registry;
}

function luux_layout_save_register(array $config): void {
    $slug = $config['slug'] ?? '';

    if ($slug === '') {
        return;
    }

    $registry       = &luux_layout_save_registry();
    $registry[$slug] = $config;
}

function luux_layout_save_get(string $slug): ?array {
    $registry = luux_layout_save_registry();

    return $registry[$slug] ?? null;
}

/** @return array<string, array{0: string, 1: string, 2: string}> */
function luux_layout_save_field_map(array $config): array {
    $map = [];

    foreach ($config['fields'] ?? [] as $field) {
        $map[$field['key']] = [$field['name'], $field['key'], $field['type'] ?? 'scalar'];
    }

    return $map;
}

function luux_layout_save_row_layout(int $post_id, int $index, string $slug): string {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta !== []) {
        return luux_page_section_layout_slug($meta, $index);
    }

    return luux_page_section_layout_slug(
        ['page_sections' => get_post_meta($post_id, 'page_sections', true)],
        $index
    );
}

/** @return list<int> */
function luux_layout_save_db_row_indices(int $post_id, string $slug): array {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta === []) {
        return [];
    }

    $indices = [];
    $count   = luux_acf_page_sections_row_count($meta);

    for ($i = 0; $i < $count; $i++) {
        if (luux_page_section_layout_slug($meta, $i) === $slug) {
            $indices[] = $i;
        }
    }

    return $indices;
}

function luux_layout_save_resolve_db_index(int $post_id, string $slug, int $row_index, int $row_nth = 0): int {
    $resolved = luux_find_section_row_index($post_id, $slug);

    if ($resolved !== null) {
        return $resolved;
    }

    $db_indices = luux_layout_save_db_row_indices($post_id, $slug);

    if (isset($db_indices[$row_nth])) {
        return $db_indices[$row_nth];
    }

    if (luux_layout_save_row_layout($post_id, $row_index, $slug) === $slug) {
        return $row_index;
    }

    return $row_index;
}

function luux_layout_save_attachment_id(mixed $value): int {
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

function luux_layout_save_normalize_field_value(mixed $value, string $type): mixed {
    if ($type === 'link' && is_string($value)) {
        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return $decoded;
        }
    }

    if ($type === 'true_false') {
        return (bool) $value && $value !== '0' && $value !== 0;
    }

    if ($type === 'number' && is_numeric($value)) {
        return (int) $value;
    }

    return $value;
}

/** @return array<string, string> */
function luux_layout_save_field_types(array $config): array {
    $types = [];

    foreach ($config['fields'] ?? [] as $field) {
        $types[$field['name']] = $field['type'] ?? 'scalar';
    }

    return $types;
}

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

function luux_layout_save_row_value(array $row, string $field_key, string $name): mixed {
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

/** @return array<string, mixed>|null */
function luux_layout_save_rest_payload(?array $set = null, bool $reset = false): ?array {
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

function luux_layout_save_capture_rest_request(WP_REST_Request $request): void {
    $params = $request->get_json_params();

    if (! is_array($params)) {
        return;
    }

    if (! empty($params['acf']) && is_array($params['acf'])) {
        luux_layout_save_rest_payload($params['acf']);

        return;
    }

    if (! empty($params['meta']['acf']) && is_array($params['meta']['acf'])) {
        luux_layout_save_rest_payload($params['meta']['acf']);
    }
}

/**
 * @param array<string, mixed> $data
 * @return list<array{index: int, row: array<string, mixed>}>
 */
function luux_layout_save_find_rows(array $data, array $config): array {
    $slug   = $config['slug'];
    $keys   = $config['layout_keys'] ?? [$slug];
    $rows   = [];

    foreach ($data as $key => $value) {
        if (! is_array($value)) {
            continue;
        }

        $layout = (string) ($value['acf_fc_layout'] ?? '');

        if (in_array($layout, $keys, true)) {
            $index  = is_numeric($key) ? (int) $key : count($rows);
            $rows[] = ['index' => $index, 'row' => $value];
            continue;
        }

        if (luux_acf_array_is_sequential_list($value)) {
            foreach ($value as $child_key => $child) {
                if (! is_array($child)) {
                    continue;
                }

                $child_layout = (string) ($child['acf_fc_layout'] ?? '');

                if (in_array($child_layout, $keys, true)) {
                    $index  = is_numeric($child_key) ? (int) $child_key : count($rows);
                    $rows[] = ['index' => $index, 'row' => $child];
                }
            }
        }
    }

    return $rows;
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_layout_save_post_rows_from_acf_array(array $fc, array $config): array {
    $keys       = $config['layout_keys'] ?? [$config['slug']];
    $rows       = [];
    $form_index = 0;

    foreach ($fc as $key => $row) {
        if (! is_array($row) || empty($row['acf_fc_layout'])) {
            continue;
        }

        $layout = (string) $row['acf_fc_layout'];

        if (in_array($layout, $keys, true)) {
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

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_layout_save_early_post_rows(array $config): array {
    $slug = $config['slug'];

    if (empty($_POST['luux_layout_fields'][$slug]) || ! is_array($_POST['luux_layout_fields'][$slug])) {
        return [];
    }

    $field_map   = luux_layout_save_field_map($config);
    $name_to_key = [];

    foreach ($field_map as $key => [$name]) {
        $name_to_key[$name] = $key;
    }

    $rows = [];

    foreach ($_POST['luux_layout_fields'][$slug] as $index => $fields) {
        if (! is_array($fields)) {
            continue;
        }

        $row = ['acf_fc_layout' => $slug];

        foreach ($fields as $name => $value) {
            if (! is_string($name) || ! array_key_exists($name, $name_to_key)) {
                continue;
            }

            $value      = wp_unslash($value);
            $field_key  = $name_to_key[$name];
            $field_type = $field_map[$field_key][2] ?? 'scalar';
            $value      = luux_layout_save_normalize_field_value($value, $field_type);

            if ($value === '' || $value === null) {
                continue;
            }

            $row[$field_key] = $value;
            $row[$name]      = $value;
        }

        if (count($row) < 2) {
            continue;
        }

        $rows[] = ['index' => (int) $index, 'row' => $row];
    }

    return $rows;
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_layout_save_post_rows_from_acf(array $config): array {
    if (empty($_POST['acf']) || ! is_array($_POST['acf'])) {
        return [];
    }

    $fc = $_POST['acf']['field_luux_page_sections'] ?? null;

    if (! is_array($fc)) {
        return luux_layout_save_find_rows($_POST['acf'], $config);
    }

    return luux_layout_save_post_rows_from_acf_array($fc, $config);
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_layout_save_post_rows_with_indices(array $config): array {
    $early = luux_layout_save_early_post_rows($config);

    if ($early !== []) {
        return $early;
    }

    $acf = luux_layout_save_post_rows_from_acf($config);

    if ($acf !== []) {
        return $acf;
    }

    $payload = luux_layout_save_rest_payload();

    if (! is_array($payload) || $payload === []) {
        return [];
    }

    if (! empty($payload['field_luux_page_sections']) && is_array($payload['field_luux_page_sections'])) {
        return luux_layout_save_post_rows_from_acf_array($payload['field_luux_page_sections'], $config);
    }

    return luux_layout_save_find_rows($payload, $config);
}

/**
 * @param array<int, array<string, mixed>> $items
 */
function luux_layout_save_persist_repeater(
    int $post_id,
    int $db_index,
    string $prefix,
    array $repeater,
    array $items
): void {
    $name       = $repeater['name'];
    $repeater_key = $repeater['key'];
    $count      = 0;

    foreach ($items as $i => $item) {
        if (! is_array($item)) {
            continue;
        }

        $has_value = false;

        foreach ($repeater['sub_fields'] as $sub) {
            $sub_name = $sub['name'];
            $sub_key  = $sub['key'];
            $sub_type = $sub['type'] ?? 'scalar';
            $value    = luux_layout_save_row_value($item, $sub_key, $sub_name);

            if ($value === '' || $value === null) {
                continue;
            }

            if ($sub_type === 'image') {
                $value = luux_layout_save_attachment_id($value);

                if ($value === 0) {
                    continue;
                }
            }

            if ($sub_type === 'link' && is_array($value) && empty($value['url'])) {
                continue;
            }

            $meta_key = $prefix . $name . '_' . (int) $i . '_' . $sub_name;
            luux_acf_replace_section_meta($post_id, $meta_key, $value, $sub_key);
            $has_value = true;
        }

        if ($has_value) {
            $count = (int) $i + 1;
        }
    }

    if ($count > 0) {
        luux_acf_replace_section_meta($post_id, $prefix . $name, $count, $repeater_key);
    }
}

/**
 * @param array<string, mixed> $row
 */
function luux_layout_save_persist_row(int $post_id, int $db_index, array $config, array $row): void {
    $slug      = $config['slug'];
    $prefix    = 'page_sections_' . (int) $db_index . '_';
    $field_map = luux_layout_save_field_map($config);

    foreach ($field_map as $field_key => [$name, $ref, $type]) {
        if (! luux_layout_save_row_has_field($row, $field_key, $name)) {
            continue;
        }

        $value = luux_layout_save_normalize_field_value(
            luux_layout_save_row_value($row, $field_key, $name),
            $type
        );
        $meta_key = $prefix . $name;

        if ($type === 'true_false') {
            luux_acf_replace_section_meta($post_id, $meta_key, $value ? 1 : 0, $ref);
            continue;
        }

        if ($type === 'number') {
            if ($value === '' || $value === null) {
                continue;
            }

            luux_acf_replace_section_meta($post_id, $meta_key, (int) $value, $ref);
            continue;
        }

        if ($type === 'image') {
            $value = luux_layout_save_attachment_id($value);
        }

        if ($type === 'link' && is_array($value) && empty($value['url'])) {
            continue;
        }

        if ($value === '' || $value === null || $value === 0) {
            if ($type === 'image') {
                while (delete_post_meta($post_id, $meta_key)) {
                }
                while (delete_post_meta($post_id, '_' . $meta_key)) {
                }
            }

            continue;
        }

        luux_acf_replace_section_meta($post_id, $meta_key, $value, $ref);
    }

    foreach ($config['repeaters'] ?? [] as $repeater) {
        $repeater_name = $repeater['name'];
        $items         = $row[$repeater['key']] ?? $row[$repeater_name] ?? null;

        if (! is_array($items)) {
            continue;
        }

        luux_layout_save_persist_repeater($post_id, $db_index, $prefix, $repeater, $items);
    }

    if ($slug === 'hero') {
        $media_type = get_post_meta($post_id, $prefix . 'media_type', true) ?: 'image';

        if ($media_type === 'video') {
            while (delete_post_meta($post_id, $prefix . 'background_image')) {
            }
            while (delete_post_meta($post_id, '_' . $prefix . 'background_image')) {
            }
        } else {
            while (delete_post_meta($post_id, $prefix . 'background_video')) {
            }
            while (delete_post_meta($post_id, '_' . $prefix . 'background_video')) {
            }
        }
    }
}

/**
 * @param array<string, string|int> $fields
 */
function luux_layout_save_stash_row(int $post_id, array $config, int $row_index, array $fields): void {
    $stash_key = $config['stash_meta'];
    $stash     = get_post_meta($post_id, $stash_key, true);

    if (! is_array($stash)) {
        $stash = [];
    }

    $stash[(string) $row_index] = $fields;
    update_post_meta($post_id, $stash_key, $stash);
}

function luux_layout_save_restore_from_stash(int $post_id, array $config): void {
    $stash_key = $config['stash_meta'];
    $stash     = get_post_meta($post_id, $stash_key, true);

    if (! is_array($stash) || $stash === []) {
        return;
    }

    $slug      = $config['slug'];
    $field_map = luux_layout_save_field_map($config);
    $nth       = 0;

    foreach ($stash as $row_key => $fields) {
        if (! is_array($fields) || $fields === []) {
            continue;
        }

        $db_index = luux_layout_save_resolve_db_index($post_id, $slug, (int) $row_key, $nth);
        $row      = ['acf_fc_layout' => $slug];

        foreach ($fields as $name => $value) {
            if (! is_string($name) || $value === '' || $value === null) {
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
            luux_layout_save_persist_row($post_id, $db_index, $config, $row);
        }

        $nth++;
    }
}

function luux_layout_save_persist_from_request(int $post_id, array $config): void {
    $post_rows = luux_layout_save_post_rows_with_indices($config);

    if ($post_rows === []) {
        return;
    }

    $slug      = $config['slug'];
    $field_map = luux_layout_save_field_map($config);

    foreach ($post_rows as $n => $item) {
        $db_index = luux_layout_save_resolve_db_index($post_id, $slug, (int) ($item['index'] ?? 0), $n);
        $stash    = [];

        foreach ($field_map as $field_key => [$name]) {
            if (! luux_layout_save_row_has_field($item['row'], $field_key, $name)) {
                continue;
            }

            $value = luux_layout_save_row_value($item['row'], $field_key, $name);

            if ($value === '' || $value === null || $value === 0) {
                continue;
            }

            $stash[$name] = is_scalar($value) ? (string) $value : '';
        }

        if ($stash !== []) {
            luux_layout_save_stash_row($post_id, $config, $db_index, $stash);
        }

        luux_layout_save_persist_row($post_id, $db_index, $config, $item['row']);
    }
}

function luux_layout_save_all_meta(int $post_id): void {
    foreach (luux_layout_save_registry() as $config) {
        luux_layout_save_persist_from_request($post_id, $config);
        luux_layout_save_restore_from_stash($post_id, $config);
    }

    luux_layout_save_rest_payload(null, true);
}

function luux_layout_save_is_meta_name(int $post_id, string $name, array $config): bool {
    if (! preg_match('/^page_sections_(\d+)_(.+)$/', $name, $matches)) {
        return false;
    }

    $index  = (int) $matches[1];
    $suffix = $matches[2];
    $slug   = $config['slug'];

    if (luux_layout_save_row_layout($post_id, $index, $slug) !== $slug) {
        return false;
    }

    foreach ($config['fields'] ?? [] as $field) {
        if ($field['name'] === $suffix) {
            return true;
        }
    }

    foreach ($config['repeaters'] ?? [] as $repeater) {
        if ($suffix === $repeater['name']) {
            return true;
        }

        $pattern = '/^' . preg_quote($repeater['name'], '/') . '_\d+_/';

        if (preg_match($pattern, $suffix)) {
            return true;
        }
    }

    return false;
}

function luux_layout_save_field_names(array $config): array {
    $names = [];

    foreach ($config['fields'] ?? [] as $field) {
        $names[] = $field['name'];
    }

    return $names;
}

function luux_layout_save_ajax_handler(): void {
    if (! current_user_can('edit_pages')) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }

    check_ajax_referer('luux_layout_fields_save', 'nonce');

    $slug      = isset($_POST['layout']) ? sanitize_key(wp_unslash($_POST['layout'])) : '';
    $config    = luux_layout_save_get($slug);
    $post_id   = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    $row_index = isset($_POST['row_index']) ? (int) $_POST['row_index'] : -1;
    $row_nth   = isset($_POST['row_nth']) ? (int) $_POST['row_nth'] : 0;
    $fields    = isset($_POST['fields']) && is_array($_POST['fields']) ? wp_unslash($_POST['fields']) : [];

    if (! $config || $post_id < 1 || get_post_type($post_id) !== 'page' || $row_index < 0 || $fields === []) {
        wp_send_json_error(['message' => 'Invalid request'], 400);
    }

    if (! current_user_can('edit_post', $post_id)) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }

    $db_index  = luux_layout_save_resolve_db_index($post_id, $slug, $row_index, $row_nth);
    $field_map = luux_layout_save_field_map($config);
    $row       = ['acf_fc_layout' => $slug];
    $stash     = [];

    $field_types = luux_layout_save_field_types($config);

    foreach ($fields as $name => $value) {
        if (! is_string($name) || $value === '' || $value === null) {
            continue;
        }

        $field_type = $field_types[$name] ?? 'scalar';
        $value      = luux_layout_save_normalize_field_value($value, $field_type);
        $stash[$name] = is_scalar($value) ? (string) $value : wp_json_encode($value);

        foreach ($field_map as $key => [$map_name]) {
            if ($map_name !== $name) {
                continue;
            }

            $row[$key]  = $value;
            $row[$name] = $value;
        }
    }

    luux_layout_save_stash_row($post_id, $config, $db_index, $stash);
    luux_layout_save_persist_row($post_id, $db_index, $config, $row);

    wp_send_json_success(['row_index' => $db_index]);
}

/**
 * Read a repeater from postmeta on legacy pages.
 *
 * @param array<string, array{key: string, type?: string}> $sub_fields
 * @return list<array<string, mixed>>
 */
function luux_layout_read_repeater_from_meta(int $post_id, int $row_index, string $repeater_name, array $sub_fields): array {
    $count = luux_read_section_meta($post_id, $row_index, $repeater_name);

    if ($count === null || ! is_numeric($count) || (int) $count < 1) {
        return [];
    }

    $rows = [];

    for ($i = 0; $i < (int) $count; $i++) {
        $row = [];

        foreach ($sub_fields as $sub) {
            $name  = $sub['name'];
            $type  = $sub['type'] ?? 'scalar';
            $value = luux_read_section_meta($post_id, $row_index, $repeater_name . '_' . $i . '_' . $name);

            if ($value === null) {
                continue;
            }

            if ($type === 'link' && is_string($value)) {
                $value = maybe_unserialize($value);
            }

            if ($type === 'image') {
                $value = (int) $value;
            }

            if ($type === 'link' && (! is_array($value) || empty($value['url']))) {
                continue;
            }

            $row[$name] = $value;
        }

        if ($row !== []) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function luux_sub_field_link(string $name): ?array {
    $post_id = get_the_ID();

    if (
        $post_id
        && function_exists('luux_page_sections_uses_legacy_storage')
        && luux_page_sections_uses_legacy_storage((int) $post_id)
    ) {
        $row_index = luux_section_row_index();

        if ($row_index >= 0) {
            $value = luux_read_section_meta((int) $post_id, $row_index, $name);

            if ($value !== null) {
                if (is_string($value)) {
                    $value = maybe_unserialize($value);
                }

                return is_array($value) ? $value : null;
            }
        }
    }

    $value = get_sub_field($name);

    return is_array($value) ? $value : null;
}

/**
 * @param array<string, array{key: string, type?: string}> $sub_fields
 * @return list<array<string, mixed>>
 */
function luux_sub_field_repeater(string $name, array $sub_fields): array {
    $post_id = get_the_ID();

    if (
        $post_id
        && function_exists('luux_page_sections_uses_legacy_storage')
        && luux_page_sections_uses_legacy_storage((int) $post_id)
    ) {
        $row_index = luux_section_row_index();

        if ($row_index >= 0) {
            $from_meta = luux_layout_read_repeater_from_meta((int) $post_id, $row_index, $name, $sub_fields);

            if ($from_meta !== []) {
                return $from_meta;
            }
        }
    }

    $value = get_sub_field($name);

    return is_array($value) ? $value : [];
}

add_filter('acf/pre_update_metadata', function ($check, $post_id, $name, $value, $hidden) {
    if ($hidden || ! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page') {
        return $check;
    }

    if (! is_string($name)) {
        return $check;
    }

    foreach (luux_layout_save_registry() as $config) {
        if (luux_layout_save_is_meta_name((int) $post_id, $name, $config)) {
            return true;
        }
    }

    if (function_exists('luux_acf_is_video_tours_meta_name') && luux_acf_is_video_tours_meta_name((int) $post_id, $name)) {
        return true;
    }

    return $check;
}, 10, 5);

add_filter('acf/load_value', function ($value, $post_id, $field) {
    if (! is_admin() || ! is_array($field)) {
        return $value;
    }

    $name = $field['name'] ?? '';

    if ($name === '' || ! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page' || ! function_exists('acf_get_loop')) {
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

    $full_meta     = luux_acf_get_page_section_meta((int) $post_id);
    $layout_slug   = luux_page_section_layout_slug($full_meta, $row_index);
    $matched_config = null;

    foreach (luux_layout_save_registry() as $config) {
        if (($config['slug'] ?? '') === $layout_slug && in_array($name, luux_layout_save_field_names($config), true)) {
            $matched_config = $config;
            break;
        }
    }

    if ($matched_config === null) {
        return $value;
    }

    if (
        function_exists('luux_page_sections_uses_legacy_storage')
        && luux_page_sections_uses_legacy_storage((int) $post_id)
    ) {
        $direct = luux_read_section_meta((int) $post_id, $row_index, $name);

        if ($direct !== null) {
            return $direct;
        }
    }

    $stash_key = $matched_config['stash_meta'];
    $stash     = get_post_meta((int) $post_id, $stash_key, true);

    if (is_array($stash)) {
        $db_indices = luux_layout_save_db_row_indices((int) $post_id, $layout_slug);
        $stash_key_id = (string) $row_index;

        foreach ($db_indices as $db_index) {
            if ((int) $db_index === $row_index && isset($stash[(string) $db_index][$name])) {
                $stash_key_id = (string) $db_index;
                break;
            }
        }

        if (isset($stash[$stash_key_id][$name]) && $stash[$stash_key_id][$name] !== '') {
            return $stash[$stash_key_id][$name];
        }
    }

    if ($value !== null && $value !== false && $value !== '') {
        return $value;
    }

    $direct = luux_read_section_meta((int) $post_id, $row_index, $name);

    if ($direct === null || $direct === '') {
        return $value;
    }

    return $direct;
}, 21, 3);

add_filter('rest_pre_insert_page', function ($prepared_post, WP_REST_Request $request) {
    luux_layout_save_capture_rest_request($request);

    return $prepared_post;
}, 5, 2);

add_filter('rest_pre_update_page', function ($prepared_post, WP_REST_Request $request) {
    luux_layout_save_capture_rest_request($request);

    return $prepared_post;
}, 5, 2);

add_action('acf/save_post', function ($post_id): void {
    if (! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page') {
        return;
    }

    luux_layout_save_all_meta((int) $post_id);
}, 99999);

add_action('save_post_page', function (int $post_id): void {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    luux_layout_save_all_meta($post_id);
}, 99999);

add_action('rest_after_insert_page', function (\WP_Post $post, WP_REST_Request $request): void {
    if ($post->post_type !== 'page') {
        return;
    }

    luux_layout_save_capture_rest_request($request);
    luux_layout_save_all_meta((int) $post->ID);
}, 99999, 2);

add_action('wp_ajax_luux_save_layout_fields', 'luux_layout_save_ajax_handler');

add_action('admin_enqueue_scripts', function (string $hook): void {
    if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    if (! $screen || $screen->post_type !== 'page') {
        return;
    }

    $path = get_template_directory() . '/assets/js/admin-layout-saves.js';

    if (! is_readable($path)) {
        return;
    }

    $registry = luux_layout_save_registry();
    $js_map   = [];

    foreach ($registry as $slug => $config) {
        $fields      = [];
        $field_types = [];

        foreach ($config['fields'] ?? [] as $field) {
            $fields[$field['name']]      = $field['key'];
            $field_types[$field['name']] = $field['type'] ?? 'scalar';
        }

        $js_map[$slug] = [
            'slug'       => $slug,
            'layoutKeys' => $config['layout_keys'] ?? [$slug],
            'fieldKeys'  => $fields,
            'fieldTypes' => $field_types,
        ];
    }

    wp_enqueue_script(
        'luux-admin-layout-saves',
        get_template_directory_uri() . '/assets/js/admin-layout-saves.js',
        ['jquery', 'acf-input', 'wp-api-fetch', 'wp-data'],
        (string) filemtime($path),
        true
    );

    wp_localize_script('luux-admin-layout-saves', 'luuxLayoutSaves', [
        'nonce'   => wp_create_nonce('luux_layout_fields_save'),
        'ajaxurl' => admin_url('admin-ajax.php'),
        'layouts' => $js_map,
    ]);
});
