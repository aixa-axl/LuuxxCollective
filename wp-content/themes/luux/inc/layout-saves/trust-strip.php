<?php
/**
 * Trust Strip layout — direct postmeta save/read for legacy imported pages.
 * Scalars are blocked + custom-saved; press_logos repeater stays unblocked.
 */

defined('ABSPATH') || exit;

const LUUX_TRUST_STRIP_STASH_META = '_luux_trust_strip_stash';
const LUUX_TRUST_STRIP_LAYOUT     = 'trust_strip';

/** @return array<string, array{0: string, 1: string, 2: string}> */
function luux_acf_trust_strip_field_map(): array {
    return [
        'field_luux_trust_strip_rating' => ['rating', 'field_luux_trust_strip_rating', 'text'],
        'field_luux_trust_strip_award'  => ['award', 'field_luux_trust_strip_award', 'text'],
    ];
}

function luux_acf_trust_strip_layout_matches(string $layout): bool {
    return in_array($layout, [LUUX_TRUST_STRIP_LAYOUT, 'layout_luux_trust_strip'], true);
}

/** @return list<int> */
function luux_acf_trust_strip_db_row_indices(int $post_id): array {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta === []) {
        return [];
    }

    $indices = [];
    $count   = luux_acf_page_sections_row_count($meta);

    for ($i = 0; $i < $count; $i++) {
        if (luux_acf_trust_strip_layout_matches(luux_page_section_layout_slug($meta, $i))) {
            $indices[] = $i;
        }
    }

    return $indices;
}

function luux_acf_trust_strip_row_layout(int $post_id, int $index): string {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta !== []) {
        return luux_page_section_layout_slug($meta, $index);
    }

    return luux_page_section_layout_slug(
        ['page_sections' => get_post_meta($post_id, 'page_sections', true)],
        $index
    );
}

function luux_acf_resolve_trust_strip_db_index(int $post_id, int $row_index, int $row_nth = 0): int {
    $resolved = luux_find_section_row_index($post_id, LUUX_TRUST_STRIP_LAYOUT);

    if ($resolved !== null) {
        return $resolved;
    }

    $db_indices = luux_acf_trust_strip_db_row_indices($post_id);

    if (isset($db_indices[$row_nth])) {
        return $db_indices[$row_nth];
    }

    if (luux_acf_trust_strip_layout_matches(luux_acf_trust_strip_row_layout($post_id, $row_index))) {
        return $row_index;
    }

    return $row_index;
}

/** @return array<string, mixed>|null */
function luux_acf_trust_strip_rest_payload(?array $set = null, bool $reset = false): ?array {
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

function luux_acf_trust_strip_capture_rest_request(WP_REST_Request $request): void {
    $params = $request->get_json_params();

    if (! is_array($params)) {
        return;
    }

    if (! empty($params['acf']) && is_array($params['acf'])) {
        luux_acf_trust_strip_rest_payload($params['acf']);

        return;
    }

    if (! empty($params['meta']['acf']) && is_array($params['meta']['acf'])) {
        luux_acf_trust_strip_rest_payload($params['meta']['acf']);
    }
}

/**
 * @param array<string|int, mixed> $logos
 * @return list<array<string, mixed>>
 */
function luux_acf_trust_strip_normalize_logos(array $logos): array {
    $normalized = [];

    foreach ($logos as $logo) {
        if (is_array($logo)) {
            $normalized[] = $logo;
        }
    }

    return $normalized;
}

/** @return list<array<string, mixed>> */
function luux_acf_trust_strip_decode_logos_json(mixed $json): array {
    if (! is_string($json) || $json === '') {
        return [];
    }

    $decoded = json_decode(wp_unslash($json), true);

    return is_array($decoded) ? luux_acf_trust_strip_normalize_logos($decoded) : [];
}

function luux_acf_trust_strip_row_has_field(array $row, string $field_key, string $name): bool {
    return array_key_exists($field_key, $row) || array_key_exists($name, $row);
}

function luux_acf_trust_strip_row_value(array $row, string $field_key, string $name): mixed {
    if (array_key_exists($field_key, $row)) {
        return wp_unslash($row[$field_key]);
    }

    if (array_key_exists($name, $row)) {
        return wp_unslash($row[$name]);
    }

    return null;
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_trust_strip_post_rows_from_acf_array(array $fc): array {
    $rows       = [];
    $form_index = 0;

    foreach ($fc as $key => $row) {
        if (! is_array($row) || empty($row['acf_fc_layout'])) {
            continue;
        }

        if (luux_acf_trust_strip_layout_matches((string) $row['acf_fc_layout'])) {
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
function luux_acf_trust_strip_find_rows_in_array(array $data): array {
    $rows = [];

    foreach ($data as $key => $value) {
        if (! is_array($value)) {
            continue;
        }

        if (luux_acf_trust_strip_layout_matches((string) ($value['acf_fc_layout'] ?? ''))) {
            $index  = is_numeric($key) ? (int) $key : count($rows);
            $rows[] = ['index' => $index, 'row' => $value];
            continue;
        }

        if (function_exists('luux_acf_array_is_sequential_list') && luux_acf_array_is_sequential_list($value)) {
            foreach ($value as $child_key => $child) {
                if (! is_array($child)) {
                    continue;
                }

                if (luux_acf_trust_strip_layout_matches((string) ($child['acf_fc_layout'] ?? ''))) {
                    $index  = is_numeric($child_key) ? (int) $child_key : count($rows);
                    $rows[] = ['index' => $index, 'row' => $child];
                }
            }
        }
    }

    return $rows;
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_trust_strip_early_post_rows(): array {
    if (empty($_POST['luux_trust_strip']) || ! is_array($_POST['luux_trust_strip'])) {
        return [];
    }

    $field_map   = luux_acf_trust_strip_field_map();
    $name_to_key = [];

    foreach ($field_map as $key => [$name]) {
        $name_to_key[$name] = $key;
    }

    $rows = [];

    foreach ($_POST['luux_trust_strip'] as $index => $fields) {
        if (! is_array($fields)) {
            continue;
        }

        $row = ['acf_fc_layout' => LUUX_TRUST_STRIP_LAYOUT];

        foreach ($fields as $name => $value) {
            if (! is_string($name)) {
                continue;
            }

            $value = wp_unslash($value);

            if ($name === 'press_logos_json') {
                $logos = luux_acf_trust_strip_decode_logos_json($value);

                if ($logos !== []) {
                    $row['field_luux_trust_strip_press'] = $logos;
                    $row['press_logos']                  = $logos;
                }

                continue;
            }

            if (! array_key_exists($name, $name_to_key) || $value === '' || $value === null) {
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
function luux_acf_trust_strip_post_rows_from_acf(): array {
    if (empty($_POST['acf']) || ! is_array($_POST['acf'])) {
        return [];
    }

    $fc = $_POST['acf']['field_luux_page_sections'] ?? null;

    if (! is_array($fc)) {
        return luux_acf_trust_strip_find_rows_in_array($_POST['acf']);
    }

    return luux_acf_trust_strip_post_rows_from_acf_array($fc);
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_trust_strip_post_rows_from_payload(array $payload): array {
    if (! empty($payload['field_luux_page_sections']) && is_array($payload['field_luux_page_sections'])) {
        return luux_acf_trust_strip_post_rows_from_acf_array($payload['field_luux_page_sections']);
    }

    return luux_acf_trust_strip_find_rows_in_array($payload);
}

/**
 * @param list<array{index: int, row: array<string, mixed>}> $primary
 * @param list<array{index: int, row: array<string, mixed>}> ...$sources
 * @return list<array{index: int, row: array<string, mixed>}>
 */
function luux_acf_trust_strip_enrich_rows(array $primary, array ...$sources): array {
    foreach ($primary as &$item) {
        if (! is_array($item['row'] ?? null)) {
            continue;
        }

        $has_logos = ! empty($item['row']['field_luux_trust_strip_press']) || ! empty($item['row']['press_logos']);

        if ($has_logos) {
            continue;
        }

        foreach ($sources as $source) {
            foreach ($source as $candidate) {
                $cand  = $candidate['row'] ?? [];
                $logos = is_array($cand) ? ($cand['field_luux_trust_strip_press'] ?? $cand['press_logos'] ?? null) : null;

                if (is_array($logos) && $logos !== []) {
                    $item['row']['field_luux_trust_strip_press'] = $logos;
                    $item['row']['press_logos']                  = $logos;
                    break 2;
                }
            }
        }
    }
    unset($item);

    return $primary;
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_trust_strip_post_rows_with_indices(): array {
    $early = luux_acf_trust_strip_early_post_rows();
    $acf   = luux_acf_trust_strip_post_rows_from_acf();
    $rest  = [];

    $payload = luux_acf_trust_strip_rest_payload();

    if (is_array($payload) && $payload !== []) {
        $rest = luux_acf_trust_strip_post_rows_from_payload($payload);
    }

    if ($early !== []) {
        return luux_acf_trust_strip_enrich_rows($early, $acf, $rest);
    }

    if ($acf !== []) {
        return luux_acf_trust_strip_enrich_rows($acf, $rest);
    }

    return $rest;
}

/**
 * @param array<int, array<string, mixed>> $logos
 */
function luux_acf_persist_trust_strip_logos(int $post_id, int $db_index, array $logos): void {
    $prefix = 'page_sections_' . (int) $db_index . '_';
    $logos  = luux_acf_trust_strip_normalize_logos($logos);
    $count  = 0;

    foreach ($logos as $i => $logo) {
        if (! is_array($logo)) {
            continue;
        }

        $i    = (int) $i;
        $name = $logo['field_luux_trust_strip_press_name'] ?? $logo['name'] ?? null;

        if ($name === null || $name === '') {
            continue;
        }

        luux_acf_replace_section_meta(
            $post_id,
            $prefix . 'press_logos_' . $i . '_name',
            (string) $name,
            'field_luux_trust_strip_press_name'
        );
        $count = $i + 1;
    }

    if ($count > 0) {
        luux_acf_replace_section_meta($post_id, $prefix . 'press_logos', $count, 'field_luux_trust_strip_press');
    }
}

/**
 * @param array<string, mixed> $row
 */
function luux_acf_persist_trust_strip_row(int $post_id, int $db_index, array $row): void {
    $prefix    = 'page_sections_' . (int) $db_index . '_';
    $field_map = luux_acf_trust_strip_field_map();

    foreach ($field_map as $field_key => [$name, $ref]) {
        if (! luux_acf_trust_strip_row_has_field($row, $field_key, $name)) {
            continue;
        }

        $value = luux_acf_trust_strip_row_value($row, $field_key, $name);

        if ($value === '' || $value === null) {
            continue;
        }

        luux_acf_replace_section_meta($post_id, $prefix . $name, $value, $ref);
    }

    $logos = $row['field_luux_trust_strip_press'] ?? $row['press_logos'] ?? null;

    if (is_array($logos) && $logos !== []) {
        luux_acf_persist_trust_strip_logos($post_id, $db_index, $logos);
    }
}

/**
 * @param array<string, string> $fields
 */
function luux_acf_stash_trust_strip_row(int $post_id, int $row_index, array $fields): void {
    $stash = get_post_meta($post_id, LUUX_TRUST_STRIP_STASH_META, true);

    if (! is_array($stash)) {
        $stash = [];
    }

    $stash[(string) $row_index] = $fields;
    update_post_meta($post_id, LUUX_TRUST_STRIP_STASH_META, $stash);
}

function luux_acf_restore_trust_strip_from_stash(int $post_id): void {
    $stash = get_post_meta($post_id, LUUX_TRUST_STRIP_STASH_META, true);

    if (! is_array($stash) || $stash === []) {
        return;
    }

    $field_map = luux_acf_trust_strip_field_map();
    $nth       = 0;

    foreach ($stash as $row_key => $fields) {
        if (! is_array($fields) || $fields === []) {
            continue;
        }

        $db_index = luux_acf_resolve_trust_strip_db_index($post_id, (int) $row_key, $nth);
        $row      = ['acf_fc_layout' => LUUX_TRUST_STRIP_LAYOUT];

        foreach ($fields as $name => $value) {
            if (! is_string($name) || $value === '' || $value === null) {
                continue;
            }

            if ($name === '_press_logos_json') {
                $logos = luux_acf_trust_strip_decode_logos_json($value);

                if ($logos !== []) {
                    $row['field_luux_trust_strip_press'] = $logos;
                    $row['press_logos']                  = $logos;
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
            luux_acf_persist_trust_strip_row($post_id, (int) $db_index, $row);
        }

        $nth++;
    }
}

function luux_acf_persist_trust_strip_from_request(int $post_id): void {
    $post_rows = luux_acf_trust_strip_post_rows_with_indices();

    if ($post_rows === []) {
        return;
    }

    $field_map = luux_acf_trust_strip_field_map();

    foreach ($post_rows as $n => $item) {
        $db_index = luux_acf_resolve_trust_strip_db_index($post_id, (int) ($item['index'] ?? 0), $n);
        $stash    = [];

        foreach ($field_map as $field_key => [$name]) {
            if (! luux_acf_trust_strip_row_has_field($item['row'], $field_key, $name)) {
                continue;
            }

            $value = luux_acf_trust_strip_row_value($item['row'], $field_key, $name);

            if ($value === '' || $value === null) {
                continue;
            }

            $stash[$name] = is_scalar($value) ? (string) $value : '';
        }

        $logos = $item['row']['field_luux_trust_strip_press'] ?? $item['row']['press_logos'] ?? null;

        if (is_array($logos) && $logos !== []) {
            $logos                                      = luux_acf_trust_strip_normalize_logos($logos);
            $stash['_press_logos_json']                 = wp_json_encode($logos);
            $item['row']['press_logos']                 = $logos;
            $item['row']['field_luux_trust_strip_press'] = $logos;
        }

        if ($stash !== []) {
            luux_acf_stash_trust_strip_row($post_id, (int) $db_index, $stash);
        }

        luux_acf_persist_trust_strip_row($post_id, (int) $db_index, $item['row']);
    }
}

function luux_acf_save_trust_strip_meta(int $post_id): void {
    luux_acf_persist_trust_strip_from_request($post_id);
    luux_acf_restore_trust_strip_from_stash($post_id);
    luux_acf_trust_strip_rest_payload(null, true);
}

function luux_acf_is_trust_strip_meta_name(int $post_id, string $name): bool {
    if (! preg_match('/^page_sections_(\d+)_(.+)$/', $name, $matches)) {
        return false;
    }

    $index  = (int) $matches[1];
    $suffix = $matches[2];

    if (! luux_acf_trust_strip_layout_matches(luux_acf_trust_strip_row_layout($post_id, $index))) {
        return false;
    }

    return in_array($suffix, ['rating', 'award'], true);
}

function luux_acf_ajax_save_trust_strip_fields(): void {
    if (! current_user_can('edit_pages')) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }

    check_ajax_referer('luux_trust_strip_save', 'nonce');

    $post_id   = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    $row_index = isset($_POST['row_index']) ? (int) $_POST['row_index'] : -1;
    $row_nth   = isset($_POST['row_nth']) ? (int) $_POST['row_nth'] : 0;
    $fields    = isset($_POST['fields']) && is_array($_POST['fields']) ? wp_unslash($_POST['fields']) : [];

    if (! empty($_POST['press_logos_json']) && is_string($_POST['press_logos_json'])) {
        $fields['press_logos_json'] = wp_unslash($_POST['press_logos_json']);
    }

    if ($post_id < 1 || get_post_type($post_id) !== 'page' || $row_index < 0 || $fields === []) {
        wp_send_json_error(['message' => 'Invalid request'], 400);
    }

    if (! current_user_can('edit_post', $post_id)) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }

    $db_index  = luux_acf_resolve_trust_strip_db_index($post_id, $row_index, $row_nth);
    $field_map = luux_acf_trust_strip_field_map();
    $row       = ['acf_fc_layout' => LUUX_TRUST_STRIP_LAYOUT];
    $stash     = [];

    foreach ($fields as $name => $value) {
        if (! is_string($name) || $value === '' || $value === null) {
            continue;
        }

        if ($name === 'press_logos_json') {
            $logos = luux_acf_trust_strip_decode_logos_json($value);

            if ($logos !== []) {
                $row['field_luux_trust_strip_press'] = $logos;
                $row['press_logos']                  = $logos;
                $stash['_press_logos_json']          = is_string($value) ? wp_unslash($value) : wp_json_encode($logos);
            }

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
        luux_acf_stash_trust_strip_row($post_id, $db_index, $stash);
    }

    luux_acf_persist_trust_strip_row($post_id, $db_index, $row);

    wp_send_json_success(['row_index' => $db_index]);
}

/**
 * @return list<array<string, mixed>>
 */
function luux_trust_strip_press_logos_from_meta(int $post_id, int $row_index): array {
    $count_raw = luux_read_section_meta($post_id, $row_index, 'press_logos');

    if (is_array($count_raw)) {
        $rows = [];

        foreach (luux_acf_trust_strip_normalize_logos($count_raw) as $logo) {
            $name = $logo['name'] ?? $logo['field_luux_trust_strip_press_name'] ?? null;

            if ($name !== null && $name !== '') {
                $rows[] = ['name' => (string) $name];
            }
        }

        return $rows;
    }

    $count = is_numeric($count_raw) ? (int) $count_raw : 0;

    if ($count < 1) {
        for ($i = 0; $i < 30; $i++) {
            $name = luux_read_section_meta($post_id, $row_index, 'press_logos_' . $i . '_name');

            if ($name !== null && $name !== '') {
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
        $name = luux_read_section_meta($post_id, $row_index, 'press_logos_' . $i . '_name');

        if ($name !== null && $name !== '') {
            $rows[] = ['name' => (string) $name];
        }
    }

    return $rows;
}

add_filter('acf/pre_update_metadata', function ($check, $post_id, $name, $value, $hidden) {
    if ($hidden || ! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page') {
        return $check;
    }

    if (! is_string($name) || ! luux_acf_is_trust_strip_meta_name((int) $post_id, $name)) {
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

    if (! luux_acf_trust_strip_layout_matches(luux_page_section_layout_slug($full_meta, $row_index))) {
        return $value;
    }

    if ($name === 'press_logos' && $key === 'field_luux_trust_strip_press') {
        $from_meta = luux_trust_strip_press_logos_from_meta((int) $post_id, $row_index);

        return $from_meta !== [] ? $from_meta : $value;
    }

    if (! in_array($name, ['rating', 'award'], true)) {
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

    $stash = get_post_meta((int) $post_id, LUUX_TRUST_STRIP_STASH_META, true);

    if (is_array($stash) && isset($stash[(string) $row_index][$name]) && $stash[(string) $row_index][$name] !== '') {
        return $stash[(string) $row_index][$name];
    }

    $direct = luux_read_section_meta((int) $post_id, $row_index, $name);

    return ($direct !== null && $direct !== '') ? $direct : $value;
}, 26, 3);

add_filter('rest_pre_insert_page', function ($prepared_post, WP_REST_Request $request) {
    luux_acf_trust_strip_capture_rest_request($request);

    return $prepared_post;
}, 5, 2);

add_filter('rest_pre_update_page', function ($prepared_post, WP_REST_Request $request) {
    luux_acf_trust_strip_capture_rest_request($request);

    return $prepared_post;
}, 5, 2);

add_action('acf/save_post', function ($post_id): void {
    if (! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page') {
        return;
    }

    luux_acf_save_trust_strip_meta((int) $post_id);
}, 99999);

add_action('save_post_page', function (int $post_id): void {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    luux_acf_save_trust_strip_meta($post_id);
}, 99999);

add_action('rest_after_insert_page', function (\WP_Post $post, WP_REST_Request $request): void {
    if ($post->post_type !== 'page') {
        return;
    }

    luux_acf_trust_strip_capture_rest_request($request);
    luux_acf_save_trust_strip_meta((int) $post->ID);
}, 99999, 2);

add_action('wp_ajax_luux_save_trust_strip_fields', 'luux_acf_ajax_save_trust_strip_fields');

add_action('admin_enqueue_scripts', function (string $hook): void {
    if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    if (! $screen || $screen->post_type !== 'page') {
        return;
    }

    $path = get_template_directory() . '/assets/js/admin-layout-trust-strip.js';

    if (! is_readable($path)) {
        return;
    }

    wp_enqueue_script(
        'luux-admin-layout-trust-strip',
        get_template_directory_uri() . '/assets/js/admin-layout-trust-strip.js',
        ['jquery', 'acf-input', 'wp-api-fetch', 'wp-data'],
        (string) filemtime($path),
        true
    );

    wp_localize_script('luux-admin-layout-trust-strip', 'luuxLayoutTrustStrip', [
        'nonce'   => wp_create_nonce('luux_trust_strip_save'),
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
});
