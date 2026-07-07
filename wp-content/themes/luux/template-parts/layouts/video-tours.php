<?php
/**
 * Layout: video-tours
 */

$heading          = get_sub_field('heading');
$text             = get_sub_field('text');
$media_type_left  = get_sub_field('media_type_left') ?: 'image';
$image_left       = get_sub_field('image_left');
$video_left       = get_sub_field('video_left');
$media_type_right = get_sub_field('media_type_right') ?: 'image';
$image_right      = get_sub_field('image_right');
$video_right      = get_sub_field('video_right');
$section_id       = get_sub_field('section_id');

/**
 * Render a single media slot as either a looping video or an image.
 */
$render_media = static function ($type, $image_id, $video_id) {
    $is_video = ($type === 'video' && $video_id);
    if (! $is_video && ! $image_id) {
        return;
    }
    ?>
    <div class="video-tours__media">
        <?php if ($is_video) :
            $video_url  = wp_get_attachment_url($video_id);
            $video_mime = get_post_mime_type($video_id);
            ?>
            <video class="h-full w-full object-cover" autoplay muted loop playsinline>
                <?php if ($video_url) : ?>
                    <source src="<?php echo esc_url($video_url); ?>"<?php echo $video_mime ? ' type="' . esc_attr($video_mime) . '"' : ''; ?>>
                <?php endif; ?>
            </video>
        <?php else : ?>
            <?php echo wp_get_attachment_image($image_id, 'large', false, [
                'class'   => 'h-full w-full object-cover',
                'loading' => 'lazy',
            ]); ?>
        <?php endif; ?>
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
                    <p class="font-body text-body text-brand-primary"><?php echo esc_html($text); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php $render_media($media_type_right, $image_right, $video_right); ?>
    </div>
</section>
