<?php
/**
 * USPs layout — direct postmeta save/read for legacy imported pages.
 * Scalars are blocked + custom-saved; items repeater stays unblocked.
 */

defined('ABSPATH') || exit;

const LUUX_USPS_STASH_META = '_luux_usps_stash';
const LUUX_USPS_LAYOUT     = 'usps';

/** @return array<string, array{0: string, 1: string, 2: string}> */
function luux_acf_usps_field_map(): array {
    return [
        'field_luux_usps_section_label'    => ['section_label', 'field_luux_usps_section_label', 'text'],
        'field_luux_usps_heading'          => ['heading', 'field_luux_usps_heading', 'text'],
        'field_luux_usps_light_background' => ['light_background', 'field_luux_usps_light_background', 'true_false'],
        'field_luux_usps_section_id'       => ['section_id', 'field_luux_usps_section_id', 'text'],
    ];
}

function luux_acf_usps_layout_matches(string $layout): bool {
    return in_array($layout, [LUUX_USPS_LAYOUT, 'layout_luux_usps'], true);
}

/** @return list<int> */
function luux_acf_usps_db_row_indices(int $post_id): array {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta === []) {
        return [];
    }

    $indices = [];
    $count   = luux_acf_page_sections_row_count($meta);

    for ($i = 0; $i < $count; $i++) {
        if (luux_acf_usps_layout_matches(luux_page_section_layout_slug($meta, $i))) {
            $indices[] = $i;
        }
    }

    return $indices;
}

function luux_acf_usps_row_layout(int $post_id, int $index): string {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta !== []) {
        return luux_page_section_layout_slug($meta, $index);
    }

    return luux_page_section_layout_slug(
        ['page_sections' => get_post_meta($post_id, 'page_sections', true)],
        $index
    );
}

function luux_acf_resolve_usps_db_index(int $post_id, int $row_index, int $row_nth = 0): int {
    $resolved = luux_find_section_row_index($post_id, LUUX_USPS_LAYOUT);

    if ($resolved !== null) {
        return $resolved;
    }

    $db_indices = luux_acf_usps_db_row_indices($post_id);

    if (isset($db_indices[$row_nth])) {
        return $db_indices[$row_nth];
    }

    if (luux_acf_usps_layout_matches(luux_acf_usps_row_layout($post_id, $row_index))) {
        return $row_index;
    }

    return $row_index;
}

/** @return array<string, mixed>|null */
function luux_acf_usps_rest_payload(?array $set = null, bool $reset = false): ?array {
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

function luux_acf_usps_capture_rest_request(WP_REST_Request $request): void {
    $params = $request->get_json_params();

    if (! is_array($params)) {
        return;
    }

    if (! empty($params['acf']) && is_array($params['acf'])) {
        luux_acf_usps_rest_payload($params['acf']);

        return;
    }

    if (! empty($params['meta']['acf']) && is_array($params['meta']['acf'])) {
        luux_acf_usps_rest_payload($params['meta']['acf']);
    }
}

function luux_acf_usps_attachment_id(mixed $value): int {
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

/**
 * @param array<string|int, mixed> $items
 * @return list<array<string, mixed>>
 */
function luux_acf_usps_normalize_items(array $items): array {
    $normalized = [];

    foreach ($items as $item) {
        if (is_array($item)) {
            $normalized[] = $item;
        }
    }

    return $normalized;
}

/** @return list<array<string, mixed>> */
function luux_acf_usps_decode_items_json(mixed $json): array {
    if (! is_string($json) || $json === '') {
        return [];
    }

    $decoded = json_decode(wp_unslash($json), true);

    return is_array($decoded) ? luux_acf_usps_normalize_items($decoded) : [];
}

function luux_acf_usps_row_has_field(array $row, string $field_key, string $name): bool {
    return array_key_exists($field_key, $row) || array_key_exists($name, $row);
}

function luux_acf_usps_row_value(array $row, string $field_key, string $name): mixed {
    if (array_key_exists($field_key, $row)) {
        return wp_unslash($row[$field_key]);
    }

    if (array_key_exists($name, $row)) {
        return wp_unslash($row[$name]);
    }

    return null;
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_usps_post_rows_from_acf_array(array $fc): array {
    $rows       = [];
    $form_index = 0;

    foreach ($fc as $key => $row) {
        if (! is_array($row) || empty($row['acf_fc_layout'])) {
            continue;
        }

        if (luux_acf_usps_layout_matches((string) $row['acf_fc_layout'])) {
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
function luux_acf_usps_find_rows_in_array(array $data): array {
    $rows = [];

    foreach ($data as $key => $value) {
        if (! is_array($value)) {
            continue;
        }

        if (luux_acf_usps_layout_matches((string) ($value['acf_fc_layout'] ?? ''))) {
            $index  = is_numeric($key) ? (int) $key : count($rows);
            $rows[] = ['index' => $index, 'row' => $value];
            continue;
        }

        if (function_exists('luux_acf_array_is_sequential_list') && luux_acf_array_is_sequential_list($value)) {
            foreach ($value as $child_key => $child) {
                if (! is_array($child)) {
                    continue;
                }

                if (luux_acf_usps_layout_matches((string) ($child['acf_fc_layout'] ?? ''))) {
                    $index  = is_numeric($child_key) ? (int) $child_key : count($rows);
                    $rows[] = ['index' => $index, 'row' => $child];
                }
            }
        }
    }

    return $rows;
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_usps_early_post_rows(): array {
    if (empty($_POST['luux_usps']) || ! is_array($_POST['luux_usps'])) {
        return [];
    }

    $field_map   = luux_acf_usps_field_map();
    $name_to_key = [];

    foreach ($field_map as $key => [$name]) {
        $name_to_key[$name] = $key;
    }

    $rows = [];

    foreach ($_POST['luux_usps'] as $index => $fields) {
        if (! is_array($fields)) {
            continue;
        }

        $row = ['acf_fc_layout' => LUUX_USPS_LAYOUT];

        foreach ($fields as $name => $value) {
            if (! is_string($name)) {
                continue;
            }

            $value = wp_unslash($value);

            if ($name === 'items_json') {
                $items = luux_acf_usps_decode_items_json($value);

                if ($items !== []) {
                    $row['field_luux_usps_items'] = $items;
                    $row['items']                 = $items;
                }

                continue;
            }

            if (! array_key_exists($name, $name_to_key)) {
                continue;
            }

            $type = $field_map[$name_to_key[$name]][2] ?? 'text';

            if ($type === 'true_false') {
                $row[$name_to_key[$name]] = ($value === '1' || $value === 1 || $value === true || $value === 'true') ? 1 : 0;
                $row[$name]               = $row[$name_to_key[$name]];
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
function luux_acf_usps_post_rows_from_acf(): array {
    if (empty($_POST['acf']) || ! is_array($_POST['acf'])) {
        return [];
    }

    $fc = $_POST['acf']['field_luux_page_sections'] ?? null;

    if (! is_array($fc)) {
        return luux_acf_usps_find_rows_in_array($_POST['acf']);
    }

    return luux_acf_usps_post_rows_from_acf_array($fc);
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_usps_post_rows_from_payload(array $payload): array {
    if (! empty($payload['field_luux_page_sections']) && is_array($payload['field_luux_page_sections'])) {
        return luux_acf_usps_post_rows_from_acf_array($payload['field_luux_page_sections']);
    }

    return luux_acf_usps_find_rows_in_array($payload);
}

/**
 * @param list<array{index: int, row: array<string, mixed>}> $primary
 * @param list<array{index: int, row: array<string, mixed>}> ...$sources
 * @return list<array{index: int, row: array<string, mixed>}>
 */
function luux_acf_usps_enrich_rows(array $primary, array ...$sources): array {
    foreach ($primary as &$item) {
        if (! is_array($item['row'] ?? null)) {
            continue;
        }

        $has_items = ! empty($item['row']['field_luux_usps_items']) || ! empty($item['row']['items']);

        if ($has_items) {
            continue;
        }

        foreach ($sources as $source) {
            foreach ($source as $candidate) {
                $cand  = $candidate['row'] ?? [];
                $items = is_array($cand) ? ($cand['field_luux_usps_items'] ?? $cand['items'] ?? null) : null;

                if (is_array($items) && $items !== []) {
                    $item['row']['field_luux_usps_items'] = $items;
                    $item['row']['items']                 = $items;
                    break 2;
                }
            }
        }
    }
    unset($item);

    return $primary;
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_usps_post_rows_with_indices(): array {
    $early = luux_acf_usps_early_post_rows();
    $acf   = luux_acf_usps_post_rows_from_acf();
    $rest  = [];

    $payload = luux_acf_usps_rest_payload();

    if (is_array($payload) && $payload !== []) {
        $rest = luux_acf_usps_post_rows_from_payload($payload);
    }

    if ($early !== []) {
        return luux_acf_usps_enrich_rows($early, $acf, $rest);
    }

    if ($acf !== []) {
        return luux_acf_usps_enrich_rows($acf, $rest);
    }

    return $rest;
}

/**
 * @param array<int, array<string, mixed>> $items
 */
function luux_acf_persist_usps_items(int $post_id, int $db_index, array $items): void {
    $prefix = 'page_sections_' . (int) $db_index . '_';
    $items  = luux_acf_usps_normalize_items($items);
    $count  = 0;

    foreach ($items as $i => $item) {
        if (! is_array($item)) {
            continue;
        }

        $i         = (int) $i;
        $has_value = false;

        $icon = $item['field_luux_usps_icon'] ?? $item['icon'] ?? null;

        if ($icon !== null && $icon !== '') {
            $icon_id = luux_acf_usps_attachment_id($icon);

            if ($icon_id > 0) {
                luux_acf_replace_section_meta(
                    $post_id,
                    $prefix . 'items_' . $i . '_icon',
                    $icon_id,
                    'field_luux_usps_icon'
                );
                $has_value = true;
            }
        }

        $title = $item['field_luux_usps_title'] ?? $item['title'] ?? null;

        if ($title !== null && $title !== '') {
            luux_acf_replace_section_meta(
                $post_id,
                $prefix . 'items_' . $i . '_title',
                (string) $title,
                'field_luux_usps_title'
            );
            $has_value = true;
        }

        $description = $item['field_luux_usps_description'] ?? $item['description'] ?? null;

        if ($description !== null && $description !== '') {
            luux_acf_replace_section_meta(
                $post_id,
                $prefix . 'items_' . $i . '_description',
                (string) $description,
                'field_luux_usps_description'
            );
            $has_value = true;
        }

        if ($has_value) {
            $count = $i + 1;
        }
    }

    if ($count > 0) {
        luux_acf_replace_section_meta($post_id, $prefix . 'items', $count, 'field_luux_usps_items');
    }
}

/**
 * @param array<string, mixed> $row
 */
function luux_acf_persist_usps_row(int $post_id, int $db_index, array $row): void {
    $prefix    = 'page_sections_' . (int) $db_index . '_';
    $field_map = luux_acf_usps_field_map();

    foreach ($field_map as $field_key => [$name, $ref, $type]) {
        if (! luux_acf_usps_row_has_field($row, $field_key, $name)) {
            continue;
        }

        $value    = luux_acf_usps_row_value($row, $field_key, $name);
        $meta_key = $prefix . $name;

        if ($type === 'true_false') {
            luux_acf_replace_section_meta($post_id, $meta_key, $value ? 1 : 0, $ref);
            continue;
        }

        if ($value === '' || $value === null) {
            continue;
        }

        luux_acf_replace_section_meta($post_id, $meta_key, $value, $ref);
    }

    $items = $row['field_luux_usps_items'] ?? $row['items'] ?? null;

    if (is_array($items) && $items !== []) {
        luux_acf_persist_usps_items($post_id, $db_index, $items);
    }
}

/**
 * @param array<string, string> $fields
 */
function luux_acf_stash_usps_row(int $post_id, int $row_index, array $fields): void {
    $stash = get_post_meta($post_id, LUUX_USPS_STASH_META, true);

    if (! is_array($stash)) {
        $stash = [];
    }

    $stash[(string) $row_index] = $fields;
    update_post_meta($post_id, LUUX_USPS_STASH_META, $stash);
}

function luux_acf_restore_usps_from_stash(int $post_id): void {
    $stash = get_post_meta($post_id, LUUX_USPS_STASH_META, true);

    if (! is_array($stash) || $stash === []) {
        return;
    }

    $field_map = luux_acf_usps_field_map();
    $nth       = 0;

    foreach ($stash as $row_key => $fields) {
        if (! is_array($fields) || $fields === []) {
            continue;
        }

        $db_index = luux_acf_resolve_usps_db_index($post_id, (int) $row_key, $nth);
        $row      = ['acf_fc_layout' => LUUX_USPS_LAYOUT];

        foreach ($fields as $name => $value) {
            if (! is_string($name) || $value === '' || $value === null) {
                continue;
            }

            if ($name === '_items_json') {
                $items = luux_acf_usps_decode_items_json($value);

                if ($items !== []) {
                    $row['field_luux_usps_items'] = $items;
                    $row['items']                 = $items;
                }

                continue;
            }

            foreach ($field_map as $key => [$map_name, $ref, $type]) {
                if ($map_name !== $name) {
                    continue;
                }

                if ($type === 'true_false') {
                    $row[$key]  = ($value === '1' || $value === 1) ? 1 : 0;
                    $row[$name] = $row[$key];
                    continue;
                }

                $row[$key]  = $value;
                $row[$name] = $value;
            }
        }

        if (count($row) > 1) {
            luux_acf_persist_usps_row($post_id, (int) $db_index, $row);
        }

        $nth++;
    }
}

function luux_acf_persist_usps_from_request(int $post_id): void {
    $post_rows = luux_acf_usps_post_rows_with_indices();

    if ($post_rows === []) {
        return;
    }

    $field_map = luux_acf_usps_field_map();

    foreach ($post_rows as $n => $item) {
        $db_index = luux_acf_resolve_usps_db_index($post_id, (int) ($item['index'] ?? 0), $n);
        $stash    = [];

        foreach ($field_map as $field_key => [$name, $ref, $type]) {
            if (! luux_acf_usps_row_has_field($item['row'], $field_key, $name)) {
                continue;
            }

            $value = luux_acf_usps_row_value($item['row'], $field_key, $name);

            if ($type === 'true_false') {
                $stash[$name] = $value ? '1' : '0';
                continue;
            }

            if ($value === '' || $value === null) {
                continue;
            }

            $stash[$name] = is_scalar($value) ? (string) $value : '';
        }

        $items = $item['row']['field_luux_usps_items'] ?? $item['row']['items'] ?? null;

        if (is_array($items) && $items !== []) {
            $items                            = luux_acf_usps_normalize_items($items);
            $stash['_items_json']             = wp_json_encode($items);
            $item['row']['items']             = $items;
            $item['row']['field_luux_usps_items'] = $items;
        }

        if ($stash !== []) {
            luux_acf_stash_usps_row($post_id, (int) $db_index, $stash);
        }

        luux_acf_persist_usps_row($post_id, (int) $db_index, $item['row']);
    }
}

function luux_acf_save_usps_meta(int $post_id): void {
    luux_acf_persist_usps_from_request($post_id);
    luux_acf_restore_usps_from_stash($post_id);
    luux_acf_usps_rest_payload(null, true);
}

function luux_acf_is_usps_meta_name(int $post_id, string $name): bool {
    if (! preg_match('/^page_sections_(\d+)_(.+)$/', $name, $matches)) {
        return false;
    }

    $index  = (int) $matches[1];
    $suffix = $matches[2];

    if (! luux_acf_usps_layout_matches(luux_acf_usps_row_layout($post_id, $index))) {
        return false;
    }

    return in_array($suffix, ['section_label', 'heading', 'light_background', 'section_id'], true);
}

function luux_acf_ajax_save_usps_fields(): void {
    if (! current_user_can('edit_pages')) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }

    check_ajax_referer('luux_usps_save', 'nonce');

    $post_id   = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    $row_index = isset($_POST['row_index']) ? (int) $_POST['row_index'] : -1;
    $row_nth   = isset($_POST['row_nth']) ? (int) $_POST['row_nth'] : 0;
    $fields    = isset($_POST['fields']) && is_array($_POST['fields']) ? wp_unslash($_POST['fields']) : [];

    if (! empty($_POST['items_json']) && is_string($_POST['items_json'])) {
        $fields['items_json'] = wp_unslash($_POST['items_json']);
    }

    if ($post_id < 1 || get_post_type($post_id) !== 'page' || $row_index < 0 || $fields === []) {
        wp_send_json_error(['message' => 'Invalid request'], 400);
    }

    if (! current_user_can('edit_post', $post_id)) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }

    $db_index  = luux_acf_resolve_usps_db_index($post_id, $row_index, $row_nth);
    $field_map = luux_acf_usps_field_map();
    $row       = ['acf_fc_layout' => LUUX_USPS_LAYOUT];
    $stash     = [];

    foreach ($fields as $name => $value) {
        if (! is_string($name)) {
            continue;
        }

        if ($name === 'items_json') {
            $items = luux_acf_usps_decode_items_json($value);

            if ($items !== []) {
                $row['field_luux_usps_items'] = $items;
                $row['items']                 = $items;
                $stash['_items_json']         = is_string($value) ? wp_unslash($value) : wp_json_encode($items);
            }

            continue;
        }

        if ($value === '' || $value === null) {
            // Allow explicit 0 for true_false.
            if ($name !== 'light_background') {
                continue;
            }
        }

        $stash[$name] = is_scalar($value) ? (string) $value : '';

        foreach ($field_map as $key => [$map_name, $ref, $type]) {
            if ($map_name !== $name) {
                continue;
            }

            if ($type === 'true_false') {
                $row[$key]  = ($value === '1' || $value === 1 || $value === true) ? 1 : 0;
                $row[$name] = $row[$key];
                continue;
            }

            $row[$key]  = $value;
            $row[$name] = $value;
        }
    }

    if ($stash !== []) {
        luux_acf_stash_usps_row($post_id, $db_index, $stash);
    }

    luux_acf_persist_usps_row($post_id, $db_index, $row);

    wp_send_json_success(['row_index' => $db_index]);
}

/**
 * @return list<array<string, mixed>>
 */
function luux_usps_items_from_meta(int $post_id, int $row_index): array {
    $count_raw = luux_read_section_meta($post_id, $row_index, 'items');

    if (is_array($count_raw)) {
        $rows = [];

        foreach (luux_acf_usps_normalize_items($count_raw) as $item) {
            $mapped = [];

            if (! empty($item['icon']) || ! empty($item['field_luux_usps_icon'])) {
                $mapped['icon'] = (int) ($item['icon'] ?? $item['field_luux_usps_icon']);
            }

            foreach (['title', 'description'] as $name) {
                $value = $item[$name] ?? $item['field_luux_usps_' . $name] ?? null;

                if ($value !== null && $value !== '') {
                    $mapped[$name] = (string) $value;
                }
            }

            if ($mapped !== []) {
                $rows[] = $mapped;
            }
        }

        return $rows;
    }

    $count = is_numeric($count_raw) ? (int) $count_raw : 0;

    if ($count < 1) {
        for ($i = 0; $i < 20; $i++) {
            $title = luux_read_section_meta($post_id, $row_index, 'items_' . $i . '_title');
            $icon  = luux_read_section_meta($post_id, $row_index, 'items_' . $i . '_icon');

            if (($title !== null && $title !== '') || ($icon !== null && (int) $icon > 0)) {
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
        $item = [];

        $icon = luux_read_section_meta($post_id, $row_index, 'items_' . $i . '_icon');

        if ($icon !== null && is_numeric($icon)) {
            $item['icon'] = (int) $icon;
        }

        $title = luux_read_section_meta($post_id, $row_index, 'items_' . $i . '_title');

        if ($title !== null && $title !== '') {
            $item['title'] = (string) $title;
        }

        $description = luux_read_section_meta($post_id, $row_index, 'items_' . $i . '_description');

        if ($description !== null && $description !== '') {
            $item['description'] = (string) $description;
        }

        if ($item !== []) {
            $rows[] = $item;
        }
    }

    return $rows;
}

add_filter('acf/pre_update_metadata', function ($check, $post_id, $name, $value, $hidden) {
    if ($hidden || ! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page') {
        return $check;
    }

    if (! is_string($name) || ! luux_acf_is_usps_meta_name((int) $post_id, $name)) {
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

    if (! luux_acf_usps_layout_matches(luux_page_section_layout_slug($full_meta, $row_index))) {
        return $value;
    }

    if ($name === 'items' && $key === 'field_luux_usps_items') {
        $from_meta = luux_usps_items_from_meta((int) $post_id, $row_index);

        return $from_meta !== [] ? $from_meta : $value;
    }

    if (! in_array($name, ['section_label', 'heading', 'light_background', 'section_id'], true)) {
        return $value;
    }

    if (
        function_exists('luux_page_sections_uses_legacy_storage')
        && luux_page_sections_uses_legacy_storage((int) $post_id)
    ) {
        $direct = luux_read_section_meta((int) $post_id, $row_index, $name);

        if ($direct !== null && $direct !== '') {
            return $name === 'light_background' ? (bool) $direct : $direct;
        }

        if ($name === 'light_background' && $direct !== null) {
            return (bool) $direct;
        }
    }

    if ($value !== null && $value !== false && $value !== '') {
        return $value;
    }

    $stash = get_post_meta((int) $post_id, LUUX_USPS_STASH_META, true);

    if (is_array($stash) && isset($stash[(string) $row_index][$name]) && $stash[(string) $row_index][$name] !== '') {
        $stashed = $stash[(string) $row_index][$name];

        return $name === 'light_background' ? (bool) $stashed : $stashed;
    }

    $direct = luux_read_section_meta((int) $post_id, $row_index, $name);

    if ($direct === null || $direct === '') {
        return $value;
    }

    return $name === 'light_background' ? (bool) $direct : $direct;
}, 26, 3);

add_filter('rest_pre_insert_page', function ($prepared_post, WP_REST_Request $request) {
    luux_acf_usps_capture_rest_request($request);

    return $prepared_post;
}, 5, 2);

add_filter('rest_pre_update_page', function ($prepared_post, WP_REST_Request $request) {
    luux_acf_usps_capture_rest_request($request);

    return $prepared_post;
}, 5, 2);

add_action('acf/save_post', function ($post_id): void {
    if (! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page') {
        return;
    }

    luux_acf_save_usps_meta((int) $post_id);
}, 99999);

add_action('save_post_page', function (int $post_id): void {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    luux_acf_save_usps_meta($post_id);
}, 99999);

add_action('rest_after_insert_page', function (\WP_Post $post, WP_REST_Request $request): void {
    if ($post->post_type !== 'page') {
        return;
    }

    luux_acf_usps_capture_rest_request($request);
    luux_acf_save_usps_meta((int) $post->ID);
}, 99999, 2);

add_action('wp_ajax_luux_save_usps_fields', 'luux_acf_ajax_save_usps_fields');

add_action('admin_enqueue_scripts', function (string $hook): void {
    if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    if (! $screen || $screen->post_type !== 'page') {
        return;
    }

    $path = get_template_directory() . '/assets/js/admin-layout-usps.js';

    if (! is_readable($path)) {
        return;
    }

    wp_enqueue_script(
        'luux-admin-layout-usps',
        get_template_directory_uri() . '/assets/js/admin-layout-usps.js',
        ['jquery', 'acf-input', 'wp-api-fetch', 'wp-data'],
        (string) filemtime($path),
        true
    );

    wp_localize_script('luux-admin-layout-usps', 'luuxLayoutUsps', [
        'nonce'   => wp_create_nonce('luux_usps_save'),
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
});
