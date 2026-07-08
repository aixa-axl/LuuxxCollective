<?php
/**
 * Layout: resort-hero
 */

$eyebrow    = get_sub_field('eyebrow');
$heading    = get_sub_field('heading');
$text       = get_sub_field('text');
$media_type = get_sub_field('media_type') ?: 'image';
$image_id   = get_sub_field('background_image');
$video_id   = get_sub_field('background_video');
$cta        = get_sub_field('cta');
$section_id = get_sub_field('section_id');

$has_video = ($media_type === 'video' && $video_id);
$has_media = $has_video || $image_id;
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="resort-hero relative min-h-[640px] overflow-hidden lg:min-h-[780px]">
    <?php if ($has_video) :
        $video_url  = wp_get_attachment_url($video_id);
        $video_mime = get_post_mime_type($video_id);
        ?>
        <video class="absolute inset-0 h-full w-full object-cover" autoplay muted loop playsinline>
            <?php if ($video_url) : ?>
                <source src="<?php echo esc_url($video_url); ?>"<?php echo $video_mime ? ' type="' . esc_attr($video_mime) . '"' : ''; ?>>
            <?php endif; ?>
        </video>
    <?php elseif ($image_id) : ?>
        <?php echo wp_get_attachment_image($image_id, 'full', false, [
            'class'         => 'absolute inset-0 h-full w-full object-cover',
            'fetchpriority' => 'high',
        ]); ?>
    <?php endif; ?>
    <?php if ($has_media) : ?>
        <div class="resort-hero__scrim absolute inset-0" aria-hidden="true"></div>
    <?php endif; ?>

    <div class="container-site relative z-10 flex h-full flex-col justify-end pb-12 pt-24 lg:justify-center lg:pb-0 lg:pt-0">
        <div class="flex max-w-xl flex-col gap-6 text-brand-white lg:max-w-[37.5rem] lg:gap-10">
            <?php if ($eyebrow) : ?>
                <p class="font-ui font-medium text-body uppercase"><?php echo esc_html($eyebrow); ?></p>
            <?php endif; ?>
            <?php if ($heading) : ?>
                <h1 class="font-display text-[3rem] leading-none lg:text-h1 lg:leading-[0.88]"><?php echo esc_html($heading); ?></h1>
            <?php endif; ?>
            <?php if ($text) : ?>
                <div class="font-body text-body-lg"><?php echo wp_kses_post($text); ?></div>
            <?php endif; ?>
            <?php if (! empty($cta['url'])) : ?>
                <a class="link-underline self-start text-brand-white"
                   href="<?php echo esc_url($cta['url']); ?>"
                   <?php echo ! empty($cta['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                    <?php echo esc_html($cta['title']); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>
