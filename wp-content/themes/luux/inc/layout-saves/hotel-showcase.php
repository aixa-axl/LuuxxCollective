<?php
/**
 * Hotel Showcase layout — direct postmeta save/read for legacy imported pages.
 * Scalars are blocked + custom-saved; hotels repeater (incl. nested) stays unblocked.
 */

defined('ABSPATH') || exit;

const LUUX_HOTEL_SHOWCASE_STASH_META = '_luux_hotel_showcase_stash';
const LUUX_HOTEL_SHOWCASE_LAYOUT     = 'hotel_showcase';

/** @return array<string, array{0: string, 1: string}> */
function luux_acf_hotel_showcase_field_map(): array {
    return [
        'field_luux_hotel_showcase_heading'    => ['heading', 'field_luux_hotel_showcase_heading'],
        'field_luux_hotel_showcase_intro'      => ['intro', 'field_luux_hotel_showcase_intro'],
        'field_luux_hotel_showcase_footnote'   => ['footnote', 'field_luux_hotel_showcase_footnote'],
        'field_luux_hotel_showcase_section_id' => ['section_id', 'field_luux_hotel_showcase_section_id'],
    ];
}

function luux_acf_hotel_showcase_layout_matches(string $layout): bool {
    return in_array($layout, [LUUX_HOTEL_SHOWCASE_LAYOUT, 'layout_luux_hotel_showcase'], true);
}

/** @return list<int> */
function luux_acf_hotel_showcase_db_row_indices(int $post_id): array {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta === []) {
        return [];
    }

    $indices = [];
    $count   = luux_acf_page_sections_row_count($meta);

    for ($i = 0; $i < $count; $i++) {
        if (luux_acf_hotel_showcase_layout_matches(luux_page_section_layout_slug($meta, $i))) {
            $indices[] = $i;
        }
    }

    return $indices;
}

function luux_acf_hotel_showcase_row_layout(int $post_id, int $index): string {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta !== []) {
        return luux_page_section_layout_slug($meta, $index);
    }

    return luux_page_section_layout_slug(
        ['page_sections' => get_post_meta($post_id, 'page_sections', true)],
        $index
    );
}

function luux_acf_resolve_hotel_showcase_db_index(int $post_id, int $row_index, int $row_nth = 0): int {
    $resolved = luux_find_section_row_index($post_id, LUUX_HOTEL_SHOWCASE_LAYOUT);

    if ($resolved !== null) {
        return $resolved;
    }

    $db_indices = luux_acf_hotel_showcase_db_row_indices($post_id);

    if (isset($db_indices[$row_nth])) {
        return $db_indices[$row_nth];
    }

    if (luux_acf_hotel_showcase_layout_matches(luux_acf_hotel_showcase_row_layout($post_id, $row_index))) {
        return $row_index;
    }

    return $row_index;
}

/** @return array<string, mixed>|null */
function luux_acf_hotel_showcase_rest_payload(?array $set = null, bool $reset = false): ?array {
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

function luux_acf_hotel_showcase_capture_rest_request(WP_REST_Request $request): void {
    $params = $request->get_json_params();

    if (! is_array($params)) {
        return;
    }

    if (! empty($params['acf']) && is_array($params['acf'])) {
        luux_acf_hotel_showcase_rest_payload($params['acf']);

        return;
    }

    if (! empty($params['meta']['acf']) && is_array($params['meta']['acf'])) {
        luux_acf_hotel_showcase_rest_payload($params['meta']['acf']);
    }
}

function luux_acf_hotel_showcase_attachment_id(mixed $value): int {
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

function luux_acf_hotel_showcase_normalize_link(mixed $value): ?array {
    if (is_string($value) && $value !== '') {
        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            $decoded = maybe_unserialize($value);
        }

        $value = $decoded;
    }

    if (! is_array($value) || empty($value['url'])) {
        return null;
    }

    $title = isset($value['title']) ? (string) $value['title'] : '';
    $title = str_replace(['\\u2192', 'u2192'], '→', $title);

    return [
        'url'    => (string) $value['url'],
        'title'  => $title,
        'target' => isset($value['target']) ? (string) $value['target'] : '',
    ];
}

/**
 * @param array<string|int, mixed> $hotels
 * @return list<array<string, mixed>>
 */
function luux_acf_hotel_showcase_normalize_hotels(array $hotels): array {
    $normalized = [];

    foreach ($hotels as $hotel) {
        if (is_array($hotel)) {
            $normalized[] = $hotel;
        }
    }

    return $normalized;
}

/** @return list<array<string, mixed>> */
function luux_acf_hotel_showcase_decode_hotels_json(mixed $json): array {
    if (! is_string($json) || $json === '') {
        return [];
    }

    $decoded = json_decode(wp_unslash($json), true);

    return is_array($decoded) ? luux_acf_hotel_showcase_normalize_hotels($decoded) : [];
}

function luux_acf_hotel_showcase_row_has_field(array $row, string $field_key, string $name): bool {
    return array_key_exists($field_key, $row) || array_key_exists($name, $row);
}

function luux_acf_hotel_showcase_row_value(array $row, string $field_key, string $name): mixed {
    if (array_key_exists($field_key, $row)) {
        return wp_unslash($row[$field_key]);
    }

    if (array_key_exists($name, $row)) {
        return wp_unslash($row[$name]);
    }

    return null;
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_hotel_showcase_post_rows_from_acf_array(array $fc): array {
    $rows       = [];
    $form_index = 0;

    foreach ($fc as $key => $row) {
        if (! is_array($row) || empty($row['acf_fc_layout'])) {
            continue;
        }

        if (luux_acf_hotel_showcase_layout_matches((string) $row['acf_fc_layout'])) {
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
function luux_acf_hotel_showcase_find_rows_in_array(array $data): array {
    $rows = [];

    foreach ($data as $key => $value) {
        if (! is_array($value)) {
            continue;
        }

        if (luux_acf_hotel_showcase_layout_matches((string) ($value['acf_fc_layout'] ?? ''))) {
            $index  = is_numeric($key) ? (int) $key : count($rows);
            $rows[] = ['index' => $index, 'row' => $value];
            continue;
        }

        if (function_exists('luux_acf_array_is_sequential_list') && luux_acf_array_is_sequential_list($value)) {
            foreach ($value as $child_key => $child) {
                if (! is_array($child)) {
                    continue;
                }

                if (luux_acf_hotel_showcase_layout_matches((string) ($child['acf_fc_layout'] ?? ''))) {
                    $index  = is_numeric($child_key) ? (int) $child_key : count($rows);
                    $rows[] = ['index' => $index, 'row' => $child];
                }
            }
        }
    }

    return $rows;
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_hotel_showcase_early_post_rows(): array {
    if (empty($_POST['luux_hotel_showcase']) || ! is_array($_POST['luux_hotel_showcase'])) {
        return [];
    }

    $field_map   = luux_acf_hotel_showcase_field_map();
    $name_to_key = [];

    foreach ($field_map as $key => [$name]) {
        $name_to_key[$name] = $key;
    }

    $rows = [];

    foreach ($_POST['luux_hotel_showcase'] as $index => $fields) {
        if (! is_array($fields)) {
            continue;
        }

        $row = ['acf_fc_layout' => LUUX_HOTEL_SHOWCASE_LAYOUT];

        foreach ($fields as $name => $value) {
            if (! is_string($name)) {
                continue;
            }

            $value = wp_unslash($value);

            if ($name === 'hotels_json') {
                $hotels = luux_acf_hotel_showcase_decode_hotels_json($value);

                if ($hotels !== []) {
                    $row['field_luux_hotel_showcase_hotels'] = $hotels;
                    $row['hotels']                           = $hotels;
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
function luux_acf_hotel_showcase_post_rows_from_acf(): array {
    if (empty($_POST['acf']) || ! is_array($_POST['acf'])) {
        return [];
    }

    $fc = $_POST['acf']['field_luux_page_sections'] ?? null;

    if (! is_array($fc)) {
        return luux_acf_hotel_showcase_find_rows_in_array($_POST['acf']);
    }

    return luux_acf_hotel_showcase_post_rows_from_acf_array($fc);
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_hotel_showcase_post_rows_from_payload(array $payload): array {
    if (! empty($payload['field_luux_page_sections']) && is_array($payload['field_luux_page_sections'])) {
        return luux_acf_hotel_showcase_post_rows_from_acf_array($payload['field_luux_page_sections']);
    }

    return luux_acf_hotel_showcase_find_rows_in_array($payload);
}

/**
 * @param list<array{index: int, row: array<string, mixed>}> $primary
 * @param list<array{index: int, row: array<string, mixed>}> ...$sources
 * @return list<array{index: int, row: array<string, mixed>}>
 */
function luux_acf_hotel_showcase_enrich_rows(array $primary, array ...$sources): array {
    foreach ($primary as &$item) {
        if (! is_array($item['row'] ?? null)) {
            continue;
        }

        $has_hotels = ! empty($item['row']['field_luux_hotel_showcase_hotels']) || ! empty($item['row']['hotels']);

        if ($has_hotels) {
            continue;
        }

        foreach ($sources as $source) {
            foreach ($source as $candidate) {
                $cand   = $candidate['row'] ?? [];
                $hotels = is_array($cand) ? ($cand['field_luux_hotel_showcase_hotels'] ?? $cand['hotels'] ?? null) : null;

                if (is_array($hotels) && $hotels !== []) {
                    $item['row']['field_luux_hotel_showcase_hotels'] = $hotels;
                    $item['row']['hotels']                           = $hotels;
                    break 2;
                }
            }
        }
    }
    unset($item);

    return $primary;
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_hotel_showcase_post_rows_with_indices(): array {
    $early = luux_acf_hotel_showcase_early_post_rows();
    $acf   = luux_acf_hotel_showcase_post_rows_from_acf();
    $rest  = [];

    $payload = luux_acf_hotel_showcase_rest_payload();

    if (is_array($payload) && $payload !== []) {
        $rest = luux_acf_hotel_showcase_post_rows_from_payload($payload);
    }

    if ($early !== []) {
        return luux_acf_hotel_showcase_enrich_rows($early, $acf, $rest);
    }

    if ($acf !== []) {
        return luux_acf_hotel_showcase_enrich_rows($acf, $rest);
    }

    return $rest;
}

/**
 * @param array<int, array<string, mixed>> $hotels
 */
function luux_acf_persist_hotel_showcase_hotels(int $post_id, int $db_index, array $hotels): void {
    $prefix = 'page_sections_' . (int) $db_index . '_';
    $hotels = luux_acf_hotel_showcase_normalize_hotels($hotels);
    $count  = 0;

    foreach ($hotels as $i => $hotel) {
        if (! is_array($hotel)) {
            continue;
        }

        $i         = (int) $i;
        $has_value = false;

        $name = $hotel['field_luux_hotel_showcase_hotel_name'] ?? $hotel['name'] ?? null;

        if ($name !== null && $name !== '') {
            luux_acf_replace_section_meta(
                $post_id,
                $prefix . 'hotels_' . $i . '_name',
                (string) $name,
                'field_luux_hotel_showcase_hotel_name'
            );
            $has_value = true;
        }

        $image = $hotel['field_luux_hotel_showcase_hotel_image'] ?? $hotel['image'] ?? null;

        if ($image !== null && $image !== '') {
            $image_id = luux_acf_hotel_showcase_attachment_id($image);

            if ($image_id > 0) {
                luux_acf_replace_section_meta(
                    $post_id,
                    $prefix . 'hotels_' . $i . '_image',
                    $image_id,
                    'field_luux_hotel_showcase_hotel_image'
                );
                $has_value = true;
            }
        }

        $description = $hotel['field_luux_hotel_showcase_hotel_description'] ?? $hotel['description'] ?? null;

        if ($description !== null && $description !== '') {
            luux_acf_replace_section_meta(
                $post_id,
                $prefix . 'hotels_' . $i . '_description',
                (string) $description,
                'field_luux_hotel_showcase_hotel_description'
            );
            $has_value = true;
        }

        $inclusions = $hotel['field_luux_hotel_showcase_hotel_inclusions'] ?? $hotel['inclusions'] ?? null;

        if (is_array($inclusions) && $inclusions !== []) {
            $inc_count = 0;

            foreach ($inclusions as $j => $inc) {
                if (! is_array($inc)) {
                    continue;
                }

                $text = $inc['field_luux_hotel_showcase_inclusion_text'] ?? $inc['text'] ?? null;

                if ($text === null || $text === '') {
                    continue;
                }

                luux_acf_replace_section_meta(
                    $post_id,
                    $prefix . 'hotels_' . $i . '_inclusions_' . (int) $j . '_text',
                    (string) $text,
                    'field_luux_hotel_showcase_inclusion_text'
                );
                $inc_count = (int) $j + 1;
            }

            if ($inc_count > 0) {
                luux_acf_replace_section_meta(
                    $post_id,
                    $prefix . 'hotels_' . $i . '_inclusions',
                    $inc_count,
                    'field_luux_hotel_showcase_hotel_inclusions'
                );
                $has_value = true;
            }
        }

        $cta = $hotel['field_luux_hotel_showcase_hotel_cta'] ?? $hotel['cta'] ?? null;
        $cta = luux_acf_hotel_showcase_normalize_link($cta);

        if ($cta !== null) {
            luux_acf_replace_section_meta(
                $post_id,
                $prefix . 'hotels_' . $i . '_cta',
                $cta,
                'field_luux_hotel_showcase_hotel_cta'
            );
            $has_value = true;
        }

        if ($has_value) {
            $count = $i + 1;
        }
    }

    if ($count > 0) {
        luux_acf_replace_section_meta($post_id, $prefix . 'hotels', $count, 'field_luux_hotel_showcase_hotels');
    }
}

/**
 * @param array<string, mixed> $row
 */
function luux_acf_persist_hotel_showcase_row(int $post_id, int $db_index, array $row): void {
    $prefix    = 'page_sections_' . (int) $db_index . '_';
    $field_map = luux_acf_hotel_showcase_field_map();

    foreach ($field_map as $field_key => [$name, $ref]) {
        if (! luux_acf_hotel_showcase_row_has_field($row, $field_key, $name)) {
            continue;
        }

        $value = luux_acf_hotel_showcase_row_value($row, $field_key, $name);

        if ($value === '' || $value === null) {
            continue;
        }

        luux_acf_replace_section_meta($post_id, $prefix . $name, $value, $ref);
    }

    $hotels = $row['field_luux_hotel_showcase_hotels'] ?? $row['hotels'] ?? null;

    if (is_array($hotels) && $hotels !== []) {
        luux_acf_persist_hotel_showcase_hotels($post_id, $db_index, $hotels);
    }
}

/**
 * @param array<string, string> $fields
 */
function luux_acf_stash_hotel_showcase_row(int $post_id, int $row_index, array $fields): void {
    $stash = get_post_meta($post_id, LUUX_HOTEL_SHOWCASE_STASH_META, true);

    if (! is_array($stash)) {
        $stash = [];
    }

    $stash[(string) $row_index] = $fields;
    update_post_meta($post_id, LUUX_HOTEL_SHOWCASE_STASH_META, $stash);
}

function luux_acf_restore_hotel_showcase_from_stash(int $post_id): void {
    $stash = get_post_meta($post_id, LUUX_HOTEL_SHOWCASE_STASH_META, true);

    if (! is_array($stash) || $stash === []) {
        return;
    }

    $field_map = luux_acf_hotel_showcase_field_map();
    $nth       = 0;

    foreach ($stash as $row_key => $fields) {
        if (! is_array($fields) || $fields === []) {
            continue;
        }

        $db_index = luux_acf_resolve_hotel_showcase_db_index($post_id, (int) $row_key, $nth);
        $row      = ['acf_fc_layout' => LUUX_HOTEL_SHOWCASE_LAYOUT];

        foreach ($fields as $name => $value) {
            if (! is_string($name) || $value === '' || $value === null) {
                continue;
            }

            if ($name === '_hotels_json') {
                $hotels = luux_acf_hotel_showcase_decode_hotels_json($value);

                if ($hotels !== []) {
                    $row['field_luux_hotel_showcase_hotels'] = $hotels;
                    $row['hotels']                           = $hotels;
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
            luux_acf_persist_hotel_showcase_row($post_id, (int) $db_index, $row);
        }

        $nth++;
    }
}

function luux_acf_persist_hotel_showcase_from_request(int $post_id): void {
    $post_rows = luux_acf_hotel_showcase_post_rows_with_indices();

    if ($post_rows === []) {
        return;
    }

    $field_map = luux_acf_hotel_showcase_field_map();

    foreach ($post_rows as $n => $item) {
        $db_index = luux_acf_resolve_hotel_showcase_db_index($post_id, (int) ($item['index'] ?? 0), $n);
        $stash    = [];

        foreach ($field_map as $field_key => [$name]) {
            if (! luux_acf_hotel_showcase_row_has_field($item['row'], $field_key, $name)) {
                continue;
            }

            $value = luux_acf_hotel_showcase_row_value($item['row'], $field_key, $name);

            if ($value === '' || $value === null) {
                continue;
            }

            $stash[$name] = is_scalar($value) ? (string) $value : '';
        }

        $hotels = $item['row']['field_luux_hotel_showcase_hotels'] ?? $item['row']['hotels'] ?? null;

        if (is_array($hotels) && $hotels !== []) {
            $hotels                                         = luux_acf_hotel_showcase_normalize_hotels($hotels);
            $stash['_hotels_json']                          = wp_json_encode($hotels);
            $item['row']['hotels']                          = $hotels;
            $item['row']['field_luux_hotel_showcase_hotels'] = $hotels;
        }

        if ($stash !== []) {
            luux_acf_stash_hotel_showcase_row($post_id, (int) $db_index, $stash);
        }

        luux_acf_persist_hotel_showcase_row($post_id, (int) $db_index, $item['row']);
    }
}

function luux_acf_save_hotel_showcase_meta(int $post_id): void {
    luux_acf_persist_hotel_showcase_from_request($post_id);
    luux_acf_restore_hotel_showcase_from_stash($post_id);
    luux_acf_hotel_showcase_rest_payload(null, true);
}

function luux_acf_is_hotel_showcase_meta_name(int $post_id, string $name): bool {
    if (! preg_match('/^page_sections_(\d+)_(.+)$/', $name, $matches)) {
        return false;
    }

    $index  = (int) $matches[1];
    $suffix = $matches[2];

    if (! luux_acf_hotel_showcase_layout_matches(luux_acf_hotel_showcase_row_layout($post_id, $index))) {
        return false;
    }

    // Never block hotels / nested inclusions / hotel CTAs.
    return in_array($suffix, ['heading', 'intro', 'footnote', 'section_id'], true);
}

function luux_acf_ajax_save_hotel_showcase_fields(): void {
    if (! current_user_can('edit_pages')) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }

    check_ajax_referer('luux_hotel_showcase_save', 'nonce');

    $post_id   = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    $row_index = isset($_POST['row_index']) ? (int) $_POST['row_index'] : -1;
    $row_nth   = isset($_POST['row_nth']) ? (int) $_POST['row_nth'] : 0;
    $fields    = isset($_POST['fields']) && is_array($_POST['fields']) ? wp_unslash($_POST['fields']) : [];

    if (! empty($_POST['hotels_json']) && is_string($_POST['hotels_json'])) {
        $fields['hotels_json'] = wp_unslash($_POST['hotels_json']);
    }

    if ($post_id < 1 || get_post_type($post_id) !== 'page' || $row_index < 0 || $fields === []) {
        wp_send_json_error(['message' => 'Invalid request'], 400);
    }

    if (! current_user_can('edit_post', $post_id)) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }

    $db_index  = luux_acf_resolve_hotel_showcase_db_index($post_id, $row_index, $row_nth);
    $field_map = luux_acf_hotel_showcase_field_map();
    $row       = ['acf_fc_layout' => LUUX_HOTEL_SHOWCASE_LAYOUT];
    $stash     = [];

    foreach ($fields as $name => $value) {
        if (! is_string($name) || $value === '' || $value === null) {
            continue;
        }

        if ($name === 'hotels_json') {
            $hotels = luux_acf_hotel_showcase_decode_hotels_json($value);

            if ($hotels !== []) {
                $row['field_luux_hotel_showcase_hotels'] = $hotels;
                $row['hotels']                           = $hotels;
                $stash['_hotels_json']                   = is_string($value) ? wp_unslash($value) : wp_json_encode($hotels);
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
        luux_acf_stash_hotel_showcase_row($post_id, $db_index, $stash);
    }

    luux_acf_persist_hotel_showcase_row($post_id, $db_index, $row);

    wp_send_json_success(['row_index' => $db_index]);
}

/**
 * @return list<array<string, mixed>>
 */
function luux_hotel_showcase_hotels_from_meta(int $post_id, int $row_index): array {
    $count_raw = luux_read_section_meta($post_id, $row_index, 'hotels');

    if (is_array($count_raw)) {
        $rows = [];

        foreach (luux_acf_hotel_showcase_normalize_hotels($count_raw) as $hotel) {
            $mapped = luux_acf_hotel_showcase_map_hotel_item($hotel);

            if ($mapped !== []) {
                $rows[] = $mapped;
            }
        }

        return $rows;
    }

    $count = is_numeric($count_raw) ? (int) $count_raw : 0;

    if ($count < 1) {
        for ($i = 0; $i < 20; $i++) {
            $name  = luux_read_section_meta($post_id, $row_index, 'hotels_' . $i . '_name');
            $image = luux_read_section_meta($post_id, $row_index, 'hotels_' . $i . '_image');

            if (($name !== null && $name !== '') || ($image !== null && (int) $image > 0)) {
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
        $hotel = [];

        $name = luux_read_section_meta($post_id, $row_index, 'hotels_' . $i . '_name');

        if ($name !== null && $name !== '') {
            $hotel['name'] = (string) $name;
        }

        $image = luux_read_section_meta($post_id, $row_index, 'hotels_' . $i . '_image');

        if ($image !== null && is_numeric($image) && (int) $image > 0) {
            $hotel['image'] = (int) $image;
        }

        $description = luux_read_section_meta($post_id, $row_index, 'hotels_' . $i . '_description');

        if ($description !== null && $description !== '') {
            $hotel['description'] = (string) $description;
        }

        $inc_count_raw = luux_read_section_meta($post_id, $row_index, 'hotels_' . $i . '_inclusions');
        $inc_count     = is_numeric($inc_count_raw) ? (int) $inc_count_raw : 0;

        if ($inc_count < 1 && is_array($inc_count_raw)) {
            $inclusions = [];

            foreach ($inc_count_raw as $inc) {
                if (! is_array($inc)) {
                    continue;
                }

                $text = $inc['text'] ?? $inc['field_luux_hotel_showcase_inclusion_text'] ?? null;

                if ($text !== null && $text !== '') {
                    $inclusions[] = ['text' => (string) $text];
                }
            }

            if ($inclusions !== []) {
                $hotel['inclusions'] = $inclusions;
            }
        } elseif ($inc_count > 0) {
            $inclusions = [];

            for ($j = 0; $j < $inc_count; $j++) {
                $text = luux_read_section_meta($post_id, $row_index, 'hotels_' . $i . '_inclusions_' . $j . '_text');

                if ($text !== null && $text !== '') {
                    $inclusions[] = ['text' => (string) $text];
                }
            }

            if ($inclusions !== []) {
                $hotel['inclusions'] = $inclusions;
            }
        }

        $cta = luux_acf_hotel_showcase_normalize_link(
            luux_read_section_meta($post_id, $row_index, 'hotels_' . $i . '_cta')
        );

        if ($cta !== null) {
            $hotel['cta'] = $cta;
        }

        if ($hotel !== []) {
            $rows[] = $hotel;
        }
    }

    return $rows;
}

/**
 * @param array<string, mixed> $hotel
 * @return array<string, mixed>
 */
function luux_acf_hotel_showcase_map_hotel_item(array $hotel): array {
    $mapped = [];

    $name = $hotel['name'] ?? $hotel['field_luux_hotel_showcase_hotel_name'] ?? null;

    if ($name !== null && $name !== '') {
        $mapped['name'] = (string) $name;
    }

    $image = $hotel['image'] ?? $hotel['field_luux_hotel_showcase_hotel_image'] ?? null;

    if ($image !== null && is_numeric($image) && (int) $image > 0) {
        $mapped['image'] = (int) $image;
    }

    $description = $hotel['description'] ?? $hotel['field_luux_hotel_showcase_hotel_description'] ?? null;

    if ($description !== null && $description !== '') {
        $mapped['description'] = (string) $description;
    }

    $inclusions = $hotel['inclusions'] ?? $hotel['field_luux_hotel_showcase_hotel_inclusions'] ?? null;

    if (is_array($inclusions)) {
        $mapped_incs = [];

        foreach ($inclusions as $inc) {
            if (! is_array($inc)) {
                continue;
            }

            $text = $inc['text'] ?? $inc['field_luux_hotel_showcase_inclusion_text'] ?? null;

            if ($text !== null && $text !== '') {
                $mapped_incs[] = ['text' => (string) $text];
            }
        }

        if ($mapped_incs !== []) {
            $mapped['inclusions'] = $mapped_incs;
        }
    }

    $cta = luux_acf_hotel_showcase_normalize_link(
        $hotel['cta'] ?? $hotel['field_luux_hotel_showcase_hotel_cta'] ?? null
    );

    if ($cta !== null) {
        $mapped['cta'] = $cta;
    }

    return $mapped;
}

add_filter('acf/pre_update_metadata', function ($check, $post_id, $name, $value, $hidden) {
    if ($hidden || ! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page') {
        return $check;
    }

    if (! is_string($name) || ! luux_acf_is_hotel_showcase_meta_name((int) $post_id, $name)) {
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

    if (! luux_acf_hotel_showcase_layout_matches(luux_page_section_layout_slug($full_meta, $row_index))) {
        return $value;
    }

    if ($name === 'hotels' && $key === 'field_luux_hotel_showcase_hotels') {
        $from_meta = luux_hotel_showcase_hotels_from_meta((int) $post_id, $row_index);

        return $from_meta !== [] ? $from_meta : $value;
    }

    if (! in_array($name, ['heading', 'intro', 'footnote', 'section_id'], true)) {
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

    $stash = get_post_meta((int) $post_id, LUUX_HOTEL_SHOWCASE_STASH_META, true);

    if (is_array($stash) && isset($stash[(string) $row_index][$name]) && $stash[(string) $row_index][$name] !== '') {
        return $stash[(string) $row_index][$name];
    }

    $direct = luux_read_section_meta((int) $post_id, $row_index, $name);

    return ($direct !== null && $direct !== '') ? $direct : $value;
}, 26, 3);

add_filter('rest_pre_insert_page', function ($prepared_post, WP_REST_Request $request) {
    luux_acf_hotel_showcase_capture_rest_request($request);

    return $prepared_post;
}, 5, 2);

add_filter('rest_pre_update_page', function ($prepared_post, WP_REST_Request $request) {
    luux_acf_hotel_showcase_capture_rest_request($request);

    return $prepared_post;
}, 5, 2);

add_action('acf/save_post', function ($post_id): void {
    if (! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page') {
        return;
    }

    luux_acf_save_hotel_showcase_meta((int) $post_id);
}, 99999);

add_action('save_post_page', function (int $post_id): void {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    luux_acf_save_hotel_showcase_meta($post_id);
}, 99999);

add_action('rest_after_insert_page', function (\WP_Post $post, WP_REST_Request $request): void {
    if ($post->post_type !== 'page') {
        return;
    }

    luux_acf_hotel_showcase_capture_rest_request($request);
    luux_acf_save_hotel_showcase_meta((int) $post->ID);
}, 99999, 2);

add_action('wp_ajax_luux_save_hotel_showcase_fields', 'luux_acf_ajax_save_hotel_showcase_fields');

add_action('admin_enqueue_scripts', function (string $hook): void {
    if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    if (! $screen || $screen->post_type !== 'page') {
        return;
    }

    $path = get_template_directory() . '/assets/js/admin-layout-hotel-showcase.js';

    if (! is_readable($path)) {
        return;
    }

    wp_enqueue_script(
        'luux-admin-layout-hotel-showcase',
        get_template_directory_uri() . '/assets/js/admin-layout-hotel-showcase.js',
        ['jquery', 'acf-input', 'wp-api-fetch', 'wp-data'],
        (string) filemtime($path),
        true
    );

    wp_localize_script('luux-admin-layout-hotel-showcase', 'luuxLayoutHotelShowcase', [
        'nonce'   => wp_create_nonce('luux_hotel_showcase_save'),
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
});
