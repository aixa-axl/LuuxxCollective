<?php
/**
 * Featured Offers layout — direct postmeta save/read for legacy imported pages.
 * Mirrors the hero layout approach.
 */

defined('ABSPATH') || exit;

const LUUX_FEATURED_OFFERS_STASH_META = '_luux_featured_offers_stash';

const LUUX_FEATURED_OFFERS_LAYOUT     = 'featured_offers';

/** @return array<string, array{0: string, 1: string}> */
function luux_acf_featured_offers_field_map(): array {
    return [
        'field_luux_featured_offers_section_label' => ['section_label', 'field_luux_featured_offers_section_label'],
        'field_luux_featured_offers_heading'       => ['heading', 'field_luux_featured_offers_heading'],
        'field_luux_featured_offers_intro'         => ['intro', 'field_luux_featured_offers_intro'],
    ];
}

/** @return list<int> */
function luux_acf_featured_offers_db_row_indices(int $post_id): array {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta === []) {
        return [];
    }

    $indices = [];
    $count   = luux_acf_page_sections_row_count($meta);

    for ($i = 0; $i < $count; $i++) {
        if (luux_page_section_layout_slug($meta, $i) === LUUX_FEATURED_OFFERS_LAYOUT) {
            $indices[] = $i;
        }
    }

    return $indices;
}

function luux_acf_featured_offers_row_layout(int $post_id, int $index): string {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta !== []) {
        return luux_page_section_layout_slug($meta, $index);
    }

    return luux_page_section_layout_slug(
        ['page_sections' => get_post_meta($post_id, 'page_sections', true)],
        $index
    );
}

function luux_acf_resolve_featured_offers_db_index(int $post_id, int $row_index, int $row_nth = 0): int {
    $resolved = luux_find_section_row_index($post_id, LUUX_FEATURED_OFFERS_LAYOUT);

    if ($resolved !== null) {
        return $resolved;
    }

    $db_indices = luux_acf_featured_offers_db_row_indices($post_id);

    if (isset($db_indices[$row_nth])) {
        return $db_indices[$row_nth];
    }

    if (luux_acf_featured_offers_row_layout($post_id, $row_index) === LUUX_FEATURED_OFFERS_LAYOUT) {
        return $row_index;
    }

    return $row_index;
}

/** @return array<string, mixed>|null */
function luux_acf_featured_offers_rest_payload(?array $set = null, bool $reset = false): ?array {
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

function luux_acf_featured_offers_capture_rest_request(WP_REST_Request $request): void {
    $params = $request->get_json_params();

    if (! is_array($params)) {
        return;
    }

    if (! empty($params['acf']) && is_array($params['acf'])) {
        luux_acf_featured_offers_rest_payload($params['acf']);

        return;
    }

    if (! empty($params['meta']['acf']) && is_array($params['meta']['acf'])) {
        luux_acf_featured_offers_rest_payload($params['meta']['acf']);
    }
}

function luux_acf_featured_offers_layout_matches(string $layout): bool {
    return in_array($layout, [LUUX_FEATURED_OFFERS_LAYOUT, 'layout_luux_featured_offers'], true);
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_featured_offers_post_rows_from_payload(array $payload): array {
    if (! empty($payload['field_luux_page_sections']) && is_array($payload['field_luux_page_sections'])) {
        return luux_acf_featured_offers_post_rows_from_acf_array($payload['field_luux_page_sections']);
    }

    return luux_acf_featured_offers_find_rows_in_array($payload);
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_featured_offers_post_rows_from_acf_array(array $fc): array {
    $rows       = [];
    $form_index = 0;

    foreach ($fc as $key => $row) {
        if (! is_array($row) || empty($row['acf_fc_layout'])) {
            continue;
        }

        if (luux_acf_featured_offers_layout_matches((string) $row['acf_fc_layout'])) {
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

function luux_acf_featured_offers_attachment_id(mixed $value): int {
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

function luux_acf_featured_offers_row_has_field(array $row, string $field_key, string $name): bool {
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

function luux_acf_featured_offers_row_value(array $row, string $field_key, string $name): mixed {
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
function luux_acf_featured_offers_early_post_rows(): array {
    if (empty($_POST['luux_featured_offers']) || ! is_array($_POST['luux_featured_offers'])) {
        return [];
    }

    $field_map   = luux_acf_featured_offers_field_map();
    $name_to_key = [];

    foreach ($field_map as $key => [$name]) {
        $name_to_key[$name] = $key;
    }

    $rows = [];

    foreach ($_POST['luux_featured_offers'] as $index => $fields) {
        if (! is_array($fields)) {
            continue;
        }

        $row = ['acf_fc_layout' => LUUX_FEATURED_OFFERS_LAYOUT];

        foreach ($fields as $name => $value) {
            if (! is_string($name) || ! array_key_exists($name, $name_to_key)) {
                continue;
            }

            $value = wp_unslash($value);

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

/**
 * @param array<string, mixed> $data
 * @return list<array{index: int, row: array<string, mixed>}>
 */
function luux_acf_featured_offers_find_rows_in_array(array $data): array {
    $rows = [];

    foreach ($data as $key => $value) {
        if (! is_array($value)) {
            continue;
        }

        $layout = (string) ($value['acf_fc_layout'] ?? '');

        if (luux_acf_featured_offers_layout_matches($layout)) {
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

                if (luux_acf_featured_offers_layout_matches($child_layout)) {
                    $index  = is_numeric($child_key) ? (int) $child_key : count($rows);
                    $rows[] = ['index' => $index, 'row' => $child];
                }
            }
        }
    }

    return $rows;
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_featured_offers_post_rows_from_acf(): array {
    if (empty($_POST['acf']) || ! is_array($_POST['acf'])) {
        return [];
    }

    $fc = $_POST['acf']['field_luux_page_sections'] ?? null;

    if (! is_array($fc)) {
        return luux_acf_featured_offers_find_rows_in_array($_POST['acf']);
    }

    return luux_acf_featured_offers_post_rows_from_acf_array($fc);
}

/** @return list<array{index: int, row: array<string, mixed>}> */
function luux_acf_featured_offers_post_rows_with_indices(): array {
    $early = luux_acf_featured_offers_early_post_rows();

    if ($early !== []) {
        return $early;
    }

    $acf = luux_acf_featured_offers_post_rows_from_acf();

    if ($acf !== []) {
        return $acf;
    }

    $payload = luux_acf_featured_offers_rest_payload();

    if (is_array($payload) && $payload !== []) {
        return luux_acf_featured_offers_post_rows_from_payload($payload);
    }

    return [];
}

/**
 * @param array<int, array<string, mixed>> $offers
 */
function luux_acf_persist_featured_offers_items(int $post_id, int $db_index, array $offers): void {
    $prefix = 'page_sections_' . (int) $db_index . '_';
    $count  = 0;

    foreach ($offers as $i => $offer) {
        if (! is_array($offer)) {
            continue;
        }

        $has_value = false;

        $image = $offer['field_luux_featured_offers_image'] ?? $offer['image'] ?? null;

        if ($image !== null && $image !== '') {
            $image_id = luux_acf_featured_offers_attachment_id($image);

            if ($image_id > 0) {
                luux_acf_replace_section_meta(
                    $post_id,
                    $prefix . 'offers_' . (int) $i . '_image',
                    $image_id,
                    'field_luux_featured_offers_image'
                );
                $has_value = true;
            }
        }

        $title = $offer['field_luux_featured_offers_title'] ?? $offer['title'] ?? null;

        if (is_string($title) && $title !== '') {
            luux_acf_replace_section_meta(
                $post_id,
                $prefix . 'offers_' . (int) $i . '_title',
                $title,
                'field_luux_featured_offers_title'
            );
            $has_value = true;
        }

        $description = $offer['field_luux_featured_offers_description'] ?? $offer['description'] ?? null;

        if (is_string($description) && $description !== '') {
            luux_acf_replace_section_meta(
                $post_id,
                $prefix . 'offers_' . (int) $i . '_description',
                $description,
                'field_luux_featured_offers_description'
            );
            $has_value = true;
        }

        $price = $offer['field_luux_featured_offers_price'] ?? $offer['price'] ?? null;

        if (is_string($price) && $price !== '') {
            luux_acf_replace_section_meta(
                $post_id,
                $prefix . 'offers_' . (int) $i . '_price',
                $price,
                'field_luux_featured_offers_price'
            );
            $has_value = true;
        }

        $link = $offer['field_luux_featured_offers_link'] ?? $offer['link'] ?? null;

        if (is_array($link) && ! empty($link['url'])) {
            luux_acf_replace_section_meta(
                $post_id,
                $prefix . 'offers_' . (int) $i . '_link',
                $link,
                'field_luux_featured_offers_link'
            );
            $has_value = true;
        }

        if ($has_value) {
            $count = (int) $i + 1;
        }
    }

    if ($count > 0) {
        luux_acf_replace_section_meta($post_id, $prefix . 'offers', $count, 'field_luux_featured_offers_offers');
    }
}

/**
 * @param array<string, mixed> $row
 */
function luux_acf_persist_featured_offers_row(int $post_id, int $db_index, array $row): void {
    $prefix    = 'page_sections_' . (int) $db_index . '_';
    $field_map = luux_acf_featured_offers_field_map();

    foreach ($field_map as $post_key => [$name, $ref]) {
        if (! luux_acf_featured_offers_row_has_field($row, $post_key, $name)) {
            continue;
        }

        $value    = luux_acf_featured_offers_row_value($row, $post_key, $name);
        $meta_key = $prefix . $name;

        if ($value === '' || $value === null) {
            continue;
        }

        luux_acf_replace_section_meta($post_id, $meta_key, $value, $ref);
    }

    $offers = $row['field_luux_featured_offers_offers'] ?? $row['offers'] ?? null;

    if (is_array($offers)) {
        luux_acf_persist_featured_offers_items($post_id, $db_index, $offers);
    }
}

/**
 * @param array<string, string|int> $fields
 */
function luux_acf_stash_featured_offers_row(int $post_id, int $row_index, array $fields): void {
    $stash = get_post_meta($post_id, LUUX_FEATURED_OFFERS_STASH_META, true);

    if (! is_array($stash)) {
        $stash = [];
    }

    $stash[(string) $row_index] = $fields;
    update_post_meta($post_id, LUUX_FEATURED_OFFERS_STASH_META, $stash);
}

function luux_acf_restore_featured_offers_from_stash(int $post_id): void {
    $stash = get_post_meta($post_id, LUUX_FEATURED_OFFERS_STASH_META, true);

    if (! is_array($stash) || $stash === []) {
        return;
    }

    $field_map = luux_acf_featured_offers_field_map();
    $nth       = 0;

    foreach ($stash as $row_key => $fields) {
        if (! is_array($fields) || $fields === []) {
            continue;
        }

        $db_index = luux_acf_resolve_featured_offers_db_index($post_id, (int) $row_key, $nth);
        $row      = ['acf_fc_layout' => LUUX_FEATURED_OFFERS_LAYOUT];

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
            luux_acf_persist_featured_offers_row($post_id, (int) $db_index, $row);
        }

        $nth++;
    }
}

function luux_acf_persist_featured_offers_from_request(int $post_id): void {
    $post_rows = luux_acf_featured_offers_post_rows_with_indices();

    if ($post_rows === []) {
        return;
    }

    $field_map = luux_acf_featured_offers_field_map();

    foreach ($post_rows as $n => $item) {
        $db_index = luux_acf_resolve_featured_offers_db_index($post_id, (int) ($item['index'] ?? 0), $n);
        $stash    = [];

        foreach ($field_map as $post_key => [$name]) {
            if (! luux_acf_featured_offers_row_has_field($item['row'], $post_key, $name)) {
                continue;
            }

            $value = luux_acf_featured_offers_row_value($item['row'], $post_key, $name);

            if ($value === '' || $value === null) {
                continue;
            }

            $stash[$name] = is_scalar($value) ? (string) $value : '';
        }

        if ($stash !== []) {
            luux_acf_stash_featured_offers_row($post_id, (int) $db_index, $stash);
        }

        luux_acf_persist_featured_offers_row($post_id, (int) $db_index, $item['row']);
    }
}

function luux_acf_save_featured_offers_meta(int $post_id): void {
    luux_acf_persist_featured_offers_from_request($post_id);
    luux_acf_restore_featured_offers_from_stash($post_id);
    luux_acf_featured_offers_rest_payload(null, true);
}

function luux_acf_is_featured_offers_meta_name(int $post_id, string $name): bool {
    if (! preg_match('/^page_sections_(\d+)_(.+)$/', $name, $matches)) {
        return false;
    }

    $index  = (int) $matches[1];
    $suffix = $matches[2];

    if (luux_acf_featured_offers_row_layout($post_id, $index) !== LUUX_FEATURED_OFFERS_LAYOUT) {
        return false;
    }

    $scalar = ['section_label', 'heading', 'intro', 'offers'];

    if (in_array($suffix, $scalar, true)) {
        return true;
    }

    return (bool) preg_match('/^offers_\d+_(image|title|description|price|link)$/', $suffix);
}

function luux_acf_ajax_save_featured_offers_fields(): void {
    if (! current_user_can('edit_pages')) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }

    check_ajax_referer('luux_featured_offers_save', 'nonce');

    $post_id   = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    $row_index = isset($_POST['row_index']) ? (int) $_POST['row_index'] : -1;
    $row_nth   = isset($_POST['row_nth']) ? (int) $_POST['row_nth'] : 0;
    $fields    = isset($_POST['fields']) && is_array($_POST['fields']) ? wp_unslash($_POST['fields']) : [];

    if ($post_id < 1 || get_post_type($post_id) !== 'page' || $row_index < 0 || $fields === []) {
        wp_send_json_error(['message' => 'Invalid request'], 400);
    }

    if (! current_user_can('edit_post', $post_id)) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }

    $db_index  = luux_acf_resolve_featured_offers_db_index($post_id, $row_index, $row_nth);
    $field_map = luux_acf_featured_offers_field_map();
    $row       = ['acf_fc_layout' => LUUX_FEATURED_OFFERS_LAYOUT];
    $stash     = [];

    foreach ($fields as $name => $value) {
        if (! is_string($name)) {
            continue;
        }

        if ($name === 'offers' && is_array($value)) {
            $row['field_luux_featured_offers_offers'] = $value;
            $row['offers']                        = $value;
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

    luux_acf_stash_featured_offers_row($post_id, $db_index, $stash);
    luux_acf_persist_featured_offers_row($post_id, $db_index, $row);

    wp_send_json_success(['row_index' => $db_index]);
}

/**
 * Read featured offers repeater from postmeta on legacy pages.
 *
 * @return list<array<string, mixed>>
 */
function luux_featured_offers_offers_from_meta(int $post_id, int $row_index): array {
    $count = luux_read_section_meta($post_id, $row_index, 'offers');

    if ($count === null || ! is_numeric($count) || (int) $count < 1) {
        return [];
    }

    $rows = [];

    for ($i = 0; $i < (int) $count; $i++) {
        $offer = [];

        $image = luux_read_section_meta($post_id, $row_index, 'offers_' . $i . '_image');

        if ($image !== null && is_numeric($image)) {
            $offer['image'] = (int) $image;
        }

        $title = luux_read_section_meta($post_id, $row_index, 'offers_' . $i . '_title');

        if ($title !== null && $title !== '') {
            $offer['title'] = (string) $title;
        }

        $description = luux_read_section_meta($post_id, $row_index, 'offers_' . $i . '_description');

        if ($description !== null && $description !== '') {
            $offer['description'] = (string) $description;
        }

        $price = luux_read_section_meta($post_id, $row_index, 'offers_' . $i . '_price');

        if ($price !== null && $price !== '') {
            $offer['price'] = (string) $price;
        }

        $link = luux_read_section_meta($post_id, $row_index, 'offers_' . $i . '_link');

        if ($link !== null) {
            if (is_string($link)) {
                $link = maybe_unserialize($link);
            }

            if (is_array($link) && ! empty($link['url'])) {
                $offer['link'] = $link;
            }
        }

        if ($offer !== []) {
            $rows[] = $offer;
        }
    }

    return $rows;
}

add_filter('acf/pre_update_metadata', function ($check, $post_id, $name, $value, $hidden) {
    if ($hidden || ! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page') {
        return $check;
    }

    if (! is_string($name) || ! luux_acf_is_featured_offers_meta_name((int) $post_id, $name)) {
        return $check;
    }

    return true;
}, 10, 5);

add_filter('acf/load_value', function ($value, $post_id, $field) {
    if (! is_admin() || ! is_array($field)) {
        return $value;
    }

    $name    = $field['name'] ?? '';
    $allowed = ['section_label', 'heading', 'intro'];

    if (! in_array($name, $allowed, true)) {
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

    if (luux_page_section_layout_slug($full_meta, $row_index) !== LUUX_FEATURED_OFFERS_LAYOUT) {
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

    if ($value !== null && $value !== false && $value !== '') {
        return $value;
    }

    $stash = get_post_meta((int) $post_id, LUUX_FEATURED_OFFERS_STASH_META, true);

    if (is_array($stash)) {
        $db_indices = luux_acf_featured_offers_db_row_indices((int) $post_id);
        $stash_key  = (string) $row_index;

        foreach ($db_indices as $db_index) {
            if ((int) $db_index === $row_index && isset($stash[(string) $db_index][$name])) {
                $stash_key = (string) $db_index;
                break;
            }
        }

        if (isset($stash[$stash_key][$name]) && $stash[$stash_key][$name] !== '') {
            return $stash[$stash_key][$name];
        }
    }

    $direct = luux_read_section_meta((int) $post_id, $row_index, $name);

    if ($direct === null || $direct === '') {
        return $value;
    }

    return $direct;
}, 22, 3);

add_filter('rest_pre_insert_page', function ($prepared_post, WP_REST_Request $request) {
    luux_acf_featured_offers_capture_rest_request($request);

    return $prepared_post;
}, 5, 2);

add_filter('rest_pre_update_page', function ($prepared_post, WP_REST_Request $request) {
    luux_acf_featured_offers_capture_rest_request($request);

    return $prepared_post;
}, 5, 2);

add_action('acf/save_post', function ($post_id): void {
    if (! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page') {
        return;
    }

    luux_acf_save_featured_offers_meta((int) $post_id);
}, 99999);

add_action('save_post_page', function (int $post_id): void {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    luux_acf_save_featured_offers_meta($post_id);
}, 99999);

add_action('rest_after_insert_page', function (\WP_Post $post, WP_REST_Request $request): void {
    if ($post->post_type !== 'page') {
        return;
    }

    luux_acf_featured_offers_capture_rest_request($request);
    luux_acf_save_featured_offers_meta((int) $post->ID);
}, 99999, 2);

add_action('wp_ajax_luux_save_featured_offers_fields', 'luux_acf_ajax_save_featured_offers_fields');

add_action('admin_enqueue_scripts', function (string $hook): void {
    if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    if (! $screen || $screen->post_type !== 'page') {
        return;
    }

    $path = get_template_directory() . '/assets/js/admin-layout-featured-offers.js';

    if (! is_readable($path)) {
        return;
    }

    wp_enqueue_script(
        'luux-admin-layout-featured-offers',
        get_template_directory_uri() . '/assets/js/admin-layout-featured-offers.js',
        ['jquery', 'acf-input', 'wp-api-fetch', 'wp-data'],
        (string) filemtime($path),
        true
    );

    wp_localize_script('luux-admin-layout-featured-offers', 'luuxLayoutFeaturedOffers', [
        'nonce'   => wp_create_nonce('luux_featured_offers_save'),
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
});
