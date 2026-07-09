<?php
/**
 * Layout: dining — Figma 76:3353
 */

$section_label       = get_sub_field('section_label');
$heading             = get_sub_field('heading');
$text                = get_sub_field('text');
$highlights          = get_sub_field('highlights');
$hero_media_type     = get_sub_field('hero_media_type') ?: 'image';
$image_hero          = get_sub_field('image_hero');
$hero_video_id       = get_sub_field('hero_video');
$image_top_left      = get_sub_field('image_top_left') ?: get_sub_field('image_left');
$image_bottom_left   = get_sub_field('image_bottom_left');
$image_top           = get_sub_field('image_top');
$image_bottom        = get_sub_field('image_bottom');
$section_id          = get_sub_field('section_id');

$has_hero = ($hero_media_type === 'video' && $hero_video_id) || ($hero_media_type !== 'video' && $image_hero);
$has_mosaic = $image_top_left || $image_bottom_left || $image_top || $image_bottom;

$mosaic_classes = 'dining__mosaic';
if ( ! $image_bottom_left ) {
    $mosaic_classes .= ' dining__mosaic--no-bottom-left';
}

// A column with a single image gets a modifier so it can be sized taller.
$left_single_class  = ($image_top_left && ! $image_bottom_left) || ($image_bottom_left && ! $image_top_left)
    ? ' dining__mosaic-col--single' : '';
$right_single_class = ($image_top && ! $image_bottom) || ($image_bottom && ! $image_top)
    ? ' dining__mosaic-col--single' : '';

// Ordered slides for the mobile-only carousel (all media in one track).
$carousel_slides = [];
if ($hero_media_type === 'video' && $hero_video_id) {
    $carousel_slides[] = ['type' => 'video', 'id' => $hero_video_id];
} elseif ($image_hero) {
    $carousel_slides[] = ['type' => 'image', 'id' => $image_hero];
}
foreach ([$image_top_left, $image_top, $image_bottom_left, $image_bottom] as $carousel_image) {
    if ($carousel_image) {
        $carousel_slides[] = ['type' => 'image', 'id' => $carousel_image];
    }
}
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="dining section-pad bg-brand-cream-light">
    <div class="container-site dining__layout">
        <div class="dining__intro">
            <?php if ($section_label) : ?>
                <p class="font-body text-body uppercase text-brand-gold"><?php echo esc_html($section_label); ?></p>
            <?php endif; ?>
            <?php if ($heading) : ?>
                <h2 class="font-display text-h3 text-brand-dark lg:text-h2"><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>
            <?php if ($text) : ?>
                <p class="dining__intro-text font-body text-body-lg text-brand-gold-muted"><?php echo esc_html($text); ?></p>
            <?php endif; ?>
            <?php if ($highlights) : ?>
                <ul class="dining__highlights">
                    <?php foreach ($highlights as $item) :
                        if (empty($item['text'])) continue;
                        ?>
                        <li class="font-body text-body text-brand-dark"><?php echo esc_html($item['text']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <?php if ($has_hero) : ?>
            <div class="dining__hero<?php echo ($hero_media_type === 'video' && $hero_video_id) ? ' dining__hero--video' : ''; ?>">
                <?php if ($hero_media_type === 'video' && $hero_video_id) :
                    $hero_video_url  = wp_get_attachment_url($hero_video_id);
                    $hero_video_mime = get_post_mime_type($hero_video_id);
                    ?>
                    <video class="dining__video"
                           autoplay
                           muted
                           loop
                           playsinline>
                        <?php if ($hero_video_url) : ?>
                            <source src="<?php echo esc_url($hero_video_url); ?>"<?php echo $hero_video_mime ? ' type="' . esc_attr($hero_video_mime) . '"' : ''; ?>>
                        <?php endif; ?>
                    </video>
                <?php elseif ($image_hero) : ?>
                    <?php echo wp_get_attachment_image($image_hero, 'large', false, [
                        'class'   => 'dining__hero-image',
                        'loading' => 'lazy',
                        'width'   => '624',
                        'height'  => '914',
                    ]); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($has_mosaic) : ?>
            <div class="<?php echo esc_attr($mosaic_classes); ?>">
                <div class="dining__mosaic-col dining__mosaic-col--left<?php echo $left_single_class; ?>">
                    <?php if ($image_top_left) : ?>
                        <div class="dining__image dining__image--top-left">
                            <?php echo wp_get_attachment_image($image_top_left, 'large', false, [
                                'class'   => 'dining__media',
                                'loading' => 'lazy',
                            ]); ?>
                        </div>
                    <?php endif; ?> 

                    <?php if ($image_bottom_left) : ?>
                        <div class="dining__image dining__image--bottom-left">
                            <?php echo wp_get_attachment_image($image_bottom_left, 'large', false, [
                                'class'   => 'dining__media',
                                'loading' => 'lazy',
                            ]); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="dining__mosaic-col dining__mosaic-col--right<?php echo $right_single_class; ?>">
                    <?php if ($image_top) : ?>
                        <div class="dining__image dining__image--top-right">
                            <?php echo wp_get_attachment_image($image_top, 'large', false, [
                                'class'   => 'dining__media',
                                'loading' => 'lazy',
                            ]); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($image_bottom) : ?>
                        <div class="dining__image dining__image--bottom-right">
                            <?php echo wp_get_attachment_image($image_bottom, 'large', false, [
                                'class'   => 'dining__media',
                                'loading' => 'lazy',
                            ]); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (count($carousel_slides) > 1) : ?>
            <div class="dining__carousel" data-dining-carousel aria-label="<?php esc_attr_e('Dining gallery', 'luux'); ?>">
                <div class="dining__carousel-viewport">
                    <div class="dining__carousel-track" data-dining-track>
                        <?php foreach ($carousel_slides as $slide) : ?>
                            <div class="dining__carousel-slide">
                                <?php if ($slide['type'] === 'video') :
                                    $slide_video_url  = wp_get_attachment_url($slide['id']);
                                    $slide_video_mime = get_post_mime_type($slide['id']);
                                    ?>
                                    <video class="dining__media" autoplay muted loop playsinline>
                                        <?php if ($slide_video_url) : ?>
                                            <source src="<?php echo esc_url($slide_video_url); ?>"<?php echo $slide_video_mime ? ' type="' . esc_attr($slide_video_mime) . '"' : ''; ?>>
                                        <?php endif; ?>
                                    </video>
                                <?php else : ?>
                                    <?php echo wp_get_attachment_image($slide['id'], 'large', false, [
                                        'class'   => 'dining__media',
                                        'loading' => 'lazy',
                                    ]); ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="dining__carousel-dots" data-dining-dots role="tablist" aria-label="<?php esc_attr_e('Gallery pagination', 'luux'); ?>"></div>
            </div>
        <?php endif; ?>
    </div>
</section>
