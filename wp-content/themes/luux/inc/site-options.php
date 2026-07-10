<?php
/**
 * Site Options helpers — reads flat social/legal fields (repeater-free).
 */

defined('ABSPATH') || exit;

/**
 * @return array<int, array{label: string, url: string, icon: int|string}>
 */
function luux_get_social_links(): array {
    if (! function_exists('get_field')) {
        return [];
    }

    $legacy = get_field('social_links', 'option');
    if (is_array($legacy) && $legacy !== []) {
        return $legacy;
    }

    $links = [];

    for ($i = 1; $i <= 4; $i++) {
        $url = trim((string) get_field('social_' . $i . '_url', 'option'));
        if ($url === '') {
            continue;
        }

        $links[] = [
            'label' => (string) get_field('social_' . $i . '_label', 'option'),
            'url'   => $url,
            'icon'  => get_field('social_' . $i . '_icon', 'option'),
        ];
    }

    return $links;
}

/**
 * @return array<int, array{link: array{url: string, title: string, target: string}}>
 */
function luux_get_legal_links(): array {
    if (! function_exists('get_field')) {
        return [];
    }

    $legacy = get_field('legal_links', 'option');
    if (is_array($legacy) && $legacy !== []) {
        return $legacy;
    }

    $links = [];

    for ($i = 1; $i <= 4; $i++) {
        $link = get_field('legal_link_' . $i, 'option');
        if (! is_array($link) || empty($link['url'])) {
            continue;
        }

        $links[] = ['link' => $link];
    }

    return $links;
}
