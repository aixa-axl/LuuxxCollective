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

function luux_acf_array_is_sequential_list(array $array): bool {
    if ($array === []) {
        return true;
    }

    return array_keys($array) === range(0, count($array) - 1)
        || array_keys($array) === range(0, count($array) - 1, 1);
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
require get_template_directory() . '/inc/layout-saves/contact-strip.php';
require get_template_directory() . '/inc/layout-saves/usps.php';
require get_template_directory() . '/inc/layout-saves/group-section.php';
require get_template_directory() . '/inc/layout-saves/reviews.php';
require get_template_directory() . '/inc/layout-saves/careers-teaser.php';
require get_template_directory() . '/inc/layout-saves/resort-hero.php';
require get_template_directory() . '/inc/layout-saves/trust-strip.php';
require get_template_directory() . '/inc/layout-saves/hotel-showcase.php';
require get_template_directory() . '/inc/layout-saves/video-tours.php';
require get_template_directory() . '/inc/layout-saves/suite-grid.php';
require get_template_directory() . '/inc/layout-saves/dining.php';
