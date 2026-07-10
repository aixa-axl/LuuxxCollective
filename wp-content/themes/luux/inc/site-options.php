<?php
/**
 * Site Options helpers — flat social/legal fields, legacy recovery, migration.
 */

defined('ABSPATH') || exit;

/**
 * Read a raw ACF options value from wp_options (bypasses broken field-key references).
 */
function luux_get_raw_option_value(string $name) {
    $raw = get_option('options_' . $name, false);
    if ($raw === false) {
        return null;
    }

    return maybe_unserialize($raw);
}

/**
 * @return array<int, mixed>
 */
function luux_get_raw_option_array(string $name): array {
    $value = luux_get_raw_option_value($name);
    return is_array($value) ? $value : [];
}

/**
 * @return array<int, array{label: string, url: string, icon: int|string}>
 */
function luux_get_social_links(): array {
    $legacy = luux_get_raw_option_array('social_links');
    if ($legacy !== []) {
        return $legacy;
    }

    if (! function_exists('get_field')) {
        return [];
    }

    $links = [];

    for ($i = 1; $i <= 4; $i++) {
        $url = trim((string) (get_field('social_' . $i . '_url', 'option') ?: luux_get_raw_option_value('social_' . $i . '_url') ?: ''));
        if ($url === '') {
            continue;
        }

        $links[] = [
            'label' => (string) (get_field('social_' . $i . '_label', 'option') ?: luux_get_raw_option_value('social_' . $i . '_label') ?: ''),
            'url'   => $url,
            'icon'  => get_field('social_' . $i . '_icon', 'option') ?: luux_get_raw_option_value('social_' . $i . '_icon'),
        ];
    }

    return $links;
}

/**
 * @return array<int, array{link: array{url: string, title: string, target: string}}>
 */
function luux_get_legal_links(): array {
    $legacy = luux_get_raw_option_array('legal_links');
    if ($legacy !== []) {
        return $legacy;
    }

    if (! function_exists('get_field')) {
        return [];
    }

    $links = [];

    for ($i = 1; $i <= 4; $i++) {
        $link = get_field('legal_link_' . $i, 'option');
        if (! is_array($link) || empty($link['url'])) {
            $link = luux_get_raw_option_value('legal_link_' . $i);
        }
        if (! is_array($link) || empty($link['url'])) {
            continue;
        }

        $links[] = ['link' => $link];
    }

    return $links;
}

/**
 * Scalar Site Options field names stored in wp_options.
 *
 * @return list<string>
 */
function luux_site_options_scalar_fields(): array {
    return [
        'site_logo',
        'site_logo_dark',
        'site_logo_light',
        'enquire_link',
        'footer_tagline',
        'footer_group_text',
        'footer_logo_one',
        'footer_logo_two',
        'footer_disclaimer',
        'contact_intro',
        'contact_email',
        'contact_phone',
        'contact_address',
        'social_1_label',
        'social_1_url',
        'social_1_icon',
        'social_2_label',
        'social_2_url',
        'social_2_icon',
        'social_3_label',
        'social_3_url',
        'social_3_icon',
        'social_4_label',
        'social_4_url',
        'social_4_icon',
        'instagram_username',
        'instagram_user_id',
        'instagram_access_token',
        'instagram_post_count',
        'instagram_cache_hours',
        'instagram_token_expires_at',
        'instagram_last_refresh',
        'instagram_refresh_error',
        'legal_link_1',
        'legal_link_2',
        'legal_link_3',
        'legal_link_4',
    ];
}

/**
 * One-time migration: copy legacy repeater data into flat fields and re-link scalar values.
 */
function luux_migrate_site_options_legacy_data(): void {
    if (! function_exists('update_field') || get_option('luux_site_options_migrated_v2')) {
        return;
    }

    $legacy_social = luux_get_raw_option_array('social_links');
    foreach ($legacy_social as $index => $row) {
        if (! is_array($row)) {
            continue;
        }

        $slot = $index + 1;
        if ($slot > 4) {
            break;
        }

        $url = trim((string) ($row['url'] ?? ''));
        if ($url === '') {
            continue;
        }

        if (! get_field('social_' . $slot . '_url', 'option')) {
            update_field('social_' . $slot . '_label', (string) ($row['label'] ?? ''), 'option');
            update_field('social_' . $slot . '_url', $url, 'option');
            if (! empty($row['icon'])) {
                update_field('social_' . $slot . '_icon', $row['icon'], 'option');
            }
        }
    }

    $legacy_legal = luux_get_raw_option_array('legal_links');
    foreach ($legacy_legal as $index => $row) {
        if (! is_array($row) || empty($row['link']) || ! is_array($row['link'])) {
            continue;
        }

        $slot = $index + 1;
        if ($slot > 4) {
            break;
        }

        if (! get_field('legal_link_' . $slot, 'option')) {
            update_field('legal_link_' . $slot, $row['link'], 'option');
        }
    }

    foreach (luux_site_options_scalar_fields() as $name) {
        $raw = luux_get_raw_option_value($name);
        if ($raw === null || $raw === '' || $raw === false) {
            continue;
        }

        $current = get_field($name, 'option');
        if ($current !== null && $current !== '' && $current !== false) {
            continue;
        }

        update_field($name, $raw, 'option');
    }

    update_option('luux_site_options_migrated_v2', time(), false);
}

add_action('acf/init', 'luux_migrate_site_options_legacy_data', 20);

/**
 * If ACF cannot resolve a value (broken field-key reference), fall back to wp_options.
 */
add_filter('acf/load_value', function ($value, $post_id, $field) {
    if ($post_id !== 'options' && $post_id !== 'option') {
        return $value;
    }

    if ($value !== null && $value !== false && $value !== '') {
        return $value;
    }

    $name = $field['name'] ?? '';
    if ($name === '') {
        return $value;
    }

    $raw = luux_get_raw_option_value($name);
    if ($raw === null || $raw === false || $raw === '') {
        return $value;
    }

    return $raw;
}, 20, 3);
