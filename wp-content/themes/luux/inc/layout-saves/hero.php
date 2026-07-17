<?php
/**
 * Hero layout — legacy frontend read helpers.
 * Save hooks live in shared.php + homepage-layouts.php.
 */

defined('ABSPATH') || exit;

/**
 * Read a hero CTA repeater from postmeta on legacy pages.
 *
 * @return list<array{link: array<string, string>}>
 */
function luux_hero_ctas_from_meta(int $post_id, int $row_index): array {
    $rows = luux_layout_read_repeater_from_meta($post_id, $row_index, 'ctas', [
        ['name' => 'link', 'key' => 'field_luux_hero_cta_link', 'type' => 'link'],
    ]);

    $ctas = [];

    foreach ($rows as $row) {
        if (! empty($row['link']) && is_array($row['link'])) {
            $ctas[] = ['link' => $row['link']];
        }
    }

    return $ctas;
}
