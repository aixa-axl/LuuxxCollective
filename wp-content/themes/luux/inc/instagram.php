<?php
/**
 * Instagram feed — Graph API, transient cache, automated token refresh.
 *
 * Cron setup (WP Engine → Utilities → Cron):
 *   POST /wp-json/luux/v1/instagram/refresh
 *   Header: Authorization: Bearer {LUUX_CRON_SECRET}
 *
 * Set LUUX_CRON_SECRET in wp-config.php or WP Engine environment variables.
 */

defined('ABSPATH') || exit;

/** Days before expiry to attempt a token refresh. */
const LUUX_INSTAGRAM_REFRESH_WINDOW_DAYS = 14;

/**
 * Secret used to authorise external cron requests.
 */
function luux_cron_secret(): string {
    if (defined('LUUX_CRON_SECRET') && LUUX_CRON_SECRET !== '') {
        return (string) LUUX_CRON_SECRET;
    }

    $env = getenv('LUUX_CRON_SECRET');
    return $env !== false ? (string) $env : '';
}

/**
 * Default Instagram profile for this site.
 */
function luux_instagram_username(): string {
    $username = function_exists('get_field') ? get_field('instagram_username', 'option') : '';
    return $username ?: 'luxxcollective.travel';
}

/**
 * Instagram profile URL.
 */
function luux_instagram_profile_url(): string {
    return 'https://www.instagram.com/' . rawurlencode(luux_instagram_username()) . '/';
}

/**
 * Cache duration in seconds.
 */
function luux_instagram_cache_ttl(): int {
    $hours = function_exists('get_field') ? (int) get_field('instagram_cache_hours', 'option') : 1;
    return max(1, $hours) * HOUR_IN_SECONDS;
}

/**
 * Persist an Instagram Site Option field.
 */
function luux_instagram_set_option(string $name, $value): void {
    if (function_exists('update_field')) {
        update_field($name, $value, 'option');
    }
}

/**
 * Token expiry as Unix timestamp (0 if unknown).
 */
function luux_instagram_token_expires_at(): int {
    if (! function_exists('get_field')) {
        return 0;
    }

    $stored = get_field('instagram_token_expires_at', 'option');
    if (is_numeric($stored)) {
        return (int) $stored;
    }

    if (is_string($stored) && $stored !== '') {
        $parsed = strtotime($stored);
        return $parsed !== false ? $parsed : 0;
    }

    return 0;
}

/**
 * Whether the access token should be refreshed.
 */
function luux_instagram_token_needs_refresh(): bool {
    $expires = luux_instagram_token_expires_at();
    if ($expires === 0) {
        return false;
    }

    return ($expires - time()) <= (LUUX_INSTAGRAM_REFRESH_WINDOW_DAYS * DAY_IN_SECONDS);
}

/**
 * Clear the Instagram feed cache.
 */
function luux_flush_instagram_cache(): void {
    delete_transient('luux_instagram_feed');
}

/**
 * Refresh the long-lived Instagram access token via Meta Graph API.
 *
 * @return array{success: bool, refreshed: bool, message: string}
 */
function luux_refresh_instagram_token(bool $force = false): array {
    if (! function_exists('get_field')) {
        return [
            'success'   => false,
            'refreshed' => false,
            'message'   => __('ACF is not available.', 'luux'),
        ];
    }

    $token = trim((string) get_field('instagram_access_token', 'option'));
    if ($token === '') {
        return [
            'success'   => false,
            'refreshed' => false,
            'message'   => __('No Instagram access token configured.', 'luux'),
        ];
    }

    if (! $force && ! luux_instagram_token_needs_refresh()) {
        $expires = luux_instagram_token_expires_at();
        return [
            'success'   => true,
            'refreshed' => false,
            'message'   => sprintf(
                /* translators: %s: human-readable expiry date */
                __('Token still valid until %s — refresh skipped.', 'luux'),
                $expires > 0 ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $expires) : __('unknown', 'luux')
            ),
        ];
    }

    $url = add_query_arg([
        'grant_type'   => 'ig_refresh_token',
        'access_token' => $token,
    ], 'https://graph.instagram.com/refresh_access_token');

    $response = wp_remote_get($url, [
        'timeout' => 15,
        'headers' => ['Accept' => 'application/json'],
    ]);

    if (is_wp_error($response)) {
        $message = $response->get_error_message();
        luux_instagram_set_option('instagram_refresh_error', $message);

        return [
            'success'   => false,
            'refreshed' => false,
            'message'   => $message,
        ];
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode((string) wp_remote_retrieve_body($response), true);

    if ($code !== 200 || empty($body['access_token'])) {
        $message = isset($body['error']['message'])
            ? (string) $body['error']['message']
            : __('Instagram token refresh failed.', 'luux');

        luux_instagram_set_option('instagram_refresh_error', $message);

        return [
            'success'   => false,
            'refreshed' => false,
            'message'   => $message,
        ];
    }

    $new_token  = trim((string) $body['access_token']);
    $expires_in = isset($body['expires_in']) ? (int) $body['expires_in'] : (60 * DAY_IN_SECONDS);
    $expires_at = time() + max($expires_in, DAY_IN_SECONDS);

    luux_instagram_set_option('instagram_access_token', $new_token);
    luux_instagram_set_option('instagram_token_expires_at', $expires_at);
    luux_instagram_set_option('instagram_last_refresh', time());
    luux_instagram_set_option('instagram_refresh_error', '');
    luux_flush_instagram_cache();

    return [
        'success'   => true,
        'refreshed' => true,
        'message'   => sprintf(
            /* translators: %s: human-readable expiry date */
            __('Token refreshed. New expiry: %s.', 'luux'),
            wp_date(get_option('date_format') . ' ' . get_option('time_format'), $expires_at)
        ),
    ];
}

/**
 * Fetch media from Instagram Graph API.
 *
 * @return array{posts: array<int, array<string, string>>, error_code: int}
 */
function luux_fetch_instagram_media(string $token, string $user_id, int $limit): array {
    $url = add_query_arg([
        'fields'       => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp',
        'limit'        => $limit,
        'access_token' => $token,
    ], 'https://graph.instagram.com/' . rawurlencode($user_id) . '/media');

    $response = wp_remote_get($url, [
        'timeout' => 15,
        'headers' => ['Accept' => 'application/json'],
    ]);

    if (is_wp_error($response)) {
        return ['posts' => [], 'error_code' => 0];
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode((string) wp_remote_retrieve_body($response), true);

    if ($code !== 200 || empty($body['data']) || ! is_array($body['data'])) {
        return ['posts' => [], 'error_code' => $code];
    }

    $posts = [];

    foreach ($body['data'] as $item) {
        if (empty($item['id']) || empty($item['permalink'])) {
            continue;
        }

        $media_type = (string) ($item['media_type'] ?? 'IMAGE');
        $image_url  = (string) ($item['media_url'] ?? '');

        if ($media_type === 'VIDEO' && ! empty($item['thumbnail_url'])) {
            $image_url = (string) $item['thumbnail_url'];
        }

        if ($image_url === '') {
            continue;
        }

        $posts[] = [
            'id'         => (string) $item['id'],
            'permalink'  => (string) $item['permalink'],
            'image_url'  => $image_url,
            'caption'    => (string) ($item['caption'] ?? ''),
            'media_type' => $media_type,
        ];
    }

    return ['posts' => $posts, 'error_code' => 0];
}

/**
 * Fetch recent Instagram posts.
 *
 * @return array<int, array{id: string, permalink: string, image_url: string, caption: string, media_type: string}>
 */
function luux_get_instagram_posts(?int $limit = null): array {
    if (! function_exists('get_field')) {
        return [];
    }

    $token   = trim((string) get_field('instagram_access_token', 'option'));
    $user_id = trim((string) get_field('instagram_user_id', 'option'));

    if ($token === '' || $user_id === '') {
        return [];
    }

    if ($limit === null) {
        $limit = (int) get_field('instagram_post_count', 'option');
    }
    $limit = max(1, min(25, $limit ?: 8));

    $cache_key = 'luux_instagram_feed';
    $cached    = get_transient($cache_key);
    if (is_array($cached)) {
        return array_slice($cached, 0, $limit);
    }

    $result = luux_fetch_instagram_media($token, $user_id, 25);

    if ($result['error_code'] === 401 || $result['error_code'] === 400) {
        $refresh = luux_refresh_instagram_token(true);
        if ($refresh['refreshed']) {
            $token  = trim((string) get_field('instagram_access_token', 'option'));
            $result = luux_fetch_instagram_media($token, $user_id, 25);
        }
    }

    $posts = $result['posts'];
    set_transient($cache_key, $posts, luux_instagram_cache_ttl());

    return array_slice($posts, 0, $limit);
}

/**
 * REST API permission check for cron endpoint.
 */
function luux_rest_instagram_refresh_permission(WP_REST_Request $request): bool {
    $secret = luux_cron_secret();
    if ($secret === '') {
        return false;
    }

    $auth = (string) $request->get_header('authorization');
    if ($auth !== '' && preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
        return hash_equals($secret, trim($matches[1]));
    }

    $header = (string) $request->get_header('x_luux_cron_secret');
    return $header !== '' && hash_equals($secret, $header);
}

/**
 * REST API callback for cron token refresh.
 */
function luux_rest_instagram_refresh(WP_REST_Request $request): WP_REST_Response {
    $force = rest_sanitize_boolean($request->get_param('force'));
    $result = luux_refresh_instagram_token($force);

    return new WP_REST_Response($result, $result['success'] ? 200 : 500);
}

add_action('rest_api_init', function () {
    register_rest_route('luux/v1', '/instagram/refresh', [
        'methods'             => 'POST',
        'callback'            => 'luux_rest_instagram_refresh',
        'permission_callback' => 'luux_rest_instagram_refresh_permission',
        'args'                => [
            'force' => [
                'type'    => 'boolean',
                'default' => false,
            ],
        ],
    ]);
});

add_action('luux_instagram_scheduled_refresh', function () {
    luux_refresh_instagram_token(false);
});

add_action('init', function () {
    if (! wp_next_scheduled('luux_instagram_scheduled_refresh')) {
        wp_schedule_event(time(), 'weekly', 'luux_instagram_scheduled_refresh');
    }
});

add_action('acf/save_post', function ($post_id) {
    if ($post_id !== 'options' || ! function_exists('get_field')) {
        return;
    }

    luux_flush_instagram_cache();

    $token   = trim((string) get_field('instagram_access_token', 'option'));
    $expires = luux_instagram_token_expires_at();

    if ($token !== '' && $expires === 0) {
        luux_instagram_set_option('instagram_token_expires_at', time() + (60 * DAY_IN_SECONDS));
        luux_instagram_set_option('instagram_refresh_error', '');
    }
}, 20);
