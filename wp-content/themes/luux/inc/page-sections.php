<?php
/**
 * Page Sections — repair broken DB field definitions, relink meta, render helpers.
 * Does not touch Site Options.
 */

defined('ABSPATH') || exit;

const LUUX_PAGE_SECTIONS_REPAIR_VERSION = 7;

function luux_acf_is_page_section_meta_key(string $key): bool {
    return (bool) preg_match('/^_?page_sections/', $key);
}

function luux_acf_page_sections_layout_count(?array $field = null): int {
    if ($field === null && function_exists('acf_get_field')) {
        $field = acf_get_field('field_luux_page_sections');
    }

    if (! is_array($field) || empty($field['layouts']) || ! is_array($field['layouts'])) {
        return 0;
    }

    return count($field['layouts']);
}

/**
 * Register Page Sections from theme JSON via local PHP (wins over broken DB copies).
 */
function luux_acf_register_page_sections_local(): bool {
    if (! function_exists('acf_add_local_field_group') || ! function_exists('acf_add_local_field')) {
        return false;
    }

    $group = luux_acf_prepare_group_from_json('group_luux_page_sections.json', 'group_luux_page_sections');
    $fc    = luux_acf_get_page_sections_field();

    if (! $group || ! $fc || luux_acf_page_sections_layout_count($fc) < 1) {
        return false;
    }

    if (function_exists('acf_remove_local_field_group')) {
        acf_remove_local_field_group('group_luux_page_sections');
    }

    $group['fields'] = [];
    acf_add_local_field_group($group);

    $fc['parent'] = 'group_luux_page_sections';
    acf_add_local_field($fc);

    return true;
}

/**
 * Remove only the database copy of Page Sections (not Site Options, not page content).
 */
function luux_acf_delete_page_sections_db_group(): void {
    if (! function_exists('acf_get_field_group')) {
        return;
    }

    $group = acf_get_field_group('group_luux_page_sections');
    if (! is_array($group) || ! empty($group['local']) || empty($group['ID'])) {
        return;
    }

    if (function_exists('acf_delete_field_group')) {
        acf_delete_field_group((int) $group['ID']);
        return;
    }

    wp_delete_post((int) $group['ID'], true);
}

function luux_acf_import_page_sections_group(): bool {
    if (! function_exists('acf_import_field_group')) {
        return false;
    }

    $group = luux_acf_prepare_group_from_json('group_luux_page_sections.json', 'group_luux_page_sections');
    if (! $group) {
        return false;
    }

    acf_import_field_group($group);

    return true;
}

/**
 * Fix Page Sections field definitions when layouts are missing.
 *
 * @return array{ok: bool, message: string}
 */
function luux_acf_repair_page_sections_definitions(): array {
    $json_path = luux_acf_resolve_json_path('group_luux_page_sections.json');
    if (! $json_path) {
        return [
            'ok'      => false,
            'message' => 'Theme JSON missing on server. Check acf-json/ and inc/acf-field-groups/ were deployed.',
        ];
    }

    $json_fc = luux_acf_get_page_sections_field();
    if (! $json_fc || luux_acf_page_sections_layout_count($json_fc) < 1) {
        return [
            'ok'      => false,
            'message' => 'Page Sections JSON is unreadable or has no layouts.',
        ];
    }

    luux_acf_delete_page_sections_db_group();
    luux_acf_register_page_sections_local();

    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }

    $layouts = luux_acf_page_sections_layout_count();
    if ($layouts < 20) {
        delete_option('luux_page_sections_repair_version');

        return [
            'ok'      => false,
            'message' => 'Repair ran but ACF still reports ' . $layouts . ' layouts (expected 25). Check JSON is deployed.',
        ];
    }

    update_option('luux_page_sections_repair_version', LUUX_PAGE_SECTIONS_REPAIR_VERSION, false);

    return [
        'ok'      => true,
        'message' => 'Page Sections repaired. ' . $layouts . ' layouts now available.',
    ];
}

function luux_acf_maybe_repair_page_sections_definitions(): void {
    static $ran = false;

    if ($ran) {
        return;
    }

    $ran = true;

    if (luux_acf_page_sections_layout_count() >= 20) {
        return;
    }

    luux_acf_repair_page_sections_definitions();
}

/** @return array<string, mixed> */
function luux_acf_page_sections_diagnostics(): array {
    $json_path = luux_acf_resolve_json_path('group_luux_page_sections.json');
    $json_fc   = luux_acf_get_page_sections_field();
    $live      = function_exists('acf_get_field') ? acf_get_field('field_luux_page_sections') : null;
    $group     = function_exists('acf_get_field_group') ? acf_get_field_group('group_luux_page_sections') : null;

    return [
        'json_path'    => $json_path ?: false,
        'json_layouts' => luux_acf_page_sections_layout_count($json_fc),
        'live_layouts' => luux_acf_page_sections_layout_count($live),
        'group_source' => is_array($group) ? ($group['local'] ?? 'database') : 'none',
        'group_id'     => is_array($group) ? ($group['ID'] ?? 0) : 0,
        'repair_done'  => (int) get_option('luux_page_sections_repair_version', 0),
        'pages_with_sections' => luux_acf_count_pages_with_sections(),
        'legacy_pages'        => luux_acf_count_legacy_pages(),
    ];
}

function luux_acf_count_pages_with_sections(): int {
    global $wpdb;

    $modern = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
        WHERE meta_key LIKE 'page\\_sections\\_%\\_acf\\_fc\\_layout'"
    );

    $legacy = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
        WHERE meta_key = 'page_sections'
        AND meta_value LIKE 'a:%'"
    );

    return max($modern, $legacy);
}

/**
 * Parse layout slugs stored in page_sections (legacy serialized array format).
 *
 * @return list<string>
 */
function luux_acf_parse_page_sections_layout_list(mixed $value): array {
    if (is_array($value)) {
        return array_values(array_filter($value, 'is_string'));
    }

    if (is_numeric($value)) {
        return [];
    }

    if (! is_string($value) || $value === '') {
        return [];
    }

    $parsed = maybe_unserialize($value);

    if (is_string($parsed)) {
        $parsed = maybe_unserialize($parsed);
    }

    if (! is_array($parsed)) {
        return [];
    }

    return array_values(array_filter($parsed, 'is_string'));
}

function luux_acf_count_legacy_pages(): int {
    global $wpdb;

    return (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
        WHERE meta_key = 'page_sections'
        AND meta_value LIKE 'a:%'"
    );
}

/**
 * Ensure ACF reference keys exist for video_tours media fields only (no full-page migration).
 */
function luux_acf_relink_video_tours_meta_for_post(int $post_id): void {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta === []) {
        return;
    }

    $count     = luux_acf_page_sections_row_count($meta);
    $field_map = luux_acf_video_tours_field_map();

    for ($i = 0; $i < $count; $i++) {
        if (luux_page_section_layout_slug($meta, $i) !== 'video_tours') {
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
 * @return array{ok: bool, message: string}
 */
function luux_acf_migrate_all_legacy_page_sections(): array {
    $pages = get_posts([
        'post_type'      => 'page',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    $migrated = 0;

    foreach ($pages as $page_id) {
        $page_id = (int) $page_id;

        if (! luux_page_sections_uses_legacy_storage($page_id)) {
            continue;
        }

        if (! luux_acf_migrate_legacy_page_sections_storage($page_id)) {
            continue;
        }

        luux_acf_relink_page_section_meta_for_post($page_id);
        $migrated++;
    }

    return [
        'ok'      => true,
        'message' => sprintf(
            /* translators: %d: number of pages migrated */
            __('Migrated %d page(s) from legacy section storage to the modern ACF format.', 'luux'),
            $migrated
        ),
    ];
}

function luux_acf_render_page_sections_tools_page(): void {
    if (! current_user_can('manage_options')) {
        return;
    }

    $result = null;
    if (isset($_GET['luux_repair_page_sections']) && check_admin_referer('luux_repair_page_sections')) {
        $result = luux_acf_repair_page_sections_definitions();
    }

    if (isset($_GET['luux_migrate_legacy_sections']) && check_admin_referer('luux_migrate_legacy_sections')) {
        $result = luux_acf_migrate_all_legacy_page_sections();
    }

    $diag = luux_acf_page_sections_diagnostics();
    $url  = wp_nonce_url(
        add_query_arg('luux_repair_page_sections', '1', admin_url('tools.php?page=luux-page-sections')),
        'luux_repair_page_sections'
    );
    $migrate_url = wp_nonce_url(
        add_query_arg('luux_migrate_legacy_sections', '1', admin_url('tools.php?page=luux-page-sections')),
        'luux_migrate_legacy_sections'
    );

    echo '<div class="wrap"><h1>Luux Page Sections</h1>';
    echo '<p>Site Options is not affected. This tool only repairs the Page Sections field group.</p>';

    if (is_array($result)) {
        $class = $result['ok'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
    }

    echo '<table class="widefat striped" style="max-width:40rem"><tbody>';
    echo '<tr><th>JSON file on server</th><td>' . ($diag['json_path'] ? '<code>' . esc_html((string) $diag['json_path']) . '</code>' : '<strong style="color:#b32d2e">Missing</strong>') . '</td></tr>';
    echo '<tr><th>Layouts in theme JSON</th><td>' . esc_html((string) $diag['json_layouts']) . ' (want 25)</td></tr>';
    echo '<tr><th>Layouts ACF is using</th><td>' . esc_html((string) $diag['live_layouts']) . ' (want 25)</td></tr>';
    echo '<tr><th>Pages with saved sections</th><td>' . esc_html((string) $diag['pages_with_sections']) . '</td></tr>';
    echo '<tr><th>Pages on legacy storage</th><td>' . esc_html((string) $diag['legacy_pages']) . '</td></tr>';
    echo '<tr><th>Field group source</th><td><code>' . esc_html((string) $diag['group_source']) . '</code></td></tr>';
    echo '</tbody></table>';

    if ($diag['live_layouts'] >= 20 && (int) $diag['pages_with_sections'] < 1) {
        echo '<div class="notice notice-warning" style="max-width:40rem;margin-top:1rem"><p><strong>Layouts are registered, but no page section content was detected.</strong> ';
        echo 'If pages look blank on the frontend, import page section postmeta from staging. ';
        echo 'Repair only fixes field definitions — it does not restore page content and must not rewrite saved meta.</p></div>';
    } elseif ($diag['live_layouts'] >= 20) {
        echo '<div class="notice notice-success" style="max-width:40rem;margin-top:1rem"><p><strong>Page Sections field group looks healthy.</strong> ';
        echo 'If a page still looks blank, edit that page and confirm sections appear in the Page Sections box.</p></div>';
    }

    echo '<div class="notice notice-warning" style="max-width:40rem;margin-top:1rem"><p><strong>Only use Repair if layouts are missing.</strong> ';
    echo 'It re-registers field definitions from theme JSON. It does not touch Site Options or page content.</p></div>';
    echo '<p><a class="button button-secondary" href="' . esc_url($url) . '">Repair field definitions only</a></p>';

    if ((int) $diag['legacy_pages'] > 0) {
        echo '<div class="notice notice-info" style="max-width:40rem;margin-top:1rem"><p><strong>Legacy imported pages detected.</strong> ';
        echo 'You do <em>not</em> need to migrate all pages. Video Tours media is fixed per page when you save that page in the editor.</p></div>';
        echo '<p class="description" style="max-width:40rem">Optional advanced: migrate every page to modern ACF storage (only use if advised).</p>';
        echo '<p><a class="button button-secondary" href="' . esc_url($migrate_url) . '">Migrate all legacy pages (advanced)</a></p>';
    }

    echo '</div>';
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

function luux_page_section_layout_slug(array $meta, int $index): string {
    $layout = $meta["page_sections_{$index}_acf_fc_layout"] ?? '';
    if ($layout !== '') {
        return $layout;
    }

    $layout_list = luux_acf_parse_page_sections_layout_list($meta['page_sections'] ?? null);

    return $layout_list[$index] ?? '';
}

function luux_page_section_count_from_meta(array $meta): int {
    $layout_list = luux_acf_parse_page_sections_layout_list($meta['page_sections'] ?? null);
    if ($layout_list !== []) {
        return count($layout_list);
    }

    if (isset($meta['page_sections']) && is_numeric($meta['page_sections']) && (int) $meta['page_sections'] > 0) {
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
    $stored = get_post_meta($post_id, 'page_sections', true);
    $meta   = ['page_sections' => $stored];

    return luux_page_section_count_from_meta($meta);
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
    $layouts     = luux_acf_page_section_layout_fields();
    $layout_list = luux_acf_parse_page_sections_layout_list($meta['page_sections'] ?? null);
    $count       = count($layout_list);

    if ($count < 1) {
        $count = luux_page_section_count_from_meta($meta);
    }

    for ($i = 0; $i < $count; $i++) {
        $layout = luux_page_section_layout_slug($meta, $i);
        if ($layout === '' || ! isset($layouts[$layout])) {
            continue;
        }

        $meta["page_sections_{$i}_acf_fc_layout"] = $layout;

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

    return $meta;
}

/**
 * Row count for ACF flexible content when meta is merged in memory (frontend helpers only).
 */
function luux_acf_page_sections_row_count(array $meta): int {
    $layout_list = luux_acf_parse_page_sections_layout_list($meta['page_sections'] ?? null);
    if ($layout_list !== []) {
        return count($layout_list);
    }

    if (isset($meta['page_sections']) && is_numeric($meta['page_sections']) && (int) $meta['page_sections'] > 0) {
        return (int) $meta['page_sections'];
    }

    return luux_page_section_count_from_meta($meta);
}

/**
 * DB row indices for video_tours layouts on a page (in order).
 *
 * @return list<int>
 */
function luux_acf_video_tours_db_row_indices(int $post_id): array {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta === []) {
        return [];
    }

    $indices = [];
    $count   = luux_acf_page_sections_row_count($meta);

    for ($i = 0; $i < $count; $i++) {
        if (luux_page_section_layout_slug($meta, $i) === 'video_tours') {
            $indices[] = $i;
        }
    }

    return $indices;
}

/**
 * video_tours rows from early hidden inputs (injected at top of form — survives max_input_vars truncation).
 *
 * @return list<array{index: int, row: array<string, mixed>}>
 */
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

        $row = ['acf_fc_layout' => 'video_tours'];

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

        $rows[] = [
            'index' => (int) $index,
            'row'   => $row,
        ];
    }

    return $rows;
}

/**
 * video_tours rows from the ACF flexible content POST payload.
 *
 * @return list<array{index: int, row: array<string, mixed>}>
 */
function luux_acf_video_tours_post_rows_from_acf(): array {
    if (empty($_POST['acf']) || ! is_array($_POST['acf'])) {
        return [];
    }

    $fc = $_POST['acf']['field_luux_page_sections'] ?? null;

    if (! is_array($fc)) {
        return luux_acf_video_tours_find_rows_in_array($_POST['acf']);
    }

    $rows       = [];
    $form_index = 0;

    foreach ($fc as $key => $row) {
        if (! is_array($row) || empty($row['acf_fc_layout'])) {
            continue;
        }

        $layout = (string) $row['acf_fc_layout'];

        if ($layout === 'video_tours' || $layout === 'layout_luux_video_tours') {
            $index = $form_index;

            if (is_numeric($key)) {
                $index = (int) $key;
            } elseif (preg_match('/^row-(\d+)$/', (string) $key, $matches)) {
                $index = (int) $matches[1];
            }

            $rows[] = [
                'index' => $index,
                'row'   => $row,
            ];
        }

        $form_index++;
    }

    return $rows;
}

/**
 * video_tours rows submitted in the current save request, with flexible-content row index.
 *
 * @return list<array{index: int, row: array<string, mixed>}>
 */
function luux_acf_video_tours_post_rows_with_indices(): array {
    $early = luux_acf_video_tours_early_post_rows();
    $acf   = luux_acf_video_tours_post_rows_from_acf();

    if ($early !== []) {
        return $early;
    }

    return $acf;
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

        $layout = (string) ($value['acf_fc_layout'] ?? '');

        if ($layout === 'video_tours' || $layout === 'layout_luux_video_tours') {
            $index = is_numeric($key) ? (int) $key : count($rows);
            $rows[] = [
                'index' => $index,
                'row'   => $value,
            ];
            continue;
        }

        if (luux_acf_array_is_sequential_list($value)) {
            foreach ($value as $child_key => $child) {
                if (! is_array($child)) {
                    continue;
                }

                $child_layout = (string) ($child['acf_fc_layout'] ?? '');

                if ($child_layout === 'video_tours' || $child_layout === 'layout_luux_video_tours') {
                    $index = is_numeric($child_key) ? (int) $child_key : count($rows);
                    $rows[] = [
                        'index' => $index,
                        'row'   => $child,
                    ];
                }
            }
        }
    }

    return $rows;
}

function luux_acf_array_is_sequential_list(array $array): bool {
    if ($array === []) {
        return true;
    }

    return array_keys($array) === range(0, count($array) - 1)
        || array_keys($array) === range(0, count($array) - 1, 1);
}

/**
 * @return list<array<string, mixed>>
 */
function luux_acf_video_tours_rows_from_request(): array {
    $rows = [];

    foreach (luux_acf_video_tours_post_rows_with_indices() as $item) {
        $rows[] = $item['row'];
    }

    return $rows;
}

/**
 * Read a submitted sub-field by ACF key or name.
 */
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

function luux_acf_is_video_tours_meta_name(int $post_id, string $name): bool {
    if (! preg_match('/^page_sections_(\d+)_(media_type_left|media_type_right|video_left|video_right|image_left|image_right)$/', $name, $matches)) {
        return false;
    }

    $index  = (int) $matches[1];
    $layout = get_post_meta($post_id, "page_sections_{$index}_acf_fc_layout", true);

    if ($layout === '') {
        $layout = luux_page_section_layout_slug(
            ['page_sections' => get_post_meta($post_id, 'page_sections', true)],
            $index
        );
    }

    return $layout === 'video_tours';
}

/**
 * Normalize a video_tours file field value to an attachment ID.
 */
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

/**
 * Current flexible-content row index (0-based) while looping page_sections.
 */
function luux_section_row_index(): int {
    if (! function_exists('get_row_index')) {
        return -1;
    }

    return (int) get_row_index() - 1;
}

/**
 * Read a page_sections sub-field directly from postmeta (newest duplicate row wins).
 */
function luux_read_section_meta(int $post_id, int $row_index, string $name): mixed {
    $meta_key = 'page_sections_' . (int) $row_index . '_' . $name;
    $raw      = get_metadata('post', $post_id, $meta_key, false);

    if (! is_array($raw) || $raw === []) {
        return null;
    }

    return luux_acf_resolve_meta_storage_value($raw);
}

/**
 * Replace a postmeta key entirely (removes duplicate import rows before writing).
 */
function luux_acf_replace_section_meta(int $post_id, string $meta_key, mixed $value, string $ref): void {
    while (delete_post_meta($post_id, $meta_key)) {
        // Remove all duplicate rows left by legacy imports.
    }

    while (delete_post_meta($post_id, '_' . $meta_key)) {
        // Remove duplicate reference rows.
    }

    update_post_meta($post_id, $meta_key, $value);
    update_post_meta($post_id, '_' . $meta_key, $ref);
}

/**
 * Find the DB row index for a layout slug on a page.
 */
function luux_find_section_row_index(int $post_id, string $layout_name): ?int {
    $full_meta = luux_acf_get_page_section_meta($post_id);

    if ($full_meta === []) {
        return null;
    }

    foreach (luux_get_page_section_row_indices($post_id) as $row_index) {
        if (luux_page_section_layout_slug($full_meta, $row_index) === $layout_name) {
            return $row_index;
        }
    }

    return null;
}

/**
 * Read a page_sections sub-field on legacy imports (direct postmeta, bypasses stale ACF reads).
 * Modern pages fall through to get_sub_field().
 */
function luux_sub_field(string $name) {
    $post_id = get_the_ID();

    if (
        $post_id
        && function_exists('luux_page_sections_uses_legacy_storage')
        && luux_page_sections_uses_legacy_storage((int) $post_id)
    ) {
        $row_index = luux_section_row_index();

        if ($row_index >= 0) {
            $raw = get_metadata('post', (int) $post_id, 'page_sections_' . $row_index . '_' . $name, false);

            if (is_array($raw) && $raw !== []) {
                return luux_acf_resolve_meta_storage_value($raw);
            }
        }
    }

    return get_sub_field($name);
}

/**
 * Hero group tag from postmeta (legacy imports).
 *
 * @return array{show: bool, logo: int}
 */
function luux_get_hero_group_tag_from_meta(int $post_id): array {
    $show = true;
    $logo = 0;

    $row_index = luux_find_section_row_index($post_id, 'hero');
    if ($row_index === null) {
        return ['show' => $show, 'logo' => $logo];
    }

    $show_val = luux_read_section_meta($post_id, $row_index, 'show_group_tag');
    if ($show_val !== null) {
        $show = ($show_val === '' || $show_val === null) ? true : (bool) $show_val;
    }

    $logo_val = luux_read_section_meta($post_id, $row_index, 'group_tag_logo');
    if ($logo_val !== null) {
        $logo = (int) $logo_val;
    }

    return ['show' => $show, 'logo' => $logo];
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

    $direct = get_post_meta($post_id, "page_sections_{$row_index}_{$name}", true);

    if ($direct === '' || $direct === false) {
        return $value;
    }

    return $direct;
}

/**
 * Map video_tours ACF field keys to stored meta names.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function luux_acf_video_tours_field_map(): array {
    return [
        'field_luux_video_tours_media_type_left'  => ['media_type_left', 'field_luux_video_tours_media_type_left'],
        'field_luux_video_tours_media_type_right' => ['media_type_right', 'field_luux_video_tours_media_type_right'],
        'field_luux_video_tours_image_left'       => ['image_left', 'field_luux_video_tours_image_left'],
        'field_luux_video_tours_image_right'      => ['image_right', 'field_luux_video_tours_image_right'],
        'field_luux_video_tours_video_left'       => ['video_left', 'field_luux_video_tours_video_left'],
        'field_luux_video_tours_video_right'      => ['video_right', 'field_luux_video_tours_video_right'],
    ];
}

/**
 * Write video_tours media fields for a single flexible-content row.
 *
 * @param array<string, mixed> $row
 */
function luux_acf_persist_video_tours_row(int $post_id, int $db_index, array $row): void {
    $prefix    = 'page_sections_' . (int) $db_index . '_';
    $field_map = luux_acf_video_tours_field_map();

    foreach ($field_map as $post_key => [$name, $ref]) {
        if (! luux_acf_video_tours_row_has_field($row, $post_key, $name)) {
            continue;
        }

        $value    = luux_acf_video_tours_row_value($row, $post_key, $name);
        $meta_key = $prefix . $name;

        if (str_starts_with($name, 'video_')) {
            $value = luux_acf_video_tours_attachment_id($value);
        }

        if ($value === '' || $value === null || $value === 0) {
            delete_post_meta($post_id, $meta_key);
            delete_post_meta($post_id, '_' . $meta_key);

            continue;
        }

        update_post_meta($post_id, $meta_key, $value);
        update_post_meta($post_id, '_' . $meta_key, $ref);
    }

    foreach (['left', 'right'] as $side) {
        $video_key  = $prefix . 'video_' . $side;
        $type_key   = $prefix . 'media_type_' . $side;
        $video_id   = luux_acf_video_tours_attachment_id(get_post_meta($post_id, $video_key, true));
        $media_type = get_post_meta($post_id, $type_key, true);

        if ($video_id && $media_type !== 'video') {
            update_post_meta($post_id, $type_key, 'video');
            update_post_meta($post_id, '_' . $type_key, 'field_luux_video_tours_media_type_' . $side);
            update_post_meta($post_id, $video_key, $video_id);
            update_post_meta($post_id, '_' . $video_key, 'field_luux_video_tours_video_' . $side);
        }
    }
}

const LUUX_VIDEO_TOURS_STASH_META = '_luux_video_tours_stash';

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

/**
 * Re-apply stashed video_tours media after ACF / block editor overwrites the database.
 */
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

        $row = ['acf_fc_layout' => 'video_tours'];

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
            luux_acf_persist_video_tours_row($post_id, (int) $db_index, $row);
        }

        $nth++;
    }

    luux_acf_sync_video_tours_media_meta($post_id);
    luux_acf_relink_video_tours_meta_for_post($post_id);
}

/**
 * Resolve the DB row index for a video_tours block (DOM index vs stored layout list).
 */
function luux_acf_resolve_video_tours_db_index(int $post_id, int $row_index, int $row_nth = 0): int {
    $db_indices = luux_acf_video_tours_db_row_indices($post_id);

    if (isset($db_indices[$row_nth])) {
        return $db_indices[$row_nth];
    }

    if (luux_acf_video_tours_row_layout($post_id, $row_index) === 'video_tours') {
        return $row_index;
    }

    return $row_index;
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

/**
 * AJAX — save video_tours media immediately (bypasses large-page POST truncation).
 */
function luux_acf_ajax_save_video_tours_media(): void {
    if (! current_user_can('edit_pages')) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }

    check_ajax_referer('luux_video_tours_save', 'nonce');

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

    $db_index = luux_acf_resolve_video_tours_db_index($post_id, $row_index, $row_nth);

    $field_map = luux_acf_video_tours_field_map();
    $row       = ['acf_fc_layout' => 'video_tours'];
    $stash     = [];

    foreach ($fields as $name => $value) {
        if (! is_string($name) || $value === '' || $value === null) {
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

    luux_acf_stash_video_tours_row($post_id, $db_index, $stash);
    luux_acf_persist_video_tours_row($post_id, $db_index, $row);
    luux_acf_sync_video_tours_media_meta($post_id);
    luux_acf_relink_video_tours_meta_for_post($post_id);

    wp_send_json_success(['row_index' => $db_index]);
}

/**
 * Write video_tours media fields directly from the ACF POST payload.
 * Imported resort pages (legacy storage) can drop conditional/file fields during a normal ACF save.
 */
function luux_acf_persist_video_tours_from_request(int $post_id): void {
    $post_rows = luux_acf_video_tours_post_rows_with_indices();

    if ($post_rows === []) {
        return;
    }

    $db_indices = luux_acf_video_tours_db_row_indices($post_id);
    $field_map  = luux_acf_video_tours_field_map();

    foreach ($post_rows as $n => $item) {
        $db_index = $db_indices[$n] ?? $item['index'] ?? null;

        if ($db_index === null) {
            continue;
        }

        $stash = [];

        foreach ($field_map as $post_key => [$name]) {
            if (! luux_acf_video_tours_row_has_field($item['row'], $post_key, $name)) {
                continue;
            }

            $value = luux_acf_video_tours_row_value($item['row'], $post_key, $name);

            if ($value === '' || $value === null || $value === 0) {
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

/**
 * Run all video_tours save handlers for a page.
 */
function luux_acf_save_video_tours_meta(int $post_id): void {
    luux_acf_persist_video_tours_from_request($post_id);
    luux_acf_restore_video_tours_from_stash($post_id);
}

/**
 * Convert imported legacy page_sections storage (serialized layout list) to modern ACF row format.
 */
function luux_acf_migrate_legacy_page_sections_storage(int $post_id): bool {
    $layout_list = luux_acf_parse_page_sections_layout_list(get_post_meta($post_id, 'page_sections', true));

    if ($layout_list === []) {
        return false;
    }

    foreach ($layout_list as $i => $layout) {
        update_post_meta($post_id, "page_sections_{$i}_acf_fc_layout", $layout);

        $layout_key = luux_acf_page_section_layout_key($layout);
        if ($layout_key) {
            update_post_meta($post_id, "_page_sections_{$i}", $layout_key);
        }
    }

    update_post_meta($post_id, 'page_sections', count($layout_list));
    update_post_meta($post_id, '_page_sections', 'field_luux_page_sections');

    return true;
}

/**
 * Keep video_tours media meta consistent after save and ensure ACF field reference keys exist.
 */
function luux_acf_sync_video_tours_media_meta(int $post_id): void {
    $meta = luux_acf_get_page_section_meta($post_id);
    if ($meta === []) {
        return;
    }

    $count = luux_acf_page_sections_row_count($meta);

    for ($i = 0; $i < $count; $i++) {
        if (luux_page_section_layout_slug($meta, $i) !== 'video_tours') {
            continue;
        }

        $prefix = "page_sections_{$i}_";

        foreach (['left', 'right'] as $side) {
            $type_key   = $prefix . 'media_type_' . $side;
            $video_key   = $prefix . 'video_' . $side;
            $media_type  = get_post_meta($post_id, $type_key, true) ?: 'image';
            $video_id    = luux_acf_video_tours_attachment_id(get_post_meta($post_id, $video_key, true));

            if ($video_id && $media_type !== 'video') {
                $media_type = 'video';
                update_post_meta($post_id, $type_key, 'video');
            }

            update_post_meta($post_id, '_' . $type_key, 'field_luux_video_tours_media_type_' . $side);

            if ($media_type === 'video') {
                delete_post_meta($post_id, $prefix . 'image_' . $side);
                delete_post_meta($post_id, '_' . $prefix . 'image_' . $side);

                $video_key = $prefix . 'video_' . $side;
                if (get_post_meta($post_id, $video_key, true)) {
                    update_post_meta($post_id, '_' . $video_key, 'field_luux_video_tours_video_' . $side);
                }

                continue;
            }

            delete_post_meta($post_id, $prefix . 'video_' . $side);
            delete_post_meta($post_id, '_' . $prefix . 'video_' . $side);

            $image_key = $prefix . 'image_' . $side;
            if (get_post_meta($post_id, $image_key, true)) {
                update_post_meta($post_id, '_' . $image_key, 'field_luux_video_tours_image_' . $side);
            }
        }
    }
}

/**
 * Resolve a postmeta value when imports left duplicate rows for the same key.
 * Editor saves often append a newer row — prefer that unless it is empty.
 */
function luux_acf_resolve_meta_storage_value(array $values): mixed {
    if ($values === []) {
        return '';
    }

    if (count($values) === 1) {
        return $values[0];
    }

    $last = $values[count($values) - 1];

    if ($last === '' || $last === null) {
        return $values[0];
    }

    return $last;
}

/** @return array<string, mixed> */
function luux_acf_get_page_section_meta(int $post_id, bool $for_display = false): array {
    $meta = [];
    $raw  = get_metadata('post', $post_id);

    if (! is_array($raw)) {
        return $meta;
    }

    foreach ($raw as $key => $values) {
        if (! is_array($values) || ! luux_acf_is_page_section_meta_key($key)) {
            continue;
        }

        $meta[$key] = $for_display
            ? luux_acf_resolve_meta_storage_value($values)
            : $values[0];
    }

    return luux_acf_fix_section_meta_refs($meta);
}

function luux_acf_relink_page_section_meta_for_post(int $post_id): void {
    $meta = luux_acf_get_page_section_meta($post_id);

    if ($meta === []) {
        return;
    }

    foreach ($meta as $key => $value) {
        if (! str_starts_with($key, '_') || ! luux_acf_is_page_section_meta_key($key)) {
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

/** @return array<string, mixed>|null */
function luux_acf_merged_page_meta(int $post_id, bool $for_display = false): ?array {
    $section_meta = luux_acf_get_page_section_meta($post_id, $for_display);

    if ($section_meta === [] || luux_page_section_count_from_meta($section_meta) < 1) {
        return null;
    }

    $all = [];
    $raw = get_metadata('post', $post_id);

    if (is_array($raw)) {
        foreach ($raw as $key => $values) {
            if (! is_array($values)) {
                continue;
            }

            $all[$key] = $for_display
                ? luux_acf_resolve_meta_storage_value($values)
                : $values[0];
        }
    }

    return array_merge($all, $section_meta);
}

/** @return list<int> */
function luux_get_page_section_row_indices(int $post_id): array {
    $indices = [];
    $raw     = get_metadata('post', $post_id);

    if (! is_array($raw)) {
        return $indices;
    }

    foreach (array_keys($raw) as $key) {
        if (preg_match('/^page_sections_(\d+)_acf_fc_layout$/', $key, $matches)) {
            $indices[] = (int) $matches[1];
        }
    }

    if ($indices !== []) {
        sort($indices);

        return array_values(array_unique($indices));
    }

    $layout_list = luux_acf_parse_page_sections_layout_list($raw['page_sections'][0] ?? null);
    if ($layout_list === []) {
        return [];
    }

    return range(0, count($layout_list) - 1);
}

/** @return array<string, mixed> */
function luux_build_single_row_meta(array $full_meta, int $row_index): array {
    $layout = luux_page_section_layout_slug($full_meta, $row_index);
    $row    = [
        'page_sections'                  => 1,
        '_page_sections'                 => 'field_luux_page_sections',
        'page_sections_0_acf_fc_layout'  => $layout,
    ];

    $layout_key = luux_acf_page_section_layout_key($layout);
    if ($layout_key) {
        $row['_page_sections_0'] = $layout_key;
    }

    $value_prefix = "page_sections_{$row_index}_";
    $ref_prefix   = "_page_sections_{$row_index}_";

    foreach ($full_meta as $key => $value) {
        if (str_starts_with($key, $value_prefix)) {
            $suffix = substr($key, strlen($value_prefix));
            if ($suffix !== '' && $suffix !== 'acf_fc_layout') {
                $row['page_sections_0_' . $suffix] = $value;
            }
        }

        if (str_starts_with($key, $ref_prefix)) {
            $suffix = substr($key, strlen($ref_prefix));
            if ($suffix !== '') {
                $row['_page_sections_0_' . $suffix] = $value;
            }
        }
    }

    return $row;
}

function luux_page_sections_uses_legacy_storage(int $post_id): bool {
    $stored = get_post_meta($post_id, 'page_sections', true);

    return luux_acf_parse_page_sections_layout_list($stored) !== [];
}

function luux_render_page_sections_by_row(int $post_id): bool {
    if (! function_exists('acf_setup_meta') || ! function_exists('have_rows')) {
        return false;
    }

    // Legacy imports store layouts as a serialized array — render via full meta instead.
    if (luux_page_sections_uses_legacy_storage($post_id)) {
        return false;
    }

    $indices = luux_get_page_section_row_indices($post_id);
    if ($indices === []) {
        return false;
    }

    $full_meta = luux_acf_get_page_section_meta($post_id);
    $rendered  = false;

    foreach ($indices as $row_index) {
        $layout = luux_page_section_layout_slug($full_meta, $row_index);
        if ($layout === '') {
            continue;
        }

        $row_meta = luux_build_single_row_meta($full_meta, $row_index);
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

function luux_acf_is_page_edit_screen(): bool {
    if (! is_admin()) {
        return false;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && $screen->id === 'page') {
        return true;
    }

    if (isset($_GET['post']) && is_numeric($_GET['post'])) {
        return get_post_type((int) $_GET['post']) === 'page';
    }

    return false;
}

add_action('acf/include_fields', function (): void {
    luux_acf_register_page_sections_local();
}, 999);

add_action('acf/init', 'luux_acf_maybe_repair_page_sections_definitions', 99);

add_filter('acf/load_field', function ($field) {
    if (! is_array($field) || ($field['key'] ?? '') !== 'field_luux_page_sections') {
        return $field;
    }

    $from_json = luux_acf_get_page_sections_field();
    if (! $from_json) {
        return $field;
    }

    $from_json['ID']     = $field['ID'] ?? 0;
    $from_json['parent'] = $field['parent'] ?? 'group_luux_page_sections';

    return luux_acf_normalize_field_tree($from_json);
}, 999);

add_filter('acf/pre_load_meta', function ($null, $post_id) {
    static $loading = [];

    if (! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page') {
        return $null;
    }

    $post_id = (int) $post_id;

    if (isset($loading[$post_id])) {
        return $null;
    }

    if (is_admin() && ! luux_acf_is_page_edit_screen()) {
        return $null;
    }

    // Legacy imports: on the frontend only, inject merged meta so editor saves are read.
    // Admin (including the page editor) must stay on raw meta so ACF saves are not broken.
    if (luux_page_sections_uses_legacy_storage($post_id)) {
        if (is_admin()) {
            return $null;
        }

        $loading[$post_id] = true;
        $meta              = luux_acf_merged_page_meta($post_id, true);
        unset($loading[$post_id]);

        if (! is_array($meta)) {
            return $meta;
        }

        $raw_page_sections = get_post_meta($post_id, 'page_sections', true);
        if ($raw_page_sections !== '' && $raw_page_sections !== false) {
            $meta['page_sections'] = $raw_page_sections;
        }

        return $meta;
    }

    $loading[$post_id] = true;
    $meta              = luux_acf_merged_page_meta($post_id);
    unset($loading[$post_id]);

    if (! is_array($meta)) {
        return $meta;
    }

    $raw_page_sections = get_post_meta($post_id, 'page_sections', true);
    if ($raw_page_sections !== '' && $raw_page_sections !== false) {
        $meta['page_sections'] = $raw_page_sections;
    }

    return $meta;
}, 10, 2);

add_filter('acf/pre_update_metadata', function ($check, $post_id, $name, $value, $hidden) {
    if ($hidden || ! is_numeric($post_id) || get_post_type((int) $post_id) !== 'page') {
        return $check;
    }

    if (! is_string($name) || ! luux_acf_is_video_tours_meta_name((int) $post_id, $name)) {
        return $check;
    }

    // ACF must not overwrite video_tours media — theme handlers own these keys.
    return true;
}, 10, 5);

add_filter('acf/load_value', function ($value, $post_id, $field) {
    if (! is_admin() || ! is_array($field)) {
        return $value;
    }

    $name = $field['name'] ?? '';
    $allowed = ['media_type_left', 'media_type_right', 'video_left', 'video_right', 'image_left', 'image_right'];

    if (! in_array($name, $allowed, true)) {
        return $value;
    }

    if ($value !== null && $value !== false && $value !== '') {
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
            return $stash[$stash_key][$name];
        }
    }

    $direct = get_post_meta((int) $post_id, "page_sections_{$row_index}_{$name}", true);

    if ($direct === '' || $direct === false) {
        return $value;
    }

    return $direct;
}, 20, 3);

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

add_action('rest_after_insert_page', function (\WP_Post $post): void {
    if ($post->post_type !== 'page') {
        return;
    }

    luux_acf_save_video_tours_meta((int) $post->ID);
}, 99999, 1);

add_action('wp_ajax_luux_save_video_tours_media', 'luux_acf_ajax_save_video_tours_media');

add_action('admin_enqueue_scripts', function (string $hook): void {
    if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    if (! $screen || $screen->post_type !== 'page') {
        return;
    }

    $path = get_template_directory() . '/assets/js/admin-video-tours.js';

    if (! is_readable($path)) {
        return;
    }

    wp_enqueue_script(
        'luux-admin-video-tours',
        get_template_directory_uri() . '/assets/js/admin-video-tours.js',
        ['jquery', 'acf-input'],
        (string) filemtime($path),
        true
    );

    if (function_exists('use_block_editor_for_post_type') && use_block_editor_for_post_type('page')) {
        wp_enqueue_script('wp-data');
    }

    wp_localize_script('luux-admin-video-tours', 'luuxVideoTours', [
        'nonce'   => wp_create_nonce('luux_video_tours_save'),
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
});

add_action('admin_menu', function (): void {
    add_management_page(
        __('Luux Page Sections', 'luux'),
        __('Luux Page Sections', 'luux'),
        'manage_options',
        'luux-page-sections',
        'luux_acf_render_page_sections_tools_page'
    );
});

add_action('admin_notices', function (): void {
    if (! current_user_can('manage_options') || ! function_exists('acf_get_field')) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && $screen->id === 'tools_page_luux-page-sections') {
        return;
    }

    if (luux_acf_page_sections_layout_count() >= 20) {
        return;
    }

    $url = esc_url(admin_url('tools.php?page=luux-page-sections'));
    echo '<div class="notice notice-error"><p><strong>Luux:</strong> Page Sections layouts are missing (site content will not show). ';
    echo '<a href="' . $url . '">Open Tools → Luux Page Sections</a> to repair.</p></div>';
});

require get_template_directory() . '/inc/layout-saves/hero.php';
require get_template_directory() . '/inc/layout-saves/featured-offers.php';
require get_template_directory() . '/inc/layout-saves/cta-strip.php';
require get_template_directory() . '/inc/layout-saves/travel-style.php';
require get_template_directory() . '/inc/layout-saves/destinations.php';
