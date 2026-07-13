<?php
/**
 * Layout: video-tours
 */

$heading          = luux_video_tours_sub_field('heading');
$text             = luux_video_tours_sub_field('text');
$media_type_left  = luux_video_tours_sub_field('media_type_left') ?: 'image';
$image_left       = luux_video_tours_sub_field('image_left');
$video_left       = luux_acf_video_tours_attachment_id(luux_video_tours_sub_field('video_left'));
$media_type_right = luux_video_tours_sub_field('media_type_right') ?: 'image';
$image_right      = luux_video_tours_sub_field('image_right');
$video_right      = luux_acf_video_tours_attachment_id(luux_video_tours_sub_field('video_right'));
$section_id       = luux_video_tours_sub_field('section_id');

// If a video attachment exists but media type did not persist, prefer the video.
if ($media_type_left !== 'video' && $video_left) {
    $media_type_left = 'video';
}

if ($media_type_right !== 'video' && $video_right) {
    $media_type_right = 'video';
}

/**
 * Render a single media slot as either a looping video or an image.
 * Respects the selected media type — never falls back to a stale image when Video is chosen.
 */
$render_media = static function ($type, $image_id, $video_id) {
    if ($type === 'video') {
        if (! $video_id) {
            return;
        }

        $video_url  = wp_get_attachment_url($video_id);
        $video_mime = get_post_mime_type($video_id);

        if (! $video_url) {
            return;
        }
        ?>
        <div class="video-tours__media">
            <video class="h-full w-full object-cover" autoplay muted loop playsinline>
                <source src="<?php echo esc_url($video_url); ?>"<?php echo $video_mime ? ' type="' . esc_attr($video_mime) . '"' : ''; ?>>
            </video>
        </div>
        <?php
        return;
    }

    if (! $image_id) {
        return;
    }
    ?>
    <div class="video-tours__media">
        <?php echo wp_get_attachment_image($image_id, 'large', false, [
            'class'   => 'h-full w-full object-cover',
            'loading' => 'lazy',
        ]); ?>
    </div>
    <?php
};
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="video-tours section-pad">
    <div class="container-site video-tours__grid">
        <?php $render_media($media_type_left, $image_left, $video_left); ?>

        <?php if ($heading || $text) : ?>
            <div class="video-tours__copy">
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h3 text-brand-primary lg:text-h2"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ($text) : ?>
                    <div class="video-tours__text font-body text-body text-brand-primary"><?php echo wp_kses_post($text); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php $render_media($media_type_right, $image_right, $video_right); ?>
    </div>
</section>
