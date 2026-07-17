<?php
/**
 * Layout: resort-hero
 */

$eyebrow    = luux_sub_field('eyebrow');
$heading    = luux_sub_field('heading');
$text       = luux_sub_field('text');
$media_type = luux_sub_field('media_type') ?: 'image';
$image_id   = luux_sub_field('background_image');
$video_id   = luux_sub_field('background_video');
$cta        = get_sub_field('cta');
$section_id = luux_sub_field('section_id');

$post_id   = get_the_ID();
$row_index = function_exists('luux_section_row_index') ? luux_section_row_index() : -1;

if (
    $post_id
    && $row_index >= 0
    && function_exists('luux_page_sections_uses_legacy_storage')
    && luux_page_sections_uses_legacy_storage($post_id)
    && function_exists('luux_resort_hero_cta_from_meta')
) {
    $cta_from_meta = luux_resort_hero_cta_from_meta((int) $post_id, $row_index);

    if ($cta_from_meta !== null) {
        $cta = $cta_from_meta;
    }
}

if (is_array($cta) && ! empty($cta['title'])) {
    $cta['title'] = str_replace(['\\u2192', 'u2192'], '→', (string) $cta['title']);
}

$has_video = ($media_type === 'video' && $video_id);
$has_media = $has_video || $image_id;
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="resort-hero resort-hero--bleed relative flex min-h-[640px] flex-col lg:min-h-[780px]">
    <?php if ($has_media) : ?>
        <div class="resort-hero__media" aria-hidden="true">
            <?php if ($has_video) :
                $video_url  = wp_get_attachment_url($video_id);
                $video_mime = get_post_mime_type($video_id);
                ?>
                <video class="resort-hero__media-el" autoplay muted loop playsinline>
                    <?php if ($video_url) : ?>
                        <source src="<?php echo esc_url($video_url); ?>"<?php echo $video_mime ? ' type="' . esc_attr($video_mime) . '"' : ''; ?>>
                    <?php endif; ?>
                </video>
            <?php elseif ($image_id) : ?>
                <?php echo wp_get_attachment_image($image_id, 'full', false, [
                    'class'         => 'resort-hero__media-el',
                    'fetchpriority' => 'high',
                ]); ?>
            <?php endif; ?>
            <div class="resort-hero__scrim"></div>
        </div>
    <?php endif; ?>

    <div class="container-site resort-hero__content relative z-10 flex flex-1 flex-col justify-end pb-10 pt-20">
        <div class="flex max-w-2xl flex-col gap-6 text-brand-white lg:max-w-[37.5rem] lg:gap-10">
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
