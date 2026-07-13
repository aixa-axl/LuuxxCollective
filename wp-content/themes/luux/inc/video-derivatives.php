<?php
/**
 * Web playback helpers — auto-generate MP4 from MOV when FFmpeg is available.
 */

defined('ABSPATH') || exit;

const LUUX_VIDEO_MP4_META = '_luux_video_mp4_file';
const LUUX_VIDEO_MP4_FAILED_META = '_luux_video_mp4_failed';

/**
 * @return list<string>
 */
function luux_quicktime_mime_types(): array {
    return ['video/quicktime', 'video/mov', 'video/x-quicktime'];
}

function luux_is_quicktime_attachment(int $attachment_id): bool {
    $mime = get_post_mime_type($attachment_id);

    return is_string($mime) && in_array($mime, luux_quicktime_mime_types(), true);
}

function luux_locate_ffmpeg(): ?string {
    static $resolved = null;

    if ($resolved !== null) {
        return $resolved !== '' ? $resolved : null;
    }

    $candidates = array_filter([
        getenv('FFMPEG_PATH') ?: null,
        '/usr/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
        '/opt/homebrew/bin/ffmpeg',
    ]);

    foreach ($candidates as $path) {
        if (is_executable($path)) {
            $resolved = $path;

            return $path;
        }
    }

    if (function_exists('exec')) {
        $output = [];
        @exec('command -v ffmpeg 2>/dev/null', $output);

        if (! empty($output[0]) && is_executable($output[0])) {
            $resolved = trim($output[0]);

            return $resolved;
        }
    }

    $resolved = '';

    return null;
}

function luux_video_mp4_derivative_path(int $attachment_id): ?string {
    $relative = get_post_meta($attachment_id, LUUX_VIDEO_MP4_META, true);

    if (! is_string($relative) || $relative === '') {
        return null;
    }

    $uploads = wp_upload_dir();

    if (! empty($uploads['error'])) {
        return null;
    }

    $absolute = trailingslashit($uploads['basedir']) . ltrim($relative, '/');

    return is_readable($absolute) ? $absolute : null;
}

function luux_video_mp4_derivative_url(int $attachment_id): ?string {
    $absolute = luux_video_mp4_derivative_path($attachment_id);

    if (! $absolute) {
        return null;
    }

    $uploads = wp_upload_dir();

    if (! empty($uploads['error'])) {
        return null;
    }

    $relative = ltrim(str_replace(trailingslashit($uploads['basedir']), '', $absolute), '/');

    return trailingslashit($uploads['baseurl']) . $relative;
}

function luux_generate_video_mp4_derivative(int $attachment_id): bool {
    if (! luux_is_quicktime_attachment($attachment_id)) {
        return false;
    }

    $ffmpeg = luux_locate_ffmpeg();

    if (! $ffmpeg) {
        return false;
    }

    $source = get_attached_file($attachment_id);

    if (! $source || ! is_readable($source)) {
        return false;
    }

    $existing = luux_video_mp4_derivative_path($attachment_id);

    if ($existing) {
        return true;
    }

    $destination = preg_replace('/\.[^.]+$/', '.mp4', $source);

    if (! is_string($destination) || $destination === $source) {
        $destination = $source . '.mp4';
    }

    $uploads = wp_upload_dir();

    if (! empty($uploads['error'])) {
        return false;
    }

    $basedir = trailingslashit($uploads['basedir']);

    if (! str_starts_with(wp_normalize_path($destination), wp_normalize_path($basedir))) {
        return false;
    }

    $command = sprintf(
        '%s -y -i %s -map 0:v:0 -map 0:a:0? -c:v libx264 -preset fast -crf 23 -pix_fmt yuv420p -movflags +faststart -c:a aac -b:a 128k %s 2>&1',
        escapeshellcmd($ffmpeg),
        escapeshellarg($source),
        escapeshellarg($destination)
    );

    $output = [];
    $code   = 1;
    @exec($command, $output, $code);

    if ($code !== 0 || ! is_readable($destination)) {
        return false;
    }

    $relative = ltrim(str_replace($basedir, '', $destination), '/');
    update_post_meta($attachment_id, LUUX_VIDEO_MP4_META, $relative);
    delete_post_meta($attachment_id, LUUX_VIDEO_MP4_FAILED_META);

    return true;
}

function luux_maybe_generate_video_mp4_derivative(int $attachment_id): void {
    if ($attachment_id < 1 || get_post_type($attachment_id) !== 'attachment') {
        return;
    }

    if (luux_video_mp4_derivative_path($attachment_id)) {
        return;
    }

    if (get_post_meta($attachment_id, LUUX_VIDEO_MP4_FAILED_META, true)) {
        return;
    }

    if (! luux_is_quicktime_attachment($attachment_id)) {
        return;
    }

    if (! luux_generate_video_mp4_derivative($attachment_id)) {
        update_post_meta($attachment_id, LUUX_VIDEO_MP4_FAILED_META, (string) time());
    }
}

/**
 * @return list<array{url: string, type: string}>
 */
function luux_get_video_sources(int $attachment_id): array {
    if ($attachment_id < 1) {
        return [];
    }

    luux_maybe_generate_video_mp4_derivative($attachment_id);

    $original_url = wp_get_attachment_url($attachment_id);
    $mime         = get_post_mime_type($attachment_id) ?: '';
    $mp4_url      = luux_video_mp4_derivative_url($attachment_id);
    $sources      = [];

    if ($mp4_url) {
        $sources[] = [
            'url'  => $mp4_url,
            'type' => 'video/mp4',
        ];
    } elseif ($mime === 'video/mp4' && $original_url) {
        $sources[] = [
            'url'  => $original_url,
            'type' => 'video/mp4',
        ];
    }

    if ($original_url && luux_is_quicktime_attachment($attachment_id)) {
        $already_listed = array_column($sources, 'url');

        if (! in_array($original_url, $already_listed, true)) {
            $sources[] = [
                'url'  => $original_url,
                'type' => $mime ?: 'video/quicktime',
            ];
        }
    }

    if ($sources === [] && $original_url) {
        $sources[] = [
            'url'  => $original_url,
            'type' => $mime,
        ];
    }

    return $sources;
}

function luux_render_video_sources(int $attachment_id): void {
    foreach (luux_get_video_sources($attachment_id) as $source) {
        if (empty($source['url'])) {
            continue;
        }

        echo '<source src="' . esc_url($source['url']) . '"';

        if (! empty($source['type'])) {
            echo ' type="' . esc_attr($source['type']) . '"';
        }

        echo '>';
    }
}

/**
 * Poster frame for a video attachment (WordPress-generated thumbnail when available).
 */
function luux_get_video_poster_url(int $attachment_id): string {
    if ($attachment_id < 1) {
        return '';
    }

    $meta = wp_get_attachment_metadata($attachment_id);

    if (! is_array($meta) || empty($meta['image']['file'])) {
        return '';
    }

    $video_url = wp_get_attachment_url($attachment_id);

    if (! $video_url) {
        return '';
    }

    return trailingslashit(dirname($video_url)) . $meta['image']['file'];
}

add_action('add_attachment', function (int $attachment_id): void {
    if (! luux_is_quicktime_attachment($attachment_id)) {
        return;
    }

    wp_schedule_single_event(time() + 10, 'luux_convert_quicktime_attachment', [$attachment_id]);
}, 20);

add_action('luux_convert_quicktime_attachment', function (int $attachment_id): void {
    luux_maybe_generate_video_mp4_derivative($attachment_id);
});
